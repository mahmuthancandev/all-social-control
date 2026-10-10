<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    public const TYPES = ['game' => 'Oyun', 'app' => 'Uygulama'];

    protected $fillable = ['name', 'slug', 'type', 'category', 'description', 'logo_path', 'color', 'is_demo'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (blank($project->slug)) {
                $base = Str::slug($project->name) ?: 'proje';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $project->slug = $slug;
            }
        });
    }

    public function socialAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SocialAccount::class, 'project_social_accounts')->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'type_label' => self::TYPES[$this->type] ?? $this->type,
            'category' => $this->category,
            'color' => $this->color,
            'logo_url' => $this->logoUrl(),
            'is_demo' => $this->is_demo,
        ];
    }
}
