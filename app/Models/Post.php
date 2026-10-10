<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\TargetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'created_by', 'title', 'body', 'hashtags', 'status', 'scheduled_at', 'is_demo'];

    protected function casts(): array
    {
        return [
            'hashtags' => 'array',
            'status' => PostStatus::class,
            'scheduled_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PostPlatformTarget::class);
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(MediaFile::class, 'post_media')->withPivot('position')->withTimestamps()->orderByPivot('position');
    }

    /**
     * Genel gönderi durumunu hedeflerin durumundan hesaplar.
     */
    public function refreshStatus(): void
    {
        $statuses = $this->targets()->pluck('status')->map(fn ($s) => $s instanceof TargetStatus ? $s : TargetStatus::from($s));

        if ($statuses->isEmpty() || $statuses->every(fn ($s) => $s === TargetStatus::Draft)) {
            $status = PostStatus::Draft;
        } elseif ($statuses->every(fn ($s) => $s->isPublished() || $s === TargetStatus::Cancelled)) {
            $status = PostStatus::Published;
        } elseif ($statuses->contains(fn ($s) => in_array($s, [TargetStatus::Queued, TargetStatus::Publishing], true))) {
            $status = PostStatus::InProgress;
        } elseif ($statuses->contains(fn ($s) => $s->isPublished())) {
            $status = PostStatus::PartiallyPublished;
        } elseif ($statuses->contains(TargetStatus::Failed)) {
            $status = PostStatus::Failed;
        } elseif ($statuses->contains(fn ($s) => in_array($s, [TargetStatus::Scheduled, TargetStatus::ManualRequired], true))) {
            $status = PostStatus::Scheduled;
        } else {
            $status = PostStatus::Draft;
        }

        $this->forceFill(['status' => $status])->save();
    }
}
