<?php

namespace App\Services\Platforms\Contracts;

use App\Models\SocialAccount;
use Carbon\CarbonInterface;

interface ProvidesDailyAnalytics
{
    /**
     * Günlük metrikler: ['Y-m-d' => ['views' => ?int, 'likes' => ?int, ...]]
     *
     * @return array<string, array<string, int|null>>
     */
    public function fetchDailyAnalytics(SocialAccount $account, CarbonInterface $from, CarbonInterface $to): array;
}
