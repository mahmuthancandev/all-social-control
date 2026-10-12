<?php

namespace App\Support;

/**
 * Panelin nerede çalıştığına dair bilgiler: yerel Windows kurulumu mu, yoksa 7/24 açık bir sunucu mu.
 */
class Deployment
{
    /**
     * Sunucu (VPS) modu: zamanlayıcı ve kuyruk sürekli çalışır.
     */
    public static function serverMode(): bool
    {
        return (bool) config('socialhub.server_mode');
    }

    /**
     * APP_URL internetten erişilebilir bir https adresi mi? (localhost / 127.0.0.1 / .local / .test değil)
     */
    public static function hasPublicHttpsUrl(): bool
    {
        $url = (string) config('app.url');
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return false;
        }

        foreach (['.localhost', '.local', '.test', '.internal'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        return true;
    }
}
