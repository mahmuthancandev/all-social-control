<?php

namespace App\Services\Platforms\Drivers;

use App\Enums\Capability;
use App\Enums\ContentType;
use App\Enums\Platform;
use App\Models\AccountCredential;
use App\Models\Comment;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;
use App\Services\Platforms\AbstractPlatformService;
use App\Services\Platforms\Contracts\ProvidesDailyAnalytics;
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Data\ProfileData;
use App\Services\Platforms\Data\PublishResult;
use App\Services\Platforms\Data\RemoteComment;
use App\Services\Platforms\Data\RemotePost;
use App\Services\Platforms\Data\TokenSet;
use App\Services\Platforms\Exceptions\NotConfiguredException;
use App\Services\Platforms\Exceptions\PlatformException;
use App\Services\Platforms\Exceptions\TokenExpiredException;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateInterval;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * YouTube Data API v3 + YouTube Analytics API v2 (resmi, ücretsiz; günlük kota ile sınırlı).
 *
 * Önemli: Google denetiminden (audit) geçmemiş API projelerinden yüklenen videolar
 * Google tarafından "özel" (private) görünürlüğe kısıtlanır.
 */
class YouTubeService extends AbstractPlatformService implements ProvidesDailyAnalytics
{
    private const API = 'https://www.googleapis.com/youtube/v3';

    private const SCOPE_READ = 'https://www.googleapis.com/auth/youtube.readonly';

    private const SCOPE_UPLOAD = 'https://www.googleapis.com/auth/youtube.upload';

    private const SCOPE_FORCE_SSL = 'https://www.googleapis.com/auth/youtube.force-ssl';

    private const SCOPE_ANALYTICS = 'https://www.googleapis.com/auth/yt-analytics.readonly';

    public function platform(): Platform
    {
        return Platform::YouTube;
    }

    public function isConfigured(): bool
    {
        return Settings::has('youtube_client_id') && Settings::has('youtube_client_secret');
    }

    public function setupNotes(): array
    {
        return [
            'Google Cloud Console\'da ücretsiz bir proje oluşturun, "YouTube Data API v3" ve "YouTube Analytics API"yi etkinleştirin.',
            'OAuth istemcisi türü "Web uygulaması" olmalı; yönlendirme adresini aşağıdan kopyalayın.',
            'Yalnızca herkese açık istatistikler için OAuth gerekmez: bir API anahtarı yeterlidir.',
            'Varsayılan kota günlük 10.000 birimdir. Video yükleme maliyeti Cloud Console kota sayfasından doğrulanmalıdır (varsayılan olarak temkinli 1600 birim sayılır).',
            'Denetimden geçmemiş projelerde API ile yüklenen videolar Google tarafından "özel" olarak kısıtlanır.',
        ];
    }

    public function usesPkce(): bool
    {
        return true;
    }

    public function oauthScopes(): array
    {
        return [self::SCOPE_READ, self::SCOPE_UPLOAD, self::SCOPE_FORCE_SSL, self::SCOPE_ANALYTICS];
    }

    public function authorizationUrl(string $state, ?string $codeChallenge, string $redirectUri): string
    {
        $this->ensureConfigured();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => Settings::get('youtube_client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->oauthScopes()),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier, string $redirectUri): TokenSet
    {
        $this->ensureConfigured();

        $json = $this->check($this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => Settings::get('youtube_client_id'),
            'client_secret' => Settings::get('youtube_client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
            'code_verifier' => $codeVerifier,
        ]), 'Google token alma')->json();

        return $this->tokenSet($json);
    }

    public function refreshToken(AccountCredential $credential): TokenSet
    {
        if (! $credential->refresh_token) {
            throw new TokenExpiredException('YouTube yenileme tokenı yok; hesabı yeniden bağlayın.');
        }

        $response = $this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => Settings::get('youtube_client_id'),
            'client_secret' => Settings::get('youtube_client_secret'),
            'refresh_token' => $credential->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->status() === 400 || $response->status() === 401) {
            throw new TokenExpiredException('YouTube erişimi iptal edilmiş veya süresi dolmuş; hesabı yeniden bağlayın.');
        }

        return $this->tokenSet($this->check($response, 'Google token yenileme')->json(), $credential->refresh_token);
    }

