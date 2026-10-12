<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Services\Platforms\Exceptions\NotConfiguredException;
use App\Support\Deployment;
use App\Support\Settings;
use Illuminate\Support\Facades\URL;

/**
 * Instagram (Meta) medyayı kendisi indirdiği için dosyaya internetten erişilebilir bir adres gerekir.
 *
 * Öncelik:
 *  1. Ayarlar'daki "herkese açık medya URL tabanı" (medyayı başka bir yerde barındıranlar için),
 *  2. APP_URL herkese açık bir https adresiyse panelin kendi süreli, imzalı indirme adresi (VPS kurulumu).
 *
 * İmzalı adres yalnızca o dosyayı ve yalnızca kısa bir süre açar; giriş gerektirmez ama tahmin edilemez.
 */
class PublicMediaUrl
{
    public static function available(): bool
    {
        return Settings::has('instagram_public_media_base_url') || Deployment::hasPublicHttpsUrl();
    }

    public static function unavailableReason(): string
    {
        return 'Meta medyayı herkese açık bir URL\'den indirir. Panel https alan adıyla (VPS) çalışmıyor ve Ayarlar\'da "herkese açık medya URL tabanı" tanımlı değil. Paylaşım Asistanı kullanılır.';
    }

    public static function for(MediaFile $media): string
    {
        $base = rtrim((string) Settings::get('instagram_public_media_base_url'), '/');
        if ($base !== '') {
            return $base.'/'.ltrim($media->path, '/');
        }

        if (Deployment::hasPublicHttpsUrl()) {
            return URL::temporarySignedRoute(
                'public-media.show',
                now()->addMinutes(max(10, (int) config('socialhub.public_media_ttl_minutes', 180))),
                ['media' => $media->id],
            );
        }

        throw new NotConfiguredException('Medya için herkese açık bir adres yok (https alan adı veya medya URL tabanı gerekli).');
    }
}
