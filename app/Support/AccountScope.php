<?php

namespace App\Support;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Ortak filtre: proje, platform ve demo verisi dahil/haric.
 */
class AccountScope
{
    public static function query(Request $request): Builder
    {
        $includeDemo = $request->query('demo', '1') !== '0';

        return SocialAccount::query()
            ->when(! $includeDemo, fn ($q) => $q->where('is_demo', false))
            ->when($request->query('project'), fn ($q, $p) => $q->whereHas('projects', fn ($q) => $q->whereKey($p)))
            ->when($request->query('platform'), fn ($q, $p) => $q->where('platform', $p));
    }
}
