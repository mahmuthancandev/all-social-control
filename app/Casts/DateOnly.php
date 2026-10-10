<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Yalnızca tarih (Y-m-d) saklar. Böylece "2026-10-10" ve "2026-10-10 00:00:00" karışmaz,
 * günlük ölçümlerde tekrar eden kayıt oluşmaz (SQLite ve MariaDB'de aynı davranır).
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse(substr((string) $value, 0, 10), config('app.timezone'))->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }
}
