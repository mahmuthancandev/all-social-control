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
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Data\ProfileData;
use App\Services\Platforms\Data\PublishResult;
use App\Services\Platforms\Data\RemoteComment;
use App\Services\Platforms\Data\RemotePost;
use App\Services\Platforms\Data\TokenSet;
use App\Services\Platforms\Exceptions\NotConfiguredException;
use App\Services\Platforms\Exceptions\PlatformException;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Instagram API with Instagram Login (graph.instagram.com) — resmi ve ücretsiz.
 * Yalnızca Profesyonel (İşletme/İçerik Üreticisi) hesaplarda çalışır.
 *
 * Yayınlama için Meta, medyayı herkese açık bir URL'den kendisi indirir. Yerel bilgisayardaki
 * dosyalar internete açık olmadığından, "herkese açık medya URL tabanı" ayarlanmadıkça
 * otomatik yayın kapalıdır ve Paylaşım Asistanı kullanılır.
 */
class InstagramService extends AbstractPlatformService
{
    private const GRAPH = 'https://graph.instagram.com';

    public function platform(): Platform
    {
        return Platform::Instagram;
    }

    public function isConfigured(): bool
    {
        return Settings::has('instagram_app_id') && Settings::has('instagram_app_secret');
    }

    public function setupNotes(): array
    {
        return [
            'developers.facebook.com üzerinde ücretsiz bir uygulama oluşturup "Instagram API with Instagram Login" ürününü ekleyin.',
            'Hesap Instagram Profesyonel (İşletme veya İçerik Üreticisi) hesabı olmalıdır.',
            'Geliştirme modunda yalnızca uygulamaya test kullanıcısı olarak eklenen hesaplar bağlanabilir; herkese açmak için Meta uygulama incelemesi gerekir.',
            'Meta yönlendirme adresi olarak https isteyebilir. Gerekirse "OAuth yönlendirme adresi tabanı" ayarını kullanın.',
            'Otomatik yayın, medyanın herkese açık bir URL\'de olmasını gerektirir. Bu ayar boşsa Paylaşım Asistanı kullanılır.',
        ];
    }

    public function oauthScopes(): array
    {
        return [
            'instagram_business_basic',
            'instagram_business_content_publish',
            'instagram_business_manage_comments',
            'instagram_business_manage_insights',
        ];
    }

