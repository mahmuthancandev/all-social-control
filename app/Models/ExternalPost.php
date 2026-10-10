<?php

namespace App\Models;

use App\Enums\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalPost extends Model
{
    protected $fillable = [
        'social_account_id', 'post_platform_target_id', 'external_id', 'content_type', 'title', 'url',
        'thumbnail_url', 'published_at', 'views', 'likes', 'comments', 'shares', 'source', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => ContentType::class,
            'published_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(PostPlatformTarget::class, 'post_platform_target_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PostMetricSnapshot::class);
    }

    public function postComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Etkileşim oranı = (beğeni + yorum + paylaşım) / görüntülenme. Veri yoksa null.
     */
    public function engagementRate(): ?float
    {
        if (! $this->views) {
            return null;
        }
        $parts = array_filter([$this->likes, $this->comments, $this->shares], fn ($v) => $v !== null);
        if ($parts === []) {
            return null;
        }

        return round(array_sum($parts) / $this->views * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'title' => $this->title,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'content_type' => $this->content_type?->value,
            'content_type_label' => $this->content_type?->label(),
            'published_at' => $this->published_at?->toIso8601String(),
            'views' => $this->views,
            'likes' => $this->likes,
            'comments' => $this->comments,
            'shares' => $this->shares,
            'engagement_rate' => $this->engagementRate(),
            'is_demo' => $this->is_demo,
            'account' => $this->relationLoaded('socialAccount') ? $this->socialAccount->toSummary() : null,
        ];
    }
}
