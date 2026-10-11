<?php

namespace App\Services\Platforms;

use App\Enums\Platform;
use App\Support\Settings;

class OAuthRedirect
{
    /**
     * Instagram ve TikTok https yönlendirme ister; bu platformlar için (ayarlıysa) https köprü adresi
     * kullanılır. YouTube ve X yerel http://127.0.0.1 adresini kabul ettiği için doğrudan panele döner.
     */
    public static function uri(Platform $platform): string
    {
        $path = '/oauth/'.$platform->value.'/callback';
        $base = in_array($platform, [Platform::Instagram, Platform::TikTok], true)
            ? Settings::get('oauth_redirect_base_url')
            : null;

        return $base ? rtrim((string) $base, '/').$path : url($path);
    }
}
