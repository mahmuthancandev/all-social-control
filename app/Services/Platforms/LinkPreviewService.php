<?php

namespace App\Services\Platforms;

use App\Enums\Platform;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Herkese açık, resmi oEmbed uç noktalarıyla gönderi linkinden başlık/kapak bilgisi alır.
 * Kazıma (scraping) yapmaz; oEmbed sağlamayan platformlarda yalnızca link saklanır.
 */
class LinkPreviewService
{
    public function detectPlatform(string $url): ?Platform
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            Str::endsWith($host, ['youtube.com', 'youtu.be']) => Platform::YouTube,
            Str::endsWith($host, 'instagram.com') => Platform::Instagram,
            Str::endsWith($host, 'tiktok.com') => Platform::TikTok,
            Str::endsWith($host, ['x.com', 'twitter.com']) => Platform::X,
            default => null,
        };
    }

    /**
     * @return array{title: ?string, author: ?string, thumbnail_url: ?string, external_id: ?string}
     */
    public function preview(string $url): array
    {
        $platform = $this->detectPlatform($url);
        $endpoint = match ($platform) {
            Platform::YouTube => 'https://www.youtube.com/oembed',
            Platform::TikTok => 'https://www.tiktok.com/oembed',
            Platform::X => 'https://publish.twitter.com/oembed',
            default => null, // Instagram oEmbed uygulama tokenı gerektirir
        };

        $data = [];
        if ($endpoint) {
            try {
                $response = Http::timeout(10)->acceptJson()->get($endpoint, ['url' => $url, 'format' => 'json', 'omit_script' => 1]);
                if ($response->successful()) {
                    $data = $response->json() ?? [];
                }
            } catch (Throwable) {
                $data = [];
            }
        }

        $title = $data['title'] ?? null;
        if (! $title && isset($data['html']) && $platform === Platform::X) {
            $title = Str::limit(trim(strip_tags(Str::before($data['html'], '&mdash;'))), 200);
        }

        return [
            'title' => $title,
            'author' => $data['author_name'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'external_id' => $this->extractId($url, $platform),
        ];
    }

    public function extractId(string $url, ?Platform $platform): ?string
    {
        return match ($platform) {
            Platform::YouTube => preg_match('~(?:v=|youtu\.be/|shorts/)([A-Za-z0-9_-]{6,})~', $url, $m) ? $m[1] : null,
            Platform::TikTok => preg_match('~/video/(\d+)~', $url, $m) ? $m[1] : null,
            Platform::X => preg_match('~/status/(\d+)~', $url, $m) ? $m[1] : null,
            Platform::Instagram => preg_match('~/(?:p|reel|reels)/([A-Za-z0-9_-]+)~', $url, $m) ? $m[1] : null,
            default => null,
        } ?? substr(sha1($url), 0, 20);
    }
}
