<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OAuth tokenları. access_token/refresh_token/meta APP_KEY ile şifrelenir ve
 * hiçbir zaman frontend'e veya loglara gönderilmez.
 */
#[Hidden(['access_token', 'refresh_token', 'meta'])]
class AccountCredential extends Model
{
    protected $fillable = [
        'social_account_id', 'access_token', 'refresh_token', 'token_type', 'expires_at',
        'refresh_expires_at', 'scopes', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'meta' => 'encrypted:array',
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function isExpired(int $leewaySeconds = 120): bool
    {
        return $this->expires_at !== null && $this->expires_at->subSeconds($leewaySeconds)->isPast();
    }
}