    protected function oauthCapabilities(SocialAccount $account, array $scopes): CapabilitySet
    {
        $has = fn (string ...$needed) => array_intersect($needed, $scopes) !== [];
        $read = $has(self::SCOPE_READ, self::SCOPE_FORCE_SSL);

        return (new CapabilitySet)
            ->set(Capability::ReadFollowers, $read, $read ? 'Gizli abone sayısı olan kanallarda boş kalır.' : 'youtube.readonly izni verilmedi.')
            ->set(Capability::ListPosts, $read, $read ? null : 'youtube.readonly izni verilmedi.')
            ->set(Capability::ReadAnalytics, $has(self::SCOPE_ANALYTICS), $has(self::SCOPE_ANALYTICS) ? null : 'yt-analytics.readonly izni verilmedi.')
            ->set(Capability::PublishVideo, $has(self::SCOPE_UPLOAD), $has(self::SCOPE_UPLOAD)
                ? 'Denetimden geçmemiş Google projelerinde video "özel" olarak yüklenir.'
                : 'youtube.upload izni verilmedi.')
            ->set(Capability::PublishImage, false, 'YouTube API görsel gönderi (Topluluk) yayınlamayı desteklemiyor.')
            ->set(Capability::PublishText, false, 'YouTube API metin gönderisi yayınlamayı desteklemiyor.')
            ->set(Capability::ReadComments, $read, $read ? null : 'youtube.readonly izni verilmedi.')
            ->set(Capability::ReplyComments, $has(self::SCOPE_FORCE_SSL), $has(self::SCOPE_FORCE_SSL) ? null : 'youtube.force-ssl izni verilmedi.');
    }

    protected function apiKeyCapabilities(SocialAccount $account): CapabilitySet
    {
        $ok = Settings::has('youtube_api_key');
        $reason = $ok ? null : 'Ayarlar sayfasında YouTube API anahtarı girilmemiş.';

        return (new CapabilitySet)
            ->set(Capability::ReadFollowers, $ok, $reason ?? 'Herkese açık abone sayısı (YouTube tarafından yuvarlanmış olabilir).')
            ->set(Capability::ListPosts, $ok, $reason)
            ->set(Capability::ReadComments, $ok, $reason ?? 'Herkese açık yorumlar.')
            ->set(Capability::ReadAnalytics, false, 'Detaylı analitik (Analytics API) yalnızca OAuth ile kanal sahibine açıktır.')
            ->set(Capability::PublishVideo, false, 'Video yüklemek için hesabı OAuth ile bağlayın.')
            ->set(Capability::PublishImage, false, 'YouTube API görsel gönderiyi desteklemiyor.')
            ->set(Capability::PublishText, false, 'YouTube API metin gönderisini desteklemiyor.')
            ->set(Capability::ReplyComments, false, 'Yorumlara cevap için hesabı OAuth ile bağlayın.');
    }

    public function fetchProfile(SocialAccount $account): ProfileData
    {
        $query = ['part' => 'snippet,statistics,contentDetails'];
        if ($account->connection_type === 'oauth') {
            $query['mine'] = 'true';
        } elseif ($account->external_id) {
            $query['id'] = $account->external_id;
        } else {
            $query['forHandle'] = '@'.ltrim($account->handle, '@');
        }

        $item = Arr::first($this->get($account, '/channels', $query, 1, 'Kanal bilgisi')['items'] ?? []);
        if (! $item) {
            throw new PlatformException('YouTube kanalı bulunamadı: '.$account->handle);
        }

        $stats = $item['statistics'] ?? [];
        $customUrl = data_get($item, 'snippet.customUrl');

        return new ProfileData(
            externalId: $item['id'],
            handle: $customUrl ? ltrim($customUrl, '@') : $account->handle,
            displayName: data_get($item, 'snippet.title'),
            avatarUrl: data_get($item, 'snippet.thumbnails.default.url'),
            profileUrl: 'https://www.youtube.com/channel/'.$item['id'],
            followers: ($stats['hiddenSubscriberCount'] ?? false) ? null : $this->int($stats['subscriberCount'] ?? null),
            postsCount: $this->int($stats['videoCount'] ?? null),
            totalViews: $this->int($stats['viewCount'] ?? null),
        );
    }

