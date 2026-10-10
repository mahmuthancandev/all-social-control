<?php

namespace App\Services\Platforms\Data;

/**
 * Hesap profili. İzni olmayan sayılar null'dır (0 değildir).
 */
final class ProfileData
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $handle,
        public readonly ?string $displayName = null,
        public readonly ?string $avatarUrl = null,
        public readonly ?string $profileUrl = null,
        public readonly ?int $followers = null,
        public readonly ?int $following = null,
        public readonly ?int $postsCount = null,
        public readonly ?int $totalViews = null,
        public readonly ?int $totalLikes = null,
    ) {}
}
