<?php

namespace Tests\Feature\Publishing;

use App\Enums\Platform;
use App\Enums\TargetStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_multi_platform_draft(): void
    {
        $admin = $this->admin();
        $yt = $this->account(Platform::YouTube);
        $x = $this->account(Platform::X);
        $video = $this->media();

        $this->actingAs($admin)->post('/posts', [
            'action' => 'draft',
            'title' => 'Fragman',
            'body' => 'Yeni sezon',
            'hashtags' => ['oyun'],
            'media_ids' => [$video->id],
            'targets' => [
                ['social_account_id' => $yt->id, 'content_type' => 'youtube_short', 'title' => 'Fragman', 'caption' => 'Yeni sezon', 'hashtags' => ['oyun'], 'media_file_id' => $video->id],
                ['social_account_id' => $x->id, 'content_type' => 'x_post', 'caption' => 'Yeni sezon X için kısa', 'hashtags' => []],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $post = Post::firstOrFail();
        $this->assertCount(2, $post->targets);
        $this->assertSame('Yeni sezon X için kısa', $post->targets->firstWhere('platform', Platform::X)->caption);
        $this->assertTrue($post->media->contains($video));
    }

    public function test_platform_rules_are_validated(): void
    {
        $admin = $this->admin();
        $x = $this->account(Platform::X);
        $ig = $this->account(Platform::Instagram);
        $video = $this->media();

        $this->actingAs($admin)->post('/posts', [
            'action' => 'draft',
            'targets' => [
                ['social_account_id' => $x->id, 'content_type' => 'x_post', 'caption' => str_repeat('a', 300)],
                ['social_account_id' => $ig->id, 'content_type' => 'instagram_post', 'caption' => 'x', 'media_file_id' => $video->id],
            ],
        ])->assertSessionHasErrors(['targets.0.caption', 'targets.1.media_file_id']);

        $this->assertSame(0, Post::count());
    }

    public function test_content_type_must_match_account_platform(): void
    {
        $admin = $this->admin();
        $yt = $this->account(Platform::YouTube);

        $this->actingAs($admin)->post('/posts', [
            'action' => 'draft',
            'targets' => [['social_account_id' => $yt->id, 'content_type' => 'tiktok_video', 'media_file_id' => $this->media()->id]],
        ])->assertSessionHasErrors('targets.0.content_type');
    }

    public function test_schedule_requires_future_date_and_sets_status(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $yt = $this->account(Platform::YouTube);
        $video = $this->media();
        $payload = [
            'action' => 'schedule',
            'title' => 'Plan',
            'targets' => [['social_account_id' => $yt->id, 'content_type' => 'youtube_video', 'title' => 'Plan', 'media_file_id' => $video->id]],
        ];

        $this->actingAs($admin)->post('/posts', $payload + ['scheduled_at' => now()->subHour()->format('Y-m-d\TH:i')])->assertSessionHasErrors('scheduled_at');

        $this->actingAs($admin)->post('/posts', $payload + ['scheduled_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $target = Post::firstOrFail()->targets()->first();
        $this->assertSame(TargetStatus::Scheduled, $target->status);
        $this->assertSame('api', $target->publish_mode);
        $this->assertSame('scheduled', Post::first()->status->value);
    }

    public function test_viewer_cannot_open_composer_or_create(): void
    {
        $viewer = $this->viewer();
        $this->actingAs($viewer)->get('/posts/create')->assertForbidden();
        $this->actingAs($viewer)->post('/posts', ['action' => 'draft', 'targets' => []])->assertForbidden();
    }
}
