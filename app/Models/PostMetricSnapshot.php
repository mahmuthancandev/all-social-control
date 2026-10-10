<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMetricSnapshot extends Model
{
    protected $fillable = [
        'external_post_id', 'captured_on', 'views', 'reach', 'impressions', 'likes', 'comments', 'shares',
        'saves', 'watch_time_seconds', 'source', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['captured_on' => DateOnly::class, 'is_demo' => 'boolean'];
    }

    public function externalPost(): BelongsTo
    {
        return $this->belongsTo(ExternalPost::class);
    }
}
