<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $fillable = [
        'social_account_id', 'external_post_id', 'external_id', 'parent_external_id', 'author_name',
        'author_avatar_url', 'body', 'like_count', 'commented_at', 'read_at', 'reply_body', 'replied_at',
        'replied_by', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'commented_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function externalPost(): BelongsTo
    {
        return $this->belongsTo(ExternalPost::class);
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
