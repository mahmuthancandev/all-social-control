<?php

namespace Tests\Feature\Platforms;

use App\Enums\Capability;
use App\Enums\Platform;
use App\Services\Media\PublicMediaUrl;
use App\Services\Platforms\PlatformManager;
use App\Support\Deployment;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicMediaTest extends TestCase
{
    use RefreshDatabase;

    private function useAppUrl(string $url): void
    {
        config(['app.url' => $url]);
        URL::forceRootUrl($url);
        URL::forceScheme(parse_url($url, PHP_URL_SCHEME));
    }

    public function test_only_public_https_urls_count_as_public(): void
    {
        foreach (['http://127.0.0.1:8000', 'https://localhost', 'https://socialhub.test', 'http://panel.ornek.com'] as $url) {
            config(['app.url' => $url]);
            $this->assertFalse(Deployment::hasPublicHttpsUrl(), $url);
        }

        config(['app.url' => 'https://panel.ornek.com']);
        $this->assertTrue(Deployment::hasPublicHttpsUrl());
    }

    public function test_instagram_publish_is_enabled_on_https_server(): void
    {
        $account = $this->account(Platform::Instagram, [
            'is_demo' => false,
            'connection_type' => 'oauth',
            'granted_scopes' => ['instagram_business_basic', 'instagram_business_content_publish'],
        ]);

        $this->assertFalse(app(PlatformManager::class)->capabilities($account)->can(Capability::PublishVideo));

        $this->useAppUrl('https://panel.ornek.com');
        $this->assertTrue(app(PlatformManager::class)->capabilities($account->fresh())->can(Capability::PublishVideo));
    }

    public function test_signed_url_serves_file_and_rejects_tampering_and_expiry(): void
    {
        Storage::fake('media');
        $this->useAppUrl('https://panel.ornek.com');

        $media = $this->media('image');
        $media->update(['is_demo' => false]);
        Storage::disk('media')->put($media->path, 'PNGDATA');

        $url = PublicMediaUrl::for($media);
        $this->assertStringStartsWith('https://panel.ornek.com/public-media/'.$media->id.'?', $url);
        $this->assertStringContainsString('signature=', $url);

        // Giriş yapmadan, imzayla erişilebilir.
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');

        // İmza bozulursa veya imzasız istenirse reddedilir.
        $this->get($url.'x')->assertForbidden();
        $this->get('/public-media/'.$media->id)->assertForbidden();

        // Süre dolunca reddedilir.
        $this->travel((int) config('socialhub.public_media_ttl_minutes') + 1)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_media_base_url_setting_still_takes_priority(): void
    {
        Settings::set('instagram_public_media_base_url', 'https://cdn.ornek.com/m');
        $media = $this->media();

        $this->assertSame('https://cdn.ornek.com/m/'.$media->path, PublicMediaUrl::for($media));
    }
}
