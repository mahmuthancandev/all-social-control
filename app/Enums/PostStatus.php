<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Published = 'published';
    case PartiallyPublished = 'partially_published';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Scheduled => 'Planlandı',
            self::InProgress => 'İşleniyor',
            self::Published => 'Yayınlandı',
            self::PartiallyPublished => 'Kısmen yayınlandı',
            self::Failed => 'Başarısız',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