    public function fetchRecentPosts(SocialAccount $account, int $limit = 25): array
    {
        $channel = Arr::first($this->get($account, '/channels', array_filter([
            'part' => 'contentDetails',
            'mine' => $account->connection_type === 'oauth' ? 'true' : null,
            'id' => $account->connection_type === 'oauth' ? null : $account->external_id,
        ]), 1, 'Yükleme listesi')['items'] ?? []);

        $uploads = data_get($channel, 'contentDetails.relatedPlaylists.uploads');
        if (! $uploads) {
            return [];
        }

        $items = $this->get($account, '/playlistItems', [
            'part' => 'contentDetails',
            'playlistId' => $uploads,
            'maxResults' => min(50, $limit),
        ], 1, 'Video listesi')['items'] ?? [];

        $ids = array_filter(array_map(fn ($i) => data_get($i, 'contentDetails.videoId'), $items));
        if ($ids === []) {
            return [];
        }

        $videos = $this->get($account, '/videos', [
            'part' => 'snippet,statistics,contentDetails',
            'id' => implode(',', $ids),
        ], 1, 'Video istatistikleri')['items'] ?? [];

        return array_map(function (array $v) {
            $duration = $this->durationSeconds(data_get($v, 'contentDetails.duration'));

            return new RemotePost(
                externalId: $v['id'],
                title: data_get($v, 'snippet.title'),
                url: 'https://www.youtube.com/watch?v='.$v['id'],
                thumbnailUrl: data_get($v, 'snippet.thumbnails.medium.url'),
                publishedAt: ($p = data_get($v, 'snippet.publishedAt')) ? CarbonImmutable::parse($p)->setTimezone(config('app.timezone')) : null,
                contentType: $duration !== null && $duration <= 180 ? ContentType::YouTubeShort : ContentType::YouTubeVideo,
                views: $this->int(data_get($v, 'statistics.viewCount')),
                likes: $this->int(data_get($v, 'statistics.likeCount')),
                comments: $this->int(data_get($v, 'statistics.commentCount')),
                shares: null, // Data API paylaşım sayısı sunmaz
            );
        }, $videos);
    }

    public function fetchComments(SocialAccount $account, string $postExternalId, int $limit = 50): array
    {
        $items = $this->get($account, '/commentThreads', [
            'part' => 'snippet',
            'videoId' => $postExternalId,
            'maxResults' => min(100, $limit),
            'order' => 'time',
            'textFormat' => 'plainText',
        ], 1, 'Yorumlar')['items'] ?? [];

        return array_map(function (array $thread) use ($postExternalId) {
            $top = data_get($thread, 'snippet.topLevelComment');

            return new RemoteComment(
                externalId: $top['id'],
                postExternalId: $postExternalId,
                body: (string) data_get($top, 'snippet.textDisplay', ''),
                authorName: data_get($top, 'snippet.authorDisplayName'),
                authorAvatarUrl: data_get($top, 'snippet.authorProfileImageUrl'),
                commentedAt: ($d = data_get($top, 'snippet.publishedAt')) ? CarbonImmutable::parse($d) : null,
                likeCount: $this->int(data_get($top, 'snippet.likeCount')),
            );
        }, $items);
    }

    public function replyToComment(SocialAccount $account, Comment $comment, string $text): string
    {
        $this->quota->consume(Platform::YouTube, 50);

        $json = $this->check(
            $this->http()->withToken($this->accessToken($account))->post(self::API.'/comments?part=snippet', [
                'snippet' => ['parentId' => $comment->external_id, 'textOriginal' => $text],
            ]),
            'Yorum cevabı',
        )->json();

        return (string) $json['id'];
    }

