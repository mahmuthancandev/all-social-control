<?php

namespace App\Services\Platforms;

use App\Enums\Platform;
use App\Models\ApiQuotaUsage;
use App\Services\Platforms\Exceptions\QuotaExceededException;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Günlük API kota kullanımını yerel olarak sayar ve limit aşılmadan çağrıları durdurur.
 * YouTube kotası Pasifik saatine göre sıfırlanır; güvenli tarafta kalmak için o tarih kullanılır.
 */
class QuotaTracker
{
    public function dailyLimit(Platform $platform): ?int
    {
        return match ($platform) {
            Platform::YouTube => (int) Settings::get('youtube_daily_quota', 10000),
            default => null,
        };
    }

    public function used(Platform $platform): int
    {
        return (int) ApiQuotaUsage::where('platform', $platform->value)
            ->whereDate('usage_date', $this->today($platform))
            ->value('units_used');
    }

    public function remaining(Platform $platform): ?int
    {
        $limit = $this->dailyLimit($platform);

        return $limit === null ? null : max(0, $limit - $this->used($platform));
    }

    /**
     * @throws QuotaExceededException
     */
    public function consume(Platform $platform, int $units): void
    {
        $limit = $this->dailyLimit($platform);
        $today = $this->today($platform);

        DB::transaction(function () use ($platform, $units, $limit, $today) {
            $row = ApiQuotaUsage::firstOrCreate(
                ['platform' => $platform->value, 'usage_date' => $today],
                ['units_used' => 0],
            );

            if ($limit !== null && $row->units_used + $units > $limit) {
                throw new QuotaExceededException(sprintf(
                    '%s günlük API kotası doldu (%d/%d birim). Kota Pasifik saatiyle gece yarısı sıfırlanır.',
                    $platform->label(), $row->units_used, $limit,
                ));
            }

            $row->increment('units_used', $units);
        });
    }

    private function today(Platform $platform): string
    {
        return $platform === Platform::YouTube
            ? now('America/Los_Angeles')->toDateString()
            : now()->toDateString();
    }
}
