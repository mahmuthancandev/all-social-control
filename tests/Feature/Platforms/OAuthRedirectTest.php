<?php

namespace Tests\Feature\Platforms;

use App\Enums\Platform;
use App\Services\Platforms\OAuthRedirect;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_bridge_is_used_only_for_instagram_and_tiktok(): void
    {
        Settings::set('oauth_redirect_base_url', 'https://ornek.github.io/socialhub-oauth/');

        $this->assertSame('https://ornek.github.io/socialhub-oauth/oauth/instagram/callback', OAuthRedirect::uri(Platform::Instagram));
        $this->assertSame('https://ornek.github.io/socialhub-oauth/oauth/tiktok/callback', OAuthRedirect::uri(Platform::TikTok));
        $this->assertSame(url('/oauth/youtube/callback'), OAuthRedirect::uri(Platform::YouTube));
    }

    public function test_bridge_must_be_https(): void
    {
        $this->actingAs($this->admin())->put('/settings', ['oauth_redirect_base_url' => 'http://ornek.com'])->assertSessionHasErrors('oauth_redirect_base_url');
    }
}
