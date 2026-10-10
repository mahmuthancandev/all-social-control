<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationJob extends Model
{
    protected $fillable = [
        'post_platform_target_id', 'triggered_by', 'mode', 'status', 'attempt', 'started_at', 'finished_at', 'message', 'response',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(PostPlatformTarget::class, 'post_platform_target_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
