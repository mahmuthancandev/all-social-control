<?php

namespace App\Services\Platforms\Drivers;

use App\Enums\Capability;
use App\Enums\ContentType;
use App\Enums\Platform;
use App\Models\AccountCredential;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;
use App\Services\Platforms\AbstractPlatformService;
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Data\ProfileData;
use App\Services\Platforms\Data\PublishResult;
use App\Services\Platforms\Data\RemotePost;
use App\Services\Platforms\Data\TokenSet;
use App\Services\Platforms\Exceptions\NotConfiguredException;
use App\Services\Platforms\Exceptions\PlatformException;
use App\Services\Platforms\Exceptions\TokenExpiredException;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * TikTok Login Kit + Display API + Content Posting API (resmi, ücretsiz; uygulama onayı gerekir).
 *
 * - "inbox" modu (video.upload): video kullanıcının TikTok gelen kutusuna TASLAK olarak gönderilir;
 *   yayını kullanıcı TikTok uygulamasında tamamlar. Panel bunu "yayınlandı" saymaz.
 * - "direct" modu (video.publish): Denetimden (audit) geçmemiş uygulamaların gönderileri TikTok
 *   tarafından yalnızca "SELF_ONLY" (özel) görünürlükle kabul edilir.
 * - Yorum okuma/cevaplama için herkese açık resmi API yoktur.
 */
class TikTokService extends AbstractPlatformService
{
    private const API = 'https://open.tiktokapis.com';

    private const CHUNK = 10 * 1024 * 1024;

    public function platform(): Platform
    {
        return Platform::TikTok;
    }

    public function isConfigured(): bool
    {
        return Settings::has('tiktok_client_key') && Settings::has('tiktok_client_secret');
    }

    public function setupNotes(): array
    {
        return [
            'developers.tiktok.com üzerinde ücretsiz bir uygulama oluşturun; Login Kit, Display API ve Content Posting API ürünlerini ekleyin.',
            'Kapsamlar (scope) için TikTok uygulama incelemesi gerekir; onaylanmayan kapsamlar bağlantıda verilmez ve ilgili özellik pasif görünür.',
            'Denetimden geçmemiş uygulamalarda doğrudan yayınlanan videolar yalnızca "özel" (SELF_ONLY) görünür.',
            'Varsayılan "gelen kutusu" modunda video TikTok uygulamanıza taslak olarak düşer; paylaşımı uygulamadan siz tamamlarsınız.',
            'TikTok yönlendirme adresi olarak https isteyebilir. Gerekirse "OAuth yönlendirme adresi tabanı" ayarını kullanın.',
        ];
    }

    public function oauthScopes(): array
    {
        $scopes = ['user.info.basic', 'user.info.profile', 'user.info.stats', 'video.list', 'video.upload'];
        if (Settings::get('tiktok_publish_mode') === 'direct') {
            $scopes[] = 'video.publish';
        }

        return $scopes;
    }

