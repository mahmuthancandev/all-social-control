<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialAccount extends Model
{
    use HasFactory;

    public const CONNECTION_TYPES = [
        'oauth' => 'Resmi OAuth',
        'api_key' => 'Herkese açık API anahtarı',
        'manual' => 'Manuel',
        'demo' => 'Demo',
    ];

    protected $fillable = [
        'platform', 'handle', 'display_name', 'external_id', 'avatar_url', 'profile_url', 'status',
        'connection_type', 'followers_count', 'posts_count', 'granted_scopes', 'last_synced_at',
        'last_error', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => AccountStatus::class,
            'granted_scopes' => 'array',
            'last_synced_at' => 'datetime',
            'is_demo' => 'boolean',
            'followers_count' => 'integer',
            'posts_count' => 'integer',
        ];
    }

    public function credential(): HasOne
    {
        return $this->hasOne(AccountCredential::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_social_accounts')->withTimestamps();
    }

    public function metricSnapshots(): HasMany
    {
        return $this->hasMany(AccountMetricSnapshot::class);
    }

    public function externalPosts(): HasMany
    {
        return $this->hasMany(ExternalPost::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PostPlatformTarget::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    public function isOAuth(): bool
    {
        return $this->connection_type === 'oauth';
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'platform_label' => $this->platform->label(),
            'platform_color' => $this->platform->color(),
            'follower_label' => $this->platform->followerLabel(),
            'handle' => $this->handle,
            'display_name' => $this->display_name ?? $this->handle,
            'avatar_url' => $this->avatar_url,
            'profile_url' => $this->profile_url ?? $this->platform->profileUrl($this->handle),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'connection_type' => $this->connection_type,
            'connection_label' => self::CONNECTION_TYPES[$this->connection_type] ?? $this->connection_type,
            'followers_count' => $this->followers_count,
            'posts_count' => $this->posts_count,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'is_demo' => $this->is_demo,
        ];
    }
}
