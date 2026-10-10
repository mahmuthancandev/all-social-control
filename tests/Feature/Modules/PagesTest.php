<?php

namespace Tests\Feature\Modules;

use App\Enums\Platform;
use App\Models\Project;
use App\Services\Demo\DemoDataGenerator;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private const SHARED = ['/dashboard', '/dashboard?range=90', '/projects', '/projects/1', '/accounts', '/accounts/1', '/posts', '/posts/1', '/media', '/calendar', '/calendar?view=week', '/calendar?view=day', '/comments', '/analytics', '/analytics?group=project&interval=month', '/compare', '/notifications', '/settings/password'];

    private const ADMIN_ONLY = ['/posts/create', '/posts/1/edit', '/settings', '/admin/users'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin();
        app(DemoDataGenerator::class)->generate();
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = $this->admin();
        foreach ([...self::SHARED, ...self::ADMIN_ONLY] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_viewer_sees_read_pages_but_not_admin_pages(): void
    {
        $viewer = $this->viewer();
        foreach (self::SHARED as $url) {
            $this->actingAs($viewer)->get($url)->assertOk();
        }
        foreach (self::ADMIN_ONLY as $url) {
            $this->actingAs($viewer)->get($url)->assertForbidden();
        }
    }

    public function test_viewer_cannot_mutate_anything(): void
    {
        $viewer = $this->viewer();
        $this->actingAs($viewer)->post('/projects', ['name' => 'X', 'type' => 'game', 'color' => '#ffffff'])->assertForbidden();
        $this->actingAs($viewer)->delete('/projects/1')->assertForbidden();
        $this->actingAs($viewer)->post('/accounts', ['platform' => 'x', 'handle' => 'a', 'connection_type' => 'manual'])->assertForbidden();
        $this->actingAs($viewer)->delete('/accounts/1')->assertForbidden();
        $this->actingAs($viewer)->delete('/posts/1')->assertForbidden();
        $this->actingAs($viewer)->put('/settings', ['youtube_api_key' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->delete('/settings/demo')->assertForbidden();
    }

    public function test_settings_secrets_are_encrypted_and_never_exposed(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put('/settings', ['youtube_client_secret' => 'SUPER-SECRET-VALUE', 'youtube_client_id' => 'cid'])->assertSessionHas('success');

        $raw = DB::table('settings')->where('key', 'youtube_client_secret')->value('value');
        $this->assertStringNotContainsString('SUPER-SECRET-VALUE', $raw);
        $this->assertSame('SUPER-SECRET-VALUE', Settings::get('youtube_client_secret'));

        $this->actingAs($admin)->get('/settings')->assertOk()->assertDontSee('SUPER-SECRET-VALUE');

        // Boş gönderilen gizli alan mevcut değeri silmez.
        $this->actingAs($admin)->put('/settings', ['youtube_client_secret' => '']);
        $this->assertSame('SUPER-SECRET-VALUE', Settings::get('youtube_client_secret'));
    }

    public function test_demo_data_is_separated_and_purgeable(): void
    {
        $admin = $this->admin();
        $real = $this->account(Platform::YouTube, ['is_demo' => false, 'connection_type' => 'manual', 'handle' => 'gercek']);

        $this->actingAs($admin)->delete('/settings/demo')->assertSessionHas('success');

        $this->assertDatabaseMissing('social_accounts', ['is_demo' => true]);
        $this->assertDatabaseMissing('projects', ['is_demo' => true]);
        $this->assertDatabaseMissing('account_metric_snapshots', ['is_demo' => true]);
        $this->assertModelExists($real);
    }

    public function test_project_crud(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/projects', ['name' => 'Yeni Oyun', 'type' => 'game', 'color' => '#123456', 'account_ids' => [1, 2]])->assertRedirect();
        $project = Project::where('name', 'Yeni Oyun')->firstOrFail();
        $this->assertSame('yeni-oyun', $project->slug);
        $this->assertCount(2, $project->socialAccounts);

        $this->actingAs($admin)->post("/projects/{$project->id}", ['name' => 'Yeni Oyun 2', 'type' => 'app', 'color' => '#123456', 'account_ids' => []])->assertSessionHas('success');
        $this->assertCount(0, $project->fresh()->socialAccounts);

        $this->actingAs($admin)->delete("/projects/{$project->id}")->assertRedirect('/projects');
        $this->assertModelMissing($project);
    }
}
