<?php

namespace App\Services\Platforms;

use App\Enums\Platform;
use App\Support\Settings;

class OAuthRedirect
{
    public static function uri(Platform $platform): string
    {
        $base = Settings::get('oauth_redirect_base_url');
        $path = '/oauth/'.$platform->value.'/callback';

        return $base ? rtrim((string) $base, '/').$path : url($path);
    }
}
