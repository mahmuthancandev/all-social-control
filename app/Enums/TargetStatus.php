<?php

namespace App\Enums;

/**
 * Bir gönderinin tek bir hesaba/platforma yayın durumu.
 * "manual_required" ve "manually_published" otomatik yayından açıkça ayrıdır.
 */
enum TargetStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Queued = 'queued';
    case Publishing = 'publishing';
    case Published = 'published';
    case ManualRequired = 'manual_required';
    case ManuallyPublished = 'manually_published';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Scheduled => 'Planlandı',
            self::Queued => 'Kuyrukta',
            self::Publishing => 'Yayınlanıyor',
            self::Published => 'Yayınlandı (API)',
            self::ManualRequired => 'Manuel paylaşım bekliyor',
            self::ManuallyPublished => 'Manuel yayınlandı',
            self::Failed => 'Başarısız',
            self::Cancelled => 'İptal edildi',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Published, self::ManuallyPublished, self::Cancelled], true);
    }

    public function isPublished(): bool
    {
        return in_array($this, [self::Published, self::ManuallyPublished], true);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
