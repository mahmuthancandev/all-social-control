<?php

namespace App\Services\Platforms\Data;

use Carbon\CarbonImmutable;

final class TokenSet
{
    /**
     * @param  array<int, string>  $scopes
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?CarbonImmutable $expiresAt = null,
        public readonly array $scopes = [],
        public readonly ?string $tokenType = 'Bearer',
        public readonly ?CarbonImmutable $refreshExpiresAt = null,
        public readonly array $meta = [],
    ) {}
}
