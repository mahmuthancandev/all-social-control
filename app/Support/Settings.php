<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Panelden düzenlenebilen uygulama ayarları. Gizli değerler (client secret, API key)
 * APP_KEY ile şifrelenerek saklanır ve frontend'e asla açık olarak gönderilmez.
 */
class Settings
{
    /**
     * key => [type, encrypted, default, label]
     *
     * @var array<string, array{0: string, 1: bool, 2: mixed, 3: string}>
     */
    public const DEFINITIONS = [
        'youtube_client_id' => ['string', false, null, 'YouTube OAuth Client ID'],
        'youtube_client_secret' => ['string', true, null, 'YouTube OAuth Client Secret'],
        'youtube_api_key' => ['string', true, null, 'YouTube Data API anahtarı (herkese açık veriler)'],
        'youtube_daily_quota' => ['int', false, 10000, 'YouTube günlük kota (birim)'],
        'youtube_upload_cost' => ['int', false, 1600, 'YouTube video yükleme maliyeti (birim)'],
        'instagram_app_id' => ['string', false, null, 'Instagram App ID'],
        'instagram_app_secret' => ['string', true, null, 'Instagram App Secret'],
        'instagram_public_media_base_url' => ['string', false, null, 'Instagram için herkese açık medya URL tabanı'],
        'tiktok_client_key' => ['string', false, null, 'TikTok Client Key'],
        'tiktok_client_secret' => ['string', true, null, 'TikTok Client Secret'],
        'tiktok_publish_mode' => ['string', false, 'inbox', 'TikTok yayın modu (inbox | direct)'],
        'x_api_enabled' => ['bool', false, false, 'X API kullanımı (ÜCRETLİ olabilir)'],
        'x_client_id' => ['string', false, null, 'X OAuth 2.0 Client ID'],
        'x_client_secret' => ['string', true, null, 'X OAuth 2.0 Client Secret'],
        'oauth_redirect_base_url' => ['string', false, null, 'OAuth yönlendirme adresi tabanı'],
    ];

    private const CACHE_KEY = 'socialhub.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $definition = self::DEFINITIONS[$key] ?? null;
        $all = self::all();

        if (! array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') {
            $envValue = env('SOCIALHUB_'.strtoupper($key));
            if ($envValue !== null && $envValue !== '') {
                return self::cast($envValue, $definition[0] ?? 'string');
            }

            return $default ?? ($definition[2] ?? null);
        }

        return self::cast($all[$key], $definition[0] ?? 'string');
    }

    public static function set(string $key, mixed $value): void
    {
        $definition = self::DEFINITIONS[$key] ?? ['string', false, null, $key];
        $encrypted = $definition[1];

        if ($value === null || $value === '') {
            Setting::where('key', $key)->delete();
        } else {
            $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            Setting::updateOrCreate(['key' => $key], [
                'value' => $encrypted ? Crypt::encryptString($stored) : $stored,
                'is_encrypted' => $encrypted,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public static function has(string $key): bool
    {
        $value = self::get($key);

        return $value !== null && $value !== '' && $value !== false;
    }

    /**
     * Frontend için güvenli görünüm: gizli değerler yalnızca "ayarlı mı" bilgisiyle döner.
     *
     * @return array<string, mixed>
     */
    public static function publicView(): array
    {
        $out = [];
        foreach (self::DEFINITIONS as $key => [$type, $encrypted, $default, $label]) {
            $out[$key] = [
                'label' => $label,
                'type' => $type,
                'secret' => $encrypted,
                'is_set' => self::has($key),
                'value' => $encrypted ? null : self::get($key),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string|null>
     */
    private static function all(): array
    {
        try {
            if (! Schema::hasTable('settings')) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 300, function () {
            return Setting::all()->mapWithKeys(function (Setting $s) {
                try {
                    $value = $s->is_encrypted && $s->value !== null ? Crypt::decryptString($s->value) : $s->value;
                } catch (Throwable) {
                    $value = null; // APP_KEY değiştiyse eski şifreli değer okunamaz
                }

                return [$s->key => $value];
            })->all();
        });
    }

    private static function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            default => (string) $value,
        };
    }
}
