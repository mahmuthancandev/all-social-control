<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'uploaded_by', 'folder', 'disk', 'path', 'original_name', 'mime_type', 'extension',
        'type', 'size_bytes', 'width', 'height', 'duration_seconds', 'thumbnail_path', 'checksum', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'is_demo' => 'boolean',
            'size_bytes' => 'integer',
            'duration_seconds' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_media')->withTimestamps();
    }

    public function fileUrl(): string
    {
        return route('media.file', $this);
    }

    public function thumbnailUrl(): ?string
    {
        if ($this->thumbnail_path) {
            return route('media.thumbnail', $this);
        }

        return $this->type === 'image' ? $this->fileUrl() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'folder' => $this->folder,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'type' => $this->type,
            'size_bytes' => $this->size_bytes,
            'width' => $this->width,
            'height' => $this->height,
            'duration_seconds' => $this->duration_seconds,
            'url' => $this->fileUrl(),
            'download_url' => route('media.download', $this),
            'thumbnail_url' => $this->thumbnailUrl(),
            'is_demo' => $this->is_demo,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
