<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class ApiQuotaUsage extends Model
{
    protected $fillable = ['platform', 'usage_date', 'units_used'];

    protected function casts(): array
    {
        return ['usage_date' => DateOnly::class, 'units_used' => 'integer'];
    }
}
