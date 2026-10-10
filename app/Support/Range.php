<?php

namespace App\Support;

use Illuminate\Http\Request;

class Range
{
    public const ALLOWED = [7, 30, 90];

    public static function days(Request $request, int $default = 30): int
    {
        $days = (int) $request->query('range', $default);

        return in_array($days, self::ALLOWED, true) ? $days : $default;
    }
}
