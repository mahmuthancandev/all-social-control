<?php

namespace App\Services\Platforms\Data;

final class PublishResult
{
    /**
     * @param  bool  $completed  false ise içerik platforma ulaştı ama yayın kullanıcı tarafından
     *                           tamamlanmalı (ör. TikTok gelen kutusu taslağı). Panel bunu "yayınlandı" saymaz.
     * @param  array<string, mixed>  $raw  Token içermeyen ham yanıt özeti
     */
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $url = null,
        public readonly ?string $note = null,
        public readonly bool $completed = true,
        public readonly array $raw = [],
    ) {}
}
