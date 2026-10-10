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
use App\Services\Platforms\Exceptions\TokenExpiredException;
use App\Services\Platforms\Exceptions\UnsupportedOperationException;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * X (Twitter) API v2.
 *
 * X, Şubat 2026'dan itibaren yeni geliştiricilere ücretsiz katman sunmuyor (kullandıkça öde / pay-per-use).
 * Bu nedenle API varsayılan olarak KAPALIDIR; yayın ücretsiz ve resmi "web intent" sayfası
 * (x.com/intent/post) ile Paylaşım Asistanı üzerinden yapılır, metrikler manuel/CSV ile girilir.
 * Kod, ileride ücretsiz/izinli erişim olursa Ayarlar'dan açılabilecek şekilde hazırdır.
 */
class XService extends AbstractPlatformService
{
    private const API = 'https://api.x.com/2';

    public function platform(): Platform
    {
        return Platform::X;
    }

    public function enabled(): bool
    {
        return (bool) Settings::get('x_api_enabled', false);
    }

    public function isConfigured(): bool
    {
        return $this->enabled() && Settings::has('x_client_id');
    }

    public function setupNotes(): array
    {
        return [
            'X API Şubat 2026\'dan beri yeni geliştiriciler için ücretli (kullandıkça öde). Bu yüzden varsayılan olarak kapalıdır.',
            'Ücretsiz yöntem: Paylaşım Asistanı, metni hazırlayıp resmi x.com/intent/post sayfasını açar; siz "Gönder"e basarsınız.',
            'Metrikler için hesabı "Manuel" ekleyip X Analytics verilerini elle veya CSV ile girebilirsiniz.',
            'API\'yi yalnızca ücretini bilerek ve kabul ederek açın; güncel fiyatları docs.x.com üzerinden kontrol edin.',
        ];
    }

    public function usesPkce(): bool
    {
        return true;
    }

    public function oauthScopes(): array
    {
        return ['tweet.read', 'tweet.write', 'users.read', 'offline.access'];
    }

    public function authorizationUrl(string $state, ?string $codeChallenge, string $redirectUri): string
    {
        $this->ensureEnabled();

        return 'https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => Settings::get('x_client_id'),
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $this->oauthScopes()),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchangeCode(string $code, ?string $codeVerifier, string $redirectUri): TokenSet
    {
        $this->ensureEnabled();

        return $this->tokenSet($this->check($this->tokenRequest()->post(self::API.'/oauth2/token', [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => Settings::get('x_client_id'),
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ]), 'X token alma')->json());
    }

    public function refreshToken(AccountCredential $credential): TokenSet
    {
        if (! $credential->refresh_token) {
            throw new TokenExpiredException('X bağlantısının süresi doldu; yeniden bağlayın.');
        }

        return $this->tokenSet($this->check($this->tokenRequest()->post(self::API.'/oauth2/token', [
            'refresh_token' => $credential->refresh_token,
            'grant_type' => 'refresh_token',
            'client_id' => Settings::get('x_client_id'),
        ]), 'X token yenileme')->json());
    }

    public function capabilities(SocialAccount $account): CapabilitySet
    {
        if (! $this->enabled() && $account->connection_type === 'oauth') {
            return CapabilitySet::none('X API ücretli olduğu için kapalı. Paylaşım Asistanı ve manuel veri girişi kullanılır.');
        }

        return parent::capabilities($account);
    }

    protected function oauthCapabilities(SocialAccount $account, array $scopes): CapabilitySet
    {
        $has = fn (string $s) => in_array($s, $scopes, true);
        $cost = 'X API ücretlidir; her çağrı kredi tüketir.';

        return (new CapabilitySet)
            ->set(Capability::ReadFollowers, $has('users.read'), $has('users.read') ? $cost : 'users.read izni verilmedi.')
            ->set(Capability::ListPosts, $has('tweet.read'), $has('tweet.read') ? $cost : 'tweet.read izni verilmedi.')
            ->set(Capability::ReadAnalytics, false, 'Detaylı analitik bu sürümde desteklenmiyor; CSV içe aktarmayı kullanın.')
            ->set(Capability::PublishText, $has('tweet.write'), $has('tweet.write') ? $cost : 'tweet.write izni verilmedi.')
            ->set(Capability::PublishImage, false, 'Medya gönderileri Paylaşım Asistanı ile yapılır.')
            ->set(Capability::PublishVideo, false, 'Medya gönderileri Paylaşım Asistanı ile yapılır.')
            ->set(Capability::ReadComments, false, 'Yanıtları okumak ücretli arama uç noktası gerektirir; desteklenmiyor.')
            ->set(Capability::ReplyComments, false, 'Desteklenmiyor; yanıtları X üzerinde yönetin.');
    }

