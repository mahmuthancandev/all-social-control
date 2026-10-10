<?php

namespace Tests\Feature\Platforms;

use App\Models\AccountCredential;
use App\Models\SocialAccount;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::set('youtube_client_id', 'client-123');
        Settings::set('youtube_client_secret', 'secret-xyz');
    }

    public function test_redirect_without_configuration_goes_to_settings(): void
    {
        Settings::set('youtube_client_id', null);

        $this->actingAs($this->admin())->get('/oauth/youtube/redirect')->assertRedirect('/settings');
    }

    public function test_redirect_uses_state_and_pkce(): void
    {
        $response = $this->actingAs($this->admin())->get('/oauth/youtube/redirect');

        $location = $response->headers->get('Location') ?? $response->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);

        $flow = session('oauth_flow');
        $this->assertSame($flow['state'], $q['state']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $expected = rtrim(strtr(base64_encode(hash('sha256', $flow['verifier'], true)), '+/', '-_'), '=');
        $this->assertSame($expected, $q['code_challenge']);
        $this->assertStringContainsString('youtube.upload', $q['scope']);
    }

    public function test_callback_with_wrong_state_is_rejected(): void
    {
        Http::fake();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/oauth/youtube/redirect');

        $this->actingAs($admin)->get('/oauth/youtube/callback?state=tampered&code=abc')
            ->assertRedirect('/accounts')->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertSame(0, SocialAccount::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'oauth.state_mismatch']);
    }

    public function test_state_is_single_use(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/oauth/youtube/redirect');
        $state = session('oauth_flow')['state'];
        Http::fake(['*' => Http::response(['error' => 'x'], 400)]);

        $this->actingAs($admin)->get("/oauth/youtube/callback?state={$state}&code=abc");
        $this->actingAs($admin)->get("/oauth/youtube/callback?state={$state}&code=abc")->assertSessionHas('error', fn ($m) => str_contains($m, 'state'));
    }

    public function test_successful_callback_creates_account_with_encrypted_tokens(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/oauth/youtube/redirect');
        $state = session('oauth_flow')['state'];

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.ACCESS-TOKEN-PLAIN',
                'refresh_token' => '1//REFRESH-TOKEN-PLAIN',
                'expires_in' => 3599,
                'scope' => 'https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/youtube.upload',
                'token_type' => 'Bearer',
            ]),
            'www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [[
                'id' => 'UC123',
                'snippet' => ['title' => 'Stüdyo Kanalı', 'customUrl' => '@studyo', 'thumbnails' => ['default' => ['url' => 'https://yt3.example/a.jpg']]],
                'statistics' => ['subscriberCount' => '1500', 'videoCount' => '42', 'hiddenSubscriberCount' => false],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU123']],
            ]]]),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => []]),
            'youtubeanalytics.googleapis.com/*' => Http::response(['columnHeaders' => [], 'rows' => []]),
            'www.googleapis.com/youtube/v3/commentThreads*' => Http::response(['items' => []]),
        ]);

        $this->actingAs($admin)->get("/oauth/youtube/callback?state={$state}&code=auth-code")->assertSessionHas('success');

        $account = SocialAccount::firstOrFail();
        $this->assertSame('UC123', $account->external_id);
        $this->assertSame('studyo', $account->handle);
        $this->assertSame(1500, $account->followers_count);
        $this->assertSame('oauth', $account->connection_type);

        // Token veritabanında şifreli, modelde çözülmüş.
        $raw = DB::table('account_credentials')->value('access_token');
        $this->assertStringNotContainsString('ACCESS-TOKEN-PLAIN', $raw);
        $this->assertSame('ya29.ACCESS-TOKEN-PLAIN', AccountCredential::first()->access_token);

        // PKCE doğrulayıcısı token isteğine gönderildi.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && filled($r['code_verifier']));

        // Token hiçbir zaman sayfa verisine sızmaz.
        $this->actingAs($admin)->get("/accounts/{$account->id}")->assertDontSee('ACCESS-TOKEN-PLAIN')->assertDontSee('REFRESH-TOKEN-PLAIN');
        $this->assertStringNotContainsString('ACCESS-TOKEN-PLAIN', (string) @file_get_contents(storage_path('logs/laravel.log')));
    }

    public function test_viewer_cannot_start_oauth(): void
    {
        $this->actingAs($this->viewer())->get('/oauth/youtube/redirect')->assertForbidden();
    }
}
