<?php

namespace App\Enums;

enum Platform: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case X = 'x';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::X => 'X (Twitter)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            // Koyu yüzeyde renk körlüğü doğrulamasından geçen sıra (bkz. README > Grafik renkleri).
            self::YouTube => '#d95926',
            self::Instagram => '#9085e9',
            self::TikTok => '#199e70',
            self::X => '#3987e5',
        };
    }

    /**
     * Platformun resmi, ücretsiz manuel paylaşım sayfası.
     */
    public function manualShareUrl(?string $text = null): string
    {
        return match ($this) {
            self::YouTube => 'https://studio.youtube.com/',
            self::Instagram => 'https://www.instagram.com/',
            self::TikTok => 'https://www.tiktok.com/tiktokstudio/upload',
            self::X => 'https://x.com/intent/post'.($text !== null ? '?text='.rawurlencode($text) : ''),
        };
    }

    public function profileUrl(string $handle): string
    {
        $handle = ltrim($handle, '@');

        return match ($this) {
            self::YouTube => 'https://www.youtube.com/@'.$handle,
            self::Instagram => 'https://www.instagram.com/'.$handle,
            self::TikTok => 'https://www.tiktok.com/@'.$handle,
            self::X => 'https://x.com/'.$handle,
        };
    }

    /**
     * Platformun takipçi metriği için kullandığı terim (metrikler karıştırılmaz).
     */
    public function followerLabel(): string
    {
        return $this === self::YouTube ? 'Abone' : 'Takipçi';
    }

    /**
     * @return array<int, ContentType>
     */
    public function contentTypes(): array
    {
        return array_values(array_filter(ContentType::cases(), fn (ContentType $t) => $t->platform() === $this));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_map(fn (self $p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'color' => $p->color(),
            'follower_label' => $p->followerLabel(),
        ], self::cases());
    }
}
