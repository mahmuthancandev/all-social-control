<?php

namespace App\Services\Platforms\Drivers;

use App\Enums\Capability;
use App\Enums\Platform;
use App\Models\AccountCredential;
use App\Models\Comment;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;
use App\Services\Platforms\Contracts\SocialPlatformInterface;
use App\Services\Platforms\Data\CapabilitySet;
use App\Services\Platforms\Data\ProfileData;
use App\Services\Platforms\Data\PublishResult;
use App\Services\Platforms\Data\RemoteComment;
use App\Services\Platforms\Data\RemotePost;
use App\Services\Platforms\Data\TokenSet;
use App\Services\Platforms\Exceptions\UnsupportedOperationException;
use Illuminate\Support\Str;

/**
 * DEMO hesaplar için ağ çağrısı yapmayan sürücü. Gerçek platformun resmi API'sinin
 * tam izinle sunduğu yetenekleri taklit eder; tüm çıktılar DEMO olarak işaretlenir.
 */
class DemoPlatformService implements SocialPlatformInterface
{
    private const COMMENTS = [
        'Harika görünüyor! Ne zaman çıkıyor?', 'Bu güncelleme çok iyi olmuş 🔥', 'Android sürümü var mı?',
        'Yeni bölümü bekliyoruz!', 'Grafikler çok güzel', 'Çok eğlenceli, arkadaşlarımla oynuyorum',
        'Bug var galiba, level 3\'te takılıyor', 'Türkçe dil desteği gelecek mi?', 'Muhteşem müzik 👏',
        'Bu oyunu nereden indirebilirim?',
    ];

    public function __construct(private Platform $platform) {}