    public function publish(PostPlatformTarget $target): PublishResult
    {
        $account = $target->socialAccount;
        $path = $this->mediaPath($target);
        $media = $target->mediaFile;
        $settings = $target->settings ?? [];

        $description = $target->fullCaption();
        $title = (string) ($target->title ?: $target->post->title ?: 'Yeni video');
        if ($target->content_type === ContentType::YouTubeShort && ! str_contains(mb_strtolower($title.' '.$description), '#shorts')) {
            $description = trim($description."\n\n#Shorts");
        }

        $this->quota->consume(Platform::YouTube, (int) Settings::get('youtube_upload_cost', 1600));

        $token = $this->accessToken($account);
        $init = $this->check(
            $this->http()->withToken($token)
                ->withHeaders([
                    'X-Upload-Content-Length' => (string) filesize($path),
                    'X-Upload-Content-Type' => $media->mime_type,
                ])
                ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                    'snippet' => [
                        'title' => mb_substr($title, 0, 100),
                        'description' => mb_substr($description, 0, 5000),
                        'tags' => array_values(array_slice(array_map(fn ($t) => ltrim((string) $t, '#'), $target->hashtags ?? []), 0, 15)),
                        'categoryId' => (string) ($settings['category_id'] ?? '20'),
                    ],
                    'status' => [
                        'privacyStatus' => in_array($settings['privacy'] ?? null, ['public', 'unlisted', 'private'], true) ? $settings['privacy'] : 'private',
                        'selfDeclaredMadeForKids' => (bool) ($settings['made_for_kids'] ?? false),
                    ],
                ]),
            'YouTube yükleme başlatma',
        );

        $uploadUrl = $init->header('Location');
        if (! $uploadUrl) {
            throw new PlatformException('YouTube yükleme adresi alınamadı.');
        }

        $stream = Utils::streamFor(fopen($path, 'rb'));
        $video = $this->check(
            $this->http()->timeout(3600)->withToken($token)
                ->withBody($stream, $media->mime_type)
                ->put($uploadUrl),
            'YouTube video yükleme',
        )->json();

        $videoId = (string) $video['id'];
        $note = null;

        if ($target->thumbnail) {
            try {
                $this->quota->consume(Platform::YouTube, 50);
                $thumbPath = Storage::disk($target->thumbnail->disk)->path($target->thumbnail->path);
                $this->check(
                    $this->http()->timeout(120)->withToken($token)
                        ->withBody(Utils::streamFor(fopen($thumbPath, 'rb')), $target->thumbnail->mime_type)
                        ->post('https://www.googleapis.com/upload/youtube/v3/thumbnails/set?videoId='.$videoId),
                    'Kapak yükleme',
                );
            } catch (Throwable $e) {
                $note = 'Video yüklendi ancak kapak ayarlanamadı (özel kapak için kanalın doğrulanmış olması gerekir): '.$e->getMessage();
            }
        }

        $privacy = data_get($video, 'status.privacyStatus');
        if ($privacy === 'private' && ($settings['privacy'] ?? 'private') !== 'private') {
            $note = trim(($note ? $note.' ' : '').'Video "özel" olarak yüklendi: Google denetiminden geçmemiş projelerde API yüklemeleri özel kalır.');
        }

        return new PublishResult(
            externalId: $videoId,
            url: 'https://www.youtube.com/watch?v='.$videoId,
            note: $note,
            raw: ['privacyStatus' => $privacy, 'uploadStatus' => data_get($video, 'status.uploadStatus')],
        );
    }

    public function fetchDailyAnalytics(SocialAccount $account, CarbonInterface $from, CarbonInterface $to): array
    {
        $json = $this->check(
            $this->http()->withToken($this->accessToken($account))->get('https://youtubeanalytics.googleapis.com/v2/reports', [
                'ids' => 'channel==MINE',
                'startDate' => $from->toDateString(),
                'endDate' => $to->toDateString(),
                'metrics' => 'views,likes,comments,shares,subscribersGained,subscribersLost',
                'dimensions' => 'day',
                'sort' => 'day',
            ]),
            'YouTube Analytics',
        )->json();

        $headers = array_map(fn ($h) => $h['name'], $json['columnHeaders'] ?? []);
        $out = [];
        foreach ($json['rows'] ?? [] as $row) {
            $r = array_combine($headers, $row);
            $out[$r['day']] = [
                'views' => $this->int($r['views'] ?? null),
                'likes' => $this->int($r['likes'] ?? null),
                'comments' => $this->int($r['comments'] ?? null),
                'shares' => $this->int($r['shares'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(SocialAccount $account, string $path, array $query, int $cost, string $context): array
    {
        $this->quota->consume(Platform::YouTube, $cost);

        $request = $this->http();
        if ($account->connection_type === 'oauth') {
            $request = $request->withToken($this->accessToken($account));
        } else {
            $key = Settings::get('youtube_api_key');
            if (! $key) {
                throw new NotConfiguredException('YouTube API anahtarı ayarlanmamış.');
            }
            $query['key'] = $key;
        }

        return $this->check($request->get(self::API.$path, $query), $context)->json() ?? [];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new NotConfiguredException('YouTube OAuth bilgileri Ayarlar sayfasında girilmemiş.');
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function tokenSet(array $json, ?string $fallbackRefresh = null): TokenSet
    {
        return new TokenSet(
            accessToken: $json['access_token'],
            refreshToken: $json['refresh_token'] ?? $fallbackRefresh,
            expiresAt: isset($json['expires_in']) ? CarbonImmutable::now()->addSeconds((int) $json['expires_in']) : null,
            scopes: isset($json['scope']) ? explode(' ', $json['scope']) : [],
            tokenType: $json['token_type'] ?? 'Bearer',
        );
    }

    private function int(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function durationSeconds(?string $iso): ?int
    {
        if (! $iso) {
            return null;
        }
        try {
            $i = new DateInterval($iso);

            return $i->d * 86400 + $i->h * 3600 + $i->i * 60 + $i->s;
        } catch (Throwable) {
            return null;
        }
    }
}
