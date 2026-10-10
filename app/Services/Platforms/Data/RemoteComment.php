<?php

namespace App\Services\Platforms\Data;

use Carbon\CarbonInterface;

final class RemoteComment
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $postExternalId,
        public readonly string $body,
        public readonly ?string $authorName = null,
        public readonly ?string $authorAvatarUrl = null,
        public readonly ?CarbonInterface $commentedAt = null,
        public readonly ?int $likeCount = null,
        public readonly ?string $parentExternalId = null,
    ) {}
}
