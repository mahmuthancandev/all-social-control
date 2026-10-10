<?php

namespace App\Services\Platforms\Data;

use App\Enums\ContentType;
use Carbon\CarbonInterface;

final class RemotePost
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $title,
        public readonly ?string $url,
        public readonly ?string $thumbnailUrl,
        public readonly ?CarbonInterface $publishedAt,
        public readonly ?ContentType $contentType = null,
        public readonly ?int $views = null,
        public readonly ?int $likes = null,
        public readonly ?int $comments = null,
        public readonly ?int $shares = null,
    ) {}
}
