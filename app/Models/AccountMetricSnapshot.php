<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMetricSnapshot extends Model
{
    public const METRICS = ['followers', 'following', 'posts_count', 'views', 'reach', 'impressions', 'likes', 'comments', 'shares'];

    protected $fillable = [
        'social_account_id', 'captured_on', 'followers', 'following', 'posts_count', 'views', 'reach',
        'impressions', 'likes', 'comments', 'shares', 'source', 'is_demo',
    ];

    protected function casts(): array
    {
        return ['captured_on' => 'date', 'is_demo' => 'boolean'];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
