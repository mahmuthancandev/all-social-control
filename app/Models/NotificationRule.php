<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRule extends Model
{
    public const TYPES = [
        'views_threshold' => 'Video belirli izlenmeye ulaştı',
        'followers_threshold' => 'Takipçi/abone hedefi geçildi',
        'schedule_reminder' => 'Zamanlanmış paylaşım yaklaşıyor',
    ];

    protected $fillable = ['type', 'social_account_id', 'threshold', 'minutes_before', 'is_active', 'last_triggered_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_triggered_at' => 'datetime', 'threshold' => 'integer', 'minutes_before' => 'integer'];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