    public function platform(): Platform
    {
        return $this->platform;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function setupNotes(): array
    {
        return ['Demo hesap: gerçek platforma bağlanmaz, veriler simüle edilir.'];
    }

    public function usesPkce(): bool
    {
        return false;
    }

    public function oauthScopes(): array
    {
        return [];
    }

    public function authorizationUrl(string $state, ?string $codeChallenge, string $redirectUri): string
    {
        throw new UnsupportedOperationException('Demo hesaplar OAuth kullanmaz.');
    }

    public function exchangeCode(string $code, ?string $codeVerifier, string $redirectUri): TokenSet
    {
        throw new UnsupportedOperationException('Demo hesaplar OAuth kullanmaz.');
    }

    public function refreshToken(AccountCredential $credential): TokenSet
    {
        throw new UnsupportedOperationException('Demo hesaplar OAuth kullanmaz.');
    }

    public function capabilities(SocialAccount $account): CapabilitySet
    {
        $note = 'DEMO simülasyonu';
        $set = new CapabilitySet;

        return match ($this->platform) {
            Platform::YouTube => $set
                ->set(Capability::ReadFollowers, true, $note)->set(Capability::ListPosts, true, $note)
                ->set(Capability::ReadAnalytics, true, $note)->set(Capability::PublishVideo, true, $note)
                ->set(Capability::PublishImage, false, 'YouTube API görsel gönderiyi desteklemiyor.')
                ->set(Capability::PublishText, false, 'YouTube API metin gönderisini desteklemiyor.')
                ->set(Capability::ReadComments, true, $note)->set(Capability::ReplyComments, true, $note),
            Platform::Instagram => $set
                ->set(Capability::ReadFollowers, true, $note)->set(Capability::ListPosts, true, $note)
                ->set(Capability::ReadAnalytics, true, $note)->set(Capability::PublishVideo, true, $note)
                ->set(Capability::PublishImage, true, $note)
                ->set(Capability::PublishText, false, 'Instagram yalnızca medyalı gönderi kabul eder.')
                ->set(Capability::ReadComments, true, $note)->set(Capability::ReplyComments, true, $note),
            Platform::TikTok => $set
                ->set(Capability::ReadFollowers, true, $note)->set(Capability::ListPosts, true, $note)
                ->set(Capability::ReadAnalytics, true, $note)->set(Capability::PublishVideo, true, $note)
                ->set(Capability::PublishImage, false, 'Fotoğraf gönderisi Paylaşım Asistanı ile yapılır.')
                ->set(Capability::PublishText, false, 'TikTok metin gönderisini desteklemez.')
                ->set(Capability::ReadComments, false, 'TikTok\'un yorumlar için herkese açık resmi API\'si yok.')
                ->set(Capability::ReplyComments, false, 'TikTok\'un yorum cevaplama için herkese açık resmi API\'si yok.'),
            // X API ücretli olduğundan demo da gerçek davranışı yansıtır: yalnızca manuel akış.
            Platform::X => CapabilitySet::none('X API ücretli olduğu için kapalı. Paylaşım Asistanı kullanılır.')
                ->set(Capability::ReadFollowers, true, 'DEMO simülasyonu (gerçekte manuel/CSV giriş)')
                ->set(Capability::ListPosts, true, 'DEMO simülasyonu (gerçekte manuel/CSV giriş)'),
        };
    }

    public function fetchProfile(SocialAccount $account): ProfileData
    {
        $base = $account->followers_count ?? (5000 + $account->id * 1371);
        $growth = (int) round($base * (mt_rand(-5, 25) / 1000));

        return new ProfileData(
            externalId: $account->external_id ?? 'demo_'.$account->id,
            handle: $account->handle,
            displayName: $account->display_name,
            avatarUrl: $account->avatar_url,
            profileUrl: $account->profile_url,
            followers: max(0, $base + $growth),
            following: $this->platform === Platform::YouTube ? null : 100 + $account->id * 7,
            postsCount: ($account->posts_count ?? 40) + (mt_rand(0, 9) === 0 ? 1 : 0),
        );
    }

    public function fetchRecentPosts(SocialAccount $account, int $limit = 25): array
    {
        return $account->externalPosts()->latest('published_at')->limit($limit)->get()
            ->map(function ($post) {
                $views = $post->views === null ? null : (int) ($post->views * (1 + mt_rand(0, 40) / 1000));

                return new RemotePost(
                    externalId: $post->external_id,
                    title: $post->title,
                    url: $post->url,
                    thumbnailUrl: $post->thumbnail_url,
                    publishedAt: $post->published_at,
                    contentType: $post->content_type,
                    views: $views,
                    likes: $post->likes === null ? null : (int) ($post->likes * (1 + mt_rand(0, 30) / 1000)),
                    comments: $post->comments === null ? null : $post->comments + mt_rand(0, 3),
                    shares: $post->shares === null ? null : $post->shares + mt_rand(0, 2),
                );
            })->all();
    }

    public function fetchComments(SocialAccount $account, string $postExternalId, int $limit = 50): array
    {
        if (! $this->capabilities($account)->can(Capability::ReadComments)) {
            throw new UnsupportedOperationException('Bu platformda yorum okuma desteklenmiyor.');
        }

        $count = mt_rand(0, 2);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = new RemoteComment(
                externalId: 'demo_c_'.Str::lower(Str::random(10)),
                postExternalId: $postExternalId,
                body: self::COMMENTS[array_rand(self::COMMENTS)],
                authorName: 'demo_kullanici'.mt_rand(10, 999),
                commentedAt: now()->subMinutes(mt_rand(1, 600)),
                likeCount: mt_rand(0, 40),
            );
        }

        return $out;
    }

    public function replyToComment(SocialAccount $account, Comment $comment, string $text): string
    {
        if (! $this->capabilities($account)->can(Capability::ReplyComments)) {
            throw new UnsupportedOperationException('Bu platformda yorumlara cevap desteklenmiyor.');
        }

        return 'demo_reply_'.Str::lower(Str::random(10));
    }

    public function publish(PostPlatformTarget $target): PublishResult
    {
        if (! $this->capabilities($target->socialAccount)->canPublish($target->content_type)) {
            throw new UnsupportedOperationException('Bu içerik türü bu platformda otomatik yayınlanamaz.');
        }

        return new PublishResult(
            externalId: 'demo_'.Str::lower(Str::random(12)),
            url: null,
            note: 'DEMO simülasyonu — gerçek platforma hiçbir şey gönderilmedi.',
        );
    }
}
