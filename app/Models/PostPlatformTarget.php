<?php

namespace App\Models;

use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\TargetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PostPlatformTarget extends Model
{
    protected $fillable = [
        'post_id', 'social_account_id', 'platform', 'content_type', 'title', 'caption', 'hashtags',
        'media_file_id', 'thumbnail_media_id', 'settings', 'publish_mode', 'status', 'scheduled_at',
        'published_at', 'external_id', 'external_url', 'error_message', 'attempts', 'idempotency_key', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'content_type' => ContentType::class,
            'status' => TargetStatus::class,
            'hashtags' => 'array',
            'settings' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PostPlatformTarget $target) {
            $target->idempotency_key ??= (string) Str::uuid();
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'thumbnail_media_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PublicationJob::class)->latest('id');
    }

    /**
     * Platforma gönderilecek tam metin (açıklama + hashtagler).
     */
    public function fullCaption(): string
    {
        $tags = collect($this->hashtags ?? [])
            ->filter()
            ->map(fn ($t) => '#'.ltrim((string) $t, '#'))
            ->implode(' ');

        return trim(trim((string) $this->caption)."\n\n".$tags);
    }
}
