<?php

namespace App\Services\Platforms\Data;

use App\Enums\Capability;
use App\Enums\ContentType;

/**
 * Bir hesabın o anki bağlantı izinlerine göre yapabildikleri ve yapamıyorsa nedeni.
 */
final class CapabilitySet
{
    /** @var array<string, array{enabled: bool, reason: ?string}> */
    private array $items = [];

    public function set(Capability $capability, bool $enabled, ?string $reason = null): self
    {
        $this->items[$capability->value] = ['enabled' => $enabled, 'reason' => $reason];

        return $this;
    }

    public function can(Capability $capability): bool
    {
        return $this->items[$capability->value]['enabled'] ?? false;
    }

    public function reason(Capability $capability): ?string
    {
        return $this->items[$capability->value]['reason'] ?? null;
    }

    public function canPublish(ContentType $type): bool
    {
        return match ($type->mediaKind()) {
            'video' => $this->can(Capability::PublishVideo),
            'image' => $this->can(Capability::PublishImage),
            'none' => $this->can(Capability::PublishText),
            default => $this->can(Capability::PublishVideo) || $this->can(Capability::PublishImage),
        };
    }

    public function publishReason(ContentType $type): ?string
    {
        return match ($type->mediaKind()) {
            'image' => $this->reason(Capability::PublishImage),
            'none' => $this->reason(Capability::PublishText),
            default => $this->reason(Capability::PublishVideo),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, enabled: bool, reason: ?string}>
     */
    public function toArray(): array
    {
        $out = [];
        foreach (Capability::cases() as $capability) {
            $item = $this->items[$capability->value] ?? ['enabled' => false, 'reason' => 'Bu platformda desteklenmiyor.'];
            $out[] = ['key' => $capability->value, 'label' => $capability->label()] + $item;
        }

        return $out;
    }

    public static function none(string $reason): self
    {
        $set = new self;
        foreach (Capability::cases() as $capability) {
            $set->set($capability, false, $reason);
        }

        return $set;
    }
}
