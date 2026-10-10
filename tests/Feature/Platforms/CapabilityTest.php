<?php

namespace Tests\Feature\Platforms;

use App\Enums\AccountStatus;
use App\Enums\Capability;
use App\Enums\ContentType;
use App\Enums\Platform;
use App\Services\Platforms\PlatformManager;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityTest extends TestCase
{
    use RefreshDatabase;

    private function caps(Platform $platform, array $attrs)
    {
        $account = $this->account($platform, $attrs + ['is_demo' => false]);

        return app(PlatformManager::class)->capabilities($account);
    }

    public function test_youtube_capabilities_follow_granted_scopes(): void
    {
        $readOnly = $this->caps(Platform::YouTube, ['connection_type' => 'oauth', 'granted_scopes' => ['https://www.googleapis.com/auth/youtube.readonly']]);
        $this->assertTrue($readOnly->can(Capability::ReadFollowers));
        $this->assertFalse($readOnly->can(Capability::PublishVideo));
        $this->assertFalse($readOnly->can(Capability::ReplyComments));

        $full = $this->caps(Platform::YouTube, ['connection_type' => 'oauth', 'granted_scopes' => [
            'https://www.googleapis.com/auth/youtube.readonly', 'https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.force-ssl',
        ]]);
        $this->assertTrue($full->canPublish(ContentType::YouTubeShort));
        $this->assertTrue($full->can(Capability::ReplyComments));
        $this->assertFalse($full->can(Capability::PublishImage));
    }

    public function test_disconnected_oauth_account_has_no_capabilities(): void
    {
        $caps = $this->caps(Platform::YouTube, ['connection_type' => 'oauth', 'status' => AccountStatus::NeedsReauth, 'granted_scopes' => ['https://www.googleapis.com/auth/youtube.upload']]);
        $this->assertFalse($caps->can(Capability::PublishVideo));
        $this->assertStringContainsString('yeniden bağlayın', $caps->reason(Capability::PublishVideo));
    }

    public function test_instagram_publish_requires_public_media_url(): void
    {
        $attrs = ['connection_type' => 'oauth', 'granted_scopes' => ['instagram_business_basic', 'instagram_business_content_publish']];
        $without = $this->caps(Platform::Instagram, $attrs);
        $this->assertFalse($without->can(Capability::PublishVideo));
        $this->assertStringContainsString('herkese açık', $without->reason(Capability::PublishVideo));

        Settings::set('instagram_public_media_base_url', 'https://cdn.example.com/media');
        $with = $this->caps(Platform::Instagram, $attrs);
        $this->assertTrue($with->can(Capability::PublishVideo));
    }

    public function test_tiktok_comments_are_never_claimed(): void
    {
        $caps = $this->caps(Platform::TikTok, ['connection_type' => 'oauth', 'granted_scopes' => ['user.info.basic', 'user.info.stats', 'video.list', 'video.upload']]);
        $this->assertTrue($caps->can(Capability::PublishVideo));
        $this->assertFalse($caps->can(Capability::ReadComments));
        $this->assertFalse($caps->can(Capability::ReplyComments));
    }

    public function test_x_api_is_disabled_by_default(): void
    {
        $caps = $this->caps(Platform::X, ['connection_type' => 'oauth', 'granted_scopes' => ['tweet.read', 'tweet.write', 'users.read']]);
        $this->assertFalse($caps->can(Capability::PublishText));
        $this->assertStringContainsString('ücretli', $caps->reason(Capability::PublishText));
    }

    public function test_manual_accounts_have_no_api_capabilities(): void
    {
        $caps = $this->caps(Platform::YouTube, ['connection_type' => 'manual', 'status' => AccountStatus::Manual]);
        foreach (Capability::cases() as $c) {
            $this->assertFalse($caps->can($c));
        }
    }

    public function test_youtube_api_key_mode_is_read_only(): void
    {
        Settings::set('youtube_api_key', 'AIza-test');
        $caps = $this->caps(Platform::YouTube, ['connection_type' => 'api_key']);
        $this->assertTrue($caps->can(Capability::ReadFollowers));
        $this->assertTrue($caps->can(Capability::ReadComments));
        $this->assertFalse($caps->can(Capability::PublishVideo));
        $this->assertFalse($caps->can(Capability::ReadAnalytics));
    }
}