    public function authorizationUrl(string $state, ?string $codeChallenge, string $redirectUri): string
    {
        $this->ensureConfigured();

        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => Settings::get('tiktok_client_key'),
            'scope' => implode(',', $this->oauthScopes()),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier, string $redirectUri): TokenSet
    {
        $this->ensureConfigured();

        return $this->tokenSet($this->check($this->http()->asForm()->post(self::API.'/v2/oauth/token/', [
            'client_key' => Settings::get('tiktok_client_key'),
            'client_secret' => Settings::get('tiktok_client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]), 'TikTok token alma')->json());
    }

    public function refreshToken(AccountCredential $credential): TokenSet
    {
        if (! $credential->refresh_token || ($credential->refresh_expires_at && $credential->refresh_expires_at->isPast())) {
            throw new TokenExpiredException('TikTok bağlantısının süresi doldu; hesabı yeniden bağlayın.');
        }

        return $this->tokenSet($this->check($this->http()->asForm()->post(self::API.'/v2/oauth/token/', [
            'client_key' => Settings::get('tiktok_client_key'),
            'client_secret' => Settings::get('tiktok_client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $credential->refresh_token,
        ]), 'TikTok token yenileme')->json());
    }

    protected function oauthCapabilities(SocialAccount $account, array $scopes): CapabilitySet
    {
        $has = fn (string $s) => in_array($s, $scopes, true);
        $direct = Settings::get('tiktok_publish_mode') === 'direct';
        $canPublish = $direct ? $has('video.publish') : $has('video.upload');
        $publishReason = $canPublish
            ? ($direct
                ? 'Doğrudan yayın: denetimden geçmemiş uygulamalarda video yalnızca "özel" görünür.'
                : 'Video TikTok gelen kutusuna taslak olarak gönderilir; yayını uygulamada siz tamamlarsınız.')
            : ($direct ? 'video.publish izni verilmedi (uygulama incelemesi gerekir).' : 'video.upload izni verilmedi (uygulama incelemesi gerekir).');

        return (new CapabilitySet)
            ->set(Capability::ReadFollowers, $has('user.info.stats'), $has('user.info.stats') ? null : 'user.info.stats izni verilmedi.')
            ->set(Capability::ListPosts, $has('video.list'), $has('video.list') ? null : 'video.list izni verilmedi.')
            ->set(Capability::ReadAnalytics, $has('video.list'), $has('video.list') ? 'Yalnızca video bazlı izlenme/beğeni/yorum/paylaşım sayıları; erişim verisi yok.' : 'video.list izni verilmedi.')
            ->set(Capability::PublishVideo, $canPublish, $publishReason)
            ->set(Capability::PublishImage, false, 'Fotoğraf gönderisi bu sürümde Paylaşım Asistanı ile yapılır.')
            ->set(Capability::PublishText, false, 'TikTok metin gönderisini desteklemez.')
            ->set(Capability::ReadComments, false, 'TikTok\'un yorumlar için herkese açık resmi API\'si yok; yorumları TikTok uygulamasında yönetin.')
            ->set(Capability::ReplyComments, false, 'TikTok\'un yorum cevaplama için herkese açık resmi API\'si yok.');
    }

    public function fetchProfile(SocialAccount $account): ProfileData
    {
        $scopes = $account->granted_scopes ?? [];
        $fields = ['open_id', 'avatar_url', 'display_name'];
        if (in_array('user.info.profile', $scopes, true)) {
            array_push($fields, 'username', 'profile_deep_link');
        }
        if (in_array('user.info.stats', $scopes, true)) {
            array_push($fields, 'follower_count', 'following_count', 'likes_count', 'video_count');
        }

        $user = data_get($this->check(
            $this->http()->withToken($this->accessToken($account))->get(self::API.'/v2/user/info/', ['fields' => implode(',', $fields)]),
            'TikTok profil',
        )->json(), 'data.user', []);

        $handle = $user['username'] ?? $account->handle;

        return new ProfileData(
            externalId: (string) $user['open_id'],
            handle: $handle,
            displayName: $user['display_name'] ?? null,
            avatarUrl: $user['avatar_url'] ?? null,
            profileUrl: $user['profile_deep_link'] ?? 'https://www.tiktok.com/@'.$handle,
            followers: isset($user['follower_count']) ? (int) $user['follower_count'] : null,
            following: isset($user['following_count']) ? (int) $user['following_count'] : null,
            postsCount: isset($user['video_count']) ? (int) $user['video_count'] : null,
            totalLikes: isset($user['likes_count']) ? (int) $user['likes_count'] : null,
        );
    }

    public function fetchRecentPosts(SocialAccount $account, int $limit = 25): array
    {
        $json = $this->check(
            $this->http()->withToken($this->accessToken($account))
                ->post(self::API.'/v2/video/list/?fields=id,title,video_description,cover_image_url,share_url,create_time,view_count,like_count,comment_count,share_count', [
                    'max_count' => min(20, $limit),
                ]),
            'TikTok video listesi',
        )->json();

        return array_map(fn (array $v) => new RemotePost(
            externalId: (string) $v['id'],
            title: $v['title'] ?? (isset($v['video_description']) ? mb_substr($v['video_description'], 0, 200) : null),
            url: $v['share_url'] ?? null,
            thumbnailUrl: $v['cover_image_url'] ?? null,
            publishedAt: isset($v['create_time']) ? CarbonImmutable::createFromTimestamp((int) $v['create_time'], config('app.timezone')) : null,
            contentType: ContentType::TikTokVideo,
            views: isset($v['view_count']) ? (int) $v['view_count'] : null,
            likes: isset($v['like_count']) ? (int) $v['like_count'] : null,
            comments: isset($v['comment_count']) ? (int) $v['comment_count'] : null,
            shares: isset($v['share_count']) ? (int) $v['share_count'] : null,
        ), data_get($json, 'data.videos', []));
    }

    public function publish(PostPlatformTarget $target): PublishResult
    {
        $account = $target->socialAccount;
        $path = $this->mediaPath($target);
        $size = filesize($path);
        $token = $this->accessToken($account);
        $direct = Settings::get('tiktok_publish_mode') === 'direct';

        // TikTok kuralı: parça 5-64 MB, son parça kalan veriyi taşır; 5 MB altı tek parça.
        $chunkSize = $size < 5 * 1024 * 1024 ? $size : self::CHUNK;
        $chunkCount = max(1, intdiv($size, $chunkSize));

        $sourceInfo = [
            'source' => 'FILE_UPLOAD',
            'video_size' => $size,
            'chunk_size' => $chunkSize,
            'total_chunk_count' => $chunkCount,
        ];

        if ($direct) {
            $creator = data_get($this->check(
                $this->http()->withToken($token)->post(self::API.'/v2/post/publish/creator_info/query/'),
                'TikTok içerik üretici bilgisi',
            )->json(), 'data', []);

            $options = $creator['privacy_level_options'] ?? ['SELF_ONLY'];
            $wanted = $target->settings['privacy'] ?? 'SELF_ONLY';
            $privacy = in_array($wanted, $options, true) ? $wanted : (in_array('SELF_ONLY', $options, true) ? 'SELF_ONLY' : $options[0]);

            $init = $this->check($this->http()->withToken($token)->post(self::API.'/v2/post/publish/video/init/', [
                'post_info' => [
                    'title' => mb_substr($target->fullCaption(), 0, 2200),
                    'privacy_level' => $privacy,
                    'disable_comment' => (bool) ($target->settings['disable_comment'] ?? false),
                    'disable_duet' => (bool) ($target->settings['disable_duet'] ?? false),
                    'disable_stitch' => (bool) ($target->settings['disable_stitch'] ?? false),
                ],
                'source_info' => $sourceInfo,
            ]), 'TikTok doğrudan yayın başlatma')->json();
        } else {
            $init = $this->check($this->http()->withToken($token)->post(self::API.'/v2/post/publish/inbox/video/init/', [
                'source_info' => $sourceInfo,
            ]), 'TikTok taslak yükleme başlatma')->json();
        }

        $publishId = (string) data_get($init, 'data.publish_id');
        $uploadUrl = data_get($init, 'data.upload_url');
        if (! $publishId || ! $uploadUrl) {
            throw new PlatformException('TikTok yükleme adresi alınamadı.');
        }

        $handle = fopen($path, 'rb');
        try {
            for ($i = 0; $i < $chunkCount; $i++) {
                $start = $i * $chunkSize;
                $length = $i === $chunkCount - 1 ? $size - $start : $chunkSize;
                fseek($handle, $start);
                $bytes = fread($handle, $length);

                $this->check(
                    $this->http()->timeout(600)
                        ->withHeaders([
                            'Content-Range' => sprintf('bytes %d-%d/%d', $start, $start + $length - 1, $size),
                        ])
                        ->withBody($bytes, $target->mediaFile->mime_type)
                        ->put($uploadUrl),
                    'TikTok parça yükleme',
                );
            }
        } finally {
            fclose($handle);
        }

        $status = data_get($this->check(
            $this->http()->withToken($token)->post(self::API.'/v2/post/publish/status/fetch/', ['publish_id' => $publishId]),
            'TikTok yayın durumu',
        )->json(), 'data.status');

        if ($status === 'FAILED') {
            throw new PlatformException('TikTok yüklemeyi reddetti.');
        }

        return new PublishResult(
            externalId: $publishId,
            url: null,
            note: $direct
                ? 'TikTok\'a gönderildi (durum: '.($status ?? 'işleniyor').'). Denetlenmemiş uygulamalarda görünürlük "özel"dir.'
                : 'Video TikTok gelen kutunuza taslak olarak gönderildi. TikTok uygulamasında açıp yayını tamamlayın, ardından burada "Manuel yayınlandı" olarak işaretleyin.',
            completed: $direct && $status === 'PUBLISH_COMPLETE',
            raw: ['status' => $status, 'mode' => $direct ? 'direct' : 'inbox'],
        );
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new NotConfiguredException('TikTok uygulama bilgileri Ayarlar sayfasında girilmemiş.');
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function tokenSet(array $json): TokenSet
    {
        if (isset($json['error']) && $json['error'] !== '' && ! isset($json['access_token'])) {
            throw new PlatformException('TikTok: '.($json['error_description'] ?? $json['error']));
        }

        return new TokenSet(
            accessToken: $json['access_token'],
            refreshToken: $json['refresh_token'] ?? null,
            expiresAt: isset($json['expires_in']) ? CarbonImmutable::now()->addSeconds((int) $json['expires_in']) : null,
            scopes: isset($json['scope']) ? array_map('trim', explode(',', $json['scope'])) : [],
            refreshExpiresAt: isset($json['refresh_expires_in']) ? CarbonImmutable::now()->addSeconds((int) $json['refresh_expires_in']) : null,
            meta: ['open_id' => $json['open_id'] ?? null],
        );
    }
}
