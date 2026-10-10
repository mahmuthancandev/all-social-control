<?php

namespace App\Enums;

enum ContentType: string
{
    case YouTubeVideo = 'youtube_video';
    case YouTubeShort = 'youtube_short';
    case InstagramPost = 'instagram_post';
    case InstagramReel = 'instagram_reel';
    case TikTokVideo = 'tiktok_video';
    case XPost = 'x_post';
    case XMedia = 'x_media';

    public function platform(): Platform
    {
        return match ($this) {
            self::YouTubeVideo, self::YouTubeShort => Platform::YouTube,
            self::InstagramPost, self::InstagramReel => Platform::Instagram,
            self::TikTokVideo => Platform::TikTok,
            self::XPost, self::XMedia => Platform::X,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::YouTubeVideo => 'YouTube videosu',
            self::YouTubeShort => 'YouTube Shorts',
            self::InstagramPost => 'Instagram gönderisi',
            self::InstagramReel => 'Instagram Reels',
            self::TikTokVideo => 'TikTok videosu',
            self::XPost => 'X gönderisi',
            self::XMedia => 'X medya gönderisi',
        };
    }

    /** Gerekli medya türü: video, image, any veya none. */
    public function mediaKind(): string
    {
        return match ($this) {
            self::YouTubeVideo, self::YouTubeShort, self::InstagramReel, self::TikTokVideo => 'video',
            self::InstagramPost => 'image',
            self::XMedia => 'any',
            self::XPost => 'none',
        };
    }

    public function needsVideo(): bool
    {
        return $this->mediaKind() === 'video';
    }

    /**
     * Platform gereksinimleri (resmi belgelere göre yaklaşık sınırlar; arayüzde uyarı olarak gösterilir).
     *
     * @return array<string, mixed>
     */
    public function requirements(): array
    {
        return match ($this) {
            self::YouTubeVideo => ['title_max' => 100, 'caption_max' => 5000, 'hashtags_max' => 15, 'aspect' => '16:9 önerilir', 'max_duration' => null, 'needs_title' => true],
            self::YouTubeShort => ['title_max' => 100, 'caption_max' => 5000, 'hashtags_max' => 15, 'aspect' => '9:16 dikey', 'max_duration' => 180, 'needs_title' => true],
            self::InstagramPost => ['title_max' => null, 'caption_max' => 2200, 'hashtags_max' => 30, 'aspect' => '1:1 veya 4:5', 'max_duration' => null, 'needs_title' => false],
            self::InstagramReel => ['title_max' => null, 'caption_max' => 2200, 'hashtags_max' => 30, 'aspect' => '9:16 dikey', 'max_duration' => 900, 'needs_title' => false],
            self::TikTokVideo => ['title_max' => null, 'caption_max' => 2200, 'hashtags_max' => null, 'aspect' => '9:16 dikey', 'max_duration' => 600, 'needs_title' => false],
            self::XPost => ['title_max' => null, 'caption_max' => 280, 'hashtags_max' => null, 'aspect' => null, 'max_duration' => null, 'needs_title' => false],
            self::XMedia => ['title_max' => null, 'caption_max' => 280, 'hashtags_max' => null, 'aspect' => '16:9 veya 1:1', 'max_duration' => 140, 'needs_title' => false],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'platform' => $t->platform()->value,
            'media_kind' => $t->mediaKind(),
            'requirements' => $t->requirements(),
        ], self::cases());
    }
}