    public function fetchProfile(SocialAccount $account): ProfileData
    {
        $user = data_get($this->check(
            $this->http()->withToken($this->accessToken($account))->get(self::API.'/users/me', [
                'user.fields' => 'profile_image_url,public_metrics,username,name',
            ]),
            'X profil',
        )->json(), 'data', []);

        return new ProfileData(
            externalId: (string) $user['id'],
            handle: $user['username'],
            displayName: $user['name'] ?? null,
            avatarUrl: $user['profile_image_url'] ?? null,
            profileUrl: 'https://x.com/'.$user['username'],
            followers: data_get($user, 'public_metrics.followers_count'),
            following: data_get($user, 'public_metrics.following_count'),
            postsCount: data_get($user, 'public_metrics.tweet_count'),
        );
    }

    public function fetchRecentPosts(SocialAccount $account, int $limit = 25): array
    {
        $json = $this->check(
            $this->http()->withToken($this->accessToken($account))->get(self::API.'/users/'.$account->external_id.'/tweets', [
                'max_results' => max(5, min(100, $limit)),
                'tweet.fields' => 'created_at,public_metrics',
            ]),
            'X gönderiler',
        )->json();

        return array_map(fn (array $t) => new RemotePost(
            externalId: (string) $t['id'],
            title: mb_substr((string) ($t['text'] ?? ''), 0, 200),
            url: 'https://x.com/'.$account->handle.'/status/'.$t['id'],
            thumbnailUrl: null,
            publishedAt: isset($t['created_at']) ? CarbonImmutable::parse($t['created_at'])->setTimezone(config('app.timezone')) : null,
            contentType: ContentType::XPost,
            views: data_get($t, 'public_metrics.impression_count'),
            likes: data_get($t, 'public_metrics.like_count'),
            comments: data_get($t, 'public_metrics.reply_count'),
            shares: data_get($t, 'public_metrics.retweet_count'),
        ), $json['data'] ?? []);
    }

    public function publish(PostPlatformTarget $target): PublishResult
    {
        if ($target->content_type !== ContentType::XPost) {
            throw new UnsupportedOperationException('X medya gönderileri Paylaşım Asistanı ile yapılır.');
        }

        $json = $this->check(
            $this->http()->withToken($this->accessToken($target->socialAccount))->post(self::API.'/tweets', [
                'text' => mb_substr($target->fullCaption(), 0, 280),
            ]),
            'X gönderi',
        )->json();

        $id = (string) data_get($json, 'data.id');

        return new PublishResult(externalId: $id, url: 'https://x.com/'.$target->socialAccount->handle.'/status/'.$id);
    }

    private function tokenRequest(): PendingRequest
    {
        $request = $this->http()->asForm();
        if (Settings::has('x_client_secret')) {
            $request = $request->withBasicAuth((string) Settings::get('x_client_id'), (string) Settings::get('x_client_secret'));
        }

        return $request;
    }

    private function ensureEnabled(): void
    {
        if (! $this->enabled()) {
            throw new NotConfiguredException('X API kapalı (ücretli). Ayarlar\'dan bilerek açmadıkça kullanılmaz.');
        }
        if (! Settings::has('x_client_id')) {
            throw new NotConfiguredException('X Client ID girilmemiş.');
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function tokenSet(array $json): TokenSet
    {
        return new TokenSet(
            accessToken: $json['access_token'],
            refreshToken: $json['refresh_token'] ?? null,
            expiresAt: isset($json['expires_in']) ? CarbonImmutable::now()->addSeconds((int) $json['expires_in']) : null,
            scopes: isset($json['scope']) ? explode(' ', $json['scope']) : [],
        );
    }
}
