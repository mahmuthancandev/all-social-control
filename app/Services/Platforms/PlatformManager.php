<?php

namespace App\Services\Platforms;

use App\Enums\Platform;
use App\Models\SocialAccount;
use App\Services\Platforms\Contracts\SocialPlatformInterface;
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Drivers\DemoPlatformService;
use App\Services\Platforms\Drivers\InstagramService;
use App\Services\Platforms\Drivers\TikTokService;
use App\Services\Platforms\Drivers\XService;
use App\Services\Platforms\Drivers\YouTubeService;

class PlatformManager
{
    /**
     * @var array<string, class-string<SocialPlatformInterface>>
     */
    private const DRIVERS = [
        'youtube' => YouTubeService::class,
        'instagram' => InstagramService::class,
        'tiktok' => TikTokService::class,
        'x' => XService::class,
    ];

    public function driver(Platform $platform): SocialPlatformInterface
    {
        return app(self::DRIVERS[$platform->value]);
    }

    /**
     * Hesap için doğru sürücü: demo hesaplar hiçbir zaman gerçek API'ye gitmez.
     */
    public function for(SocialAccount $account): SocialPlatformInterface
    {
        return $account->is_demo
            ? new DemoPlatformService($account->platform)
            : $this->driver($account->platform);
    }

    public function capabilities(SocialAccount $account): CapabilitySet
    {
        return $this->for($account)->capabilities($account);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function overview(): array
    {
        return array_map(function (Platform $platform) {
            $driver = $this->driver($platform);

            return [
                'platform' => $platform->value,
                'label' => $platform->label(),
                'color' => $platform->color(),
                'configured' => $driver->isConfigured(),
                'notes' => $driver->setupNotes(),
                'scopes' => $driver->oauthScopes(),
                'redirect_uri' => OAuthRedirect::uri($platform),
            ];
        }, Platform::cases());
    }
}