    public function authorizationUrl(string $state, ?string $codeChallenge, string $redirectUri): string
    {
        $this->ensureConfigured();

        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => Settings::get('instagram_app_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $this->oauthScopes()),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier, string $redirectUri): TokenSet
    {
        $this->ensureConfigured();

        $short = $this->check($this->http()->asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => Settings::get('instagram_app_id'),
            'client_secret' => Settings::get('instagram_app_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]), 'Instagram token alma')->json();

        $shortToken = $short['access_token'] ?? data_get($short, 'data.0.access_token');
        $permissions = $short['permissions'] ?? data_get($short, 'data.0.permissions', []);
        if (is_string($permissions)) {
            $permissions = explode(',', $permissions);
        }

        // Kısa ömürlü tokenı 60 günlük tokena çevir.
        $long = $this->check($this->http()->get(self::GRAPH.'/access_token', [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => Settings::get('instagram_app_secret'),
            'access_token' => $shortToken,
        ]), 'Instagram uzun ömürlü token')->json();

        return new TokenSet(
            accessToken: $long['access_token'],
            refreshToken: null,
            expiresAt: CarbonImmutable::now()->addSeconds((int) ($long['expires_in'] ?? 5184000)),
            scopes: array_values($permissions),
        );
    }

    public function refreshToken(AccountCredential $credential): TokenSet
    {
        $json = $this->check($this->http()->get(self::GRAPH.'/refresh_access_token', [
            'grant_type' => 'ig_refresh_token',
            'access_token' => $credential->access_token,
        ]), 'Instagram token yenileme')->json();

        return new TokenSet(
            accessToken: $json['access_token'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) ($json['expires_in'] ?? 5184000)),
            scopes: $credential->scopes ?? [],
        );
    }

    protected function oauthCapabilities(SocialAccount $account, array $scopes): CapabilitySet
    {
        $has = fn (string $s) => in_array($s, $scopes, true);
        $basic = $has('instagram_business_basic');
        $publish = $has('instagram_business_content_publish');
        $publicUrl = Settings::has('instagram_public_media_base_url');
        $publishReason = ! $publish
            ? 'instagram_business_content_publish izni verilmedi.'
            : ($publicUrl ? null : 'Meta medyayı herkese açık URL\'den indirir; Ayarlar\'da "herkese açık medya URL tabanı" tanımlı değil. Paylaşım Asistanı kullanılır.');

        return (new CapabilitySet)
            ->set(Capability::ReadFollowers, $basic, $basic ? null : 'instagram_business_basic izni verilmedi.')
            ->set(Capability::ListPosts, $basic, $basic ? null : 'instagram_business_basic izni verilmedi.')
            ->set(Capability::ReadAnalytics, $has('instagram_business_manage_insights'), $has('instagram_business_manage_insights') ? null : 'instagram_business_manage_insights izni verilmedi.')
            ->set(Capability::PublishVideo, $publish && $publicUrl, $publishReason)
            ->set(Capability::PublishImage, $publish && $publicUrl, $publishReason)
            ->set(Capability::PublishText, false, 'Instagram yalnızca medyalı gönderi kabul eder.')
            ->set(Capability::ReadComments, $has('instagram_business_manage_comments'), $has('instagram_business_manage_comments') ? null : 'instagram_business_manage_comments izni verilmedi.')
            ->set(Capability::ReplyComments, $has('instagram_business_manage_comments'), $has('instagram_business_manage_comments') ? null : 'instagram_business_manage_comments izni verilmedi.');
    }

    public function fetchProfile(SocialAccount $account): ProfileData
    {
        $json = $this->graph($account, 'GET', '/me', [
            'fields' => 'user_id,username,name,profile_picture_url,followers_count,follows_count,media_count',
        ], 'Instagram profil');

        return new ProfileData(
            externalId: (string) ($json['user_id'] ?? $json['id']),
            handle: $json['username'],
            displayName: $json['name'] ?? $json['username'],
            avatarUrl: $json['profile_picture_url'] ?? null,
            profileUrl: 'https://www.instagram.com/'.$json['username'],
            followers: isset($json['followers_count']) ? (int) $json['followers_count'] : null,
            following: isset($json['follows_count']) ? (int) $json['follows_count'] : null,
            postsCount: isset($json['media_count']) ? (int) $json['media_count'] : null,
        );
    }

    public function fetchRecentPosts(SocialAccount $account, int $limit = 25): array
    {
        $json = $this->graph($account, 'GET', '/me/media', [
            'fields' => 'id,caption,media_type,media_product_type,permalink,thumbnail_url,media_url,timestamp,like_count,comments_count',
            'limit' => min(50, $limit),
        ], 'Instagram gönderiler');

        $withInsights = in_array('instagram_business_manage_insights', $account->granted_scopes ?? [], true);

        return array_map(function (array $m) use ($account, $withInsights) {
            $views = null;
            $shares = null;
            if ($withInsights) {
                try {
                    $insights = $this->graph($account, 'GET', '/'.$m['id'].'/insights', ['metric' => 'views,shares'], 'Instagram içgörü');
                    foreach ($insights['data'] ?? [] as $metric) {
                        $value = data_get($metric, 'values.0.value', data_get($metric, 'total_value.value'));
                        if ($metric['name'] === 'views') {
                            $views = $value !== null ? (int) $value : null;
                        } elseif ($metric['name'] === 'shares') {
                            $shares = $value !== null ? (int) $value : null;
                        }
                    }
                } catch (Throwable) {
                    // Bazı medya türlerinde içgörü yoktur; değerler null kalır.
                }
            }

            $isReel = ($m['media_product_type'] ?? null) === 'REELS';

            return new RemotePost(
                externalId: (string) $m['id'],
                title: isset($m['caption']) ? mb_substr($m['caption'], 0, 200) : null,
                url: $m['permalink'] ?? null,
                thumbnailUrl: $m['thumbnail_url'] ?? (($m['media_type'] ?? '') === 'IMAGE' ? ($m['media_url'] ?? null) : null),
                publishedAt: isset($m['timestamp']) ? CarbonImmutable::parse($m['timestamp'])->setTimezone(config('app.timezone')) : null,
                contentType: $isReel ? ContentType::InstagramReel : ContentType::InstagramPost,
                views: $views,
                likes: isset($m['like_count']) ? (int) $m['like_count'] : null,
                comments: isset($m['comments_count']) ? (int) $m['comments_count'] : null,
                shares: $shares,
            );
        }, $json['data'] ?? []);
    }

    public function fetchComments(SocialAccount $account, string $postExternalId, int $limit = 50): array
    {
        $json = $this->graph($account, 'GET', '/'.$postExternalId.'/comments', [
            'fields' => 'id,text,username,timestamp,like_count',
            'limit' => min(50, $limit),
        ], 'Instagram yorumlar');

        return array_map(fn (array $c) => new RemoteComment(
            externalId: (string) $c['id'],
            postExternalId: $postExternalId,
            body: (string) ($c['text'] ?? ''),
            authorName: $c['username'] ?? null,
            commentedAt: isset($c['timestamp']) ? CarbonImmutable::parse($c['timestamp']) : null,
            likeCount: isset($c['like_count']) ? (int) $c['like_count'] : null,
        ), $json['data'] ?? []);
    }

    public function replyToComment(SocialAccount $account, Comment $comment, string $text): string
    {
        $json = $this->graph($account, 'POST', '/'.$comment->external_id.'/replies', ['message' => $text], 'Instagram yorum cevabı');

        return (string) $json['id'];
    }

    public function publish(PostPlatformTarget $target): PublishResult
    {
        $account = $target->socialAccount;
        $media = $target->mediaFile ?? throw new PlatformException('Instagram gönderisi için medya gerekli.');
        $base = rtrim((string) Settings::get('instagram_public_media_base_url'), '/');
        if ($base === '') {
            throw new NotConfiguredException('Herkese açık medya URL tabanı ayarlanmamış.');
        }
        $publicUrl = $base.'/'.ltrim($media->path, '/');
        $caption = mb_substr($target->fullCaption(), 0, 2200);

        $params = ['caption' => $caption];
        if ($media->type === 'video') {
            $params += ['media_type' => 'REELS', 'video_url' => $publicUrl, 'share_to_feed' => 'true'];
            if ($target->thumbnail) {
                $params['cover_url'] = $base.'/'.ltrim($target->thumbnail->path, '/');
            }
        } else {
            $params['image_url'] = $publicUrl;
        }

        $container = $this->graph($account, 'POST', '/me/media', $params, 'Instagram medya kapsayıcısı');
        $containerId = (string) $container['id'];

        // Video işlenene kadar bekle (en fazla ~5 dakika).
        if ($media->type === 'video') {
            $status = null;
            for ($i = 0; $i < 30; $i++) {
                $status = $this->graph($account, 'GET', '/'.$containerId, ['fields' => 'status_code,status'], 'Instagram işleme durumu');
                if (($status['status_code'] ?? null) === 'FINISHED') {
                    break;
                }
                if (in_array($status['status_code'] ?? null, ['ERROR', 'EXPIRED'], true)) {
                    throw new PlatformException('Instagram videoyu işleyemedi: '.($status['status'] ?? 'bilinmeyen hata'));
                }
                sleep(10);
            }
            if (($status['status_code'] ?? null) !== 'FINISHED') {
                throw new PlatformException('Instagram video işleme zaman aşımına uğradı; daha sonra tekrar denenecek.');
            }
        }

        $published = $this->graph($account, 'POST', '/me/media_publish', ['creation_id' => $containerId], 'Instagram yayınlama');
        $mediaId = (string) $published['id'];

        $permalink = null;
        try {
            $permalink = $this->graph($account, 'GET', '/'.$mediaId, ['fields' => 'permalink'], 'Instagram bağlantı')['permalink'] ?? null;
        } catch (Throwable) {
        }

        return new PublishResult(externalId: $mediaId, url: $permalink);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function graph(SocialAccount $account, string $method, string $path, array $params, string $context): array
    {
        $request = $this->http()->withToken($this->accessToken($account));
        $response = $method === 'GET'
            ? $request->get(self::GRAPH.$path, $params)
            : $request->asForm()->post(self::GRAPH.$path, $params);

        return $this->check($response, $context)->json() ?? [];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new NotConfiguredException('Instagram uygulama bilgileri Ayarlar sayfasında girilmemiş.');
        }
    }
}
