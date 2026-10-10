<?php

namespace App\Services\Sync;

use App\Enums\AccountStatus;
use App\Enums\Capability;
use App\Models\AccountMetricSnapshot;
use App\Models\Comment;
use App\Models\ExternalPost;
use App\Models\PostMetricSnapshot;
use App\Models\SocialAccount;
use App\Models\SyncLog;
use App\Services\NotificationService;
use App\Services\Platforms\Contracts\ProvidesDailyAnalytics;
use App\Services\Platforms\Exceptions\QuotaExceededException;
use App\Services\Platforms\Exceptions\TokenExpiredException;
use App\Services\Platforms\Exceptions\UnsupportedOperationException;
use App\Services\Platforms\PlatformManager;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hesap verilerini platformdan çeker ve tarihsel ölçümleri (snapshot) kaydeder.
 * İzni olmayan metrikler NULL bırakılır.
 */
class AccountSyncService
{
    public function __construct(
        private PlatformManager $platforms,
        private NotificationService $notifications,
    ) {}

    /**
     * @return array{status: string, message: string}
     */
    public function sync(SocialAccount $account, bool $withComments = true): array
    {
        if ($account->connection_type === 'manual') {
            return ['status' => 'skipped', 'message' => 'Manuel hesap: veriler elle veya CSV ile girilir.'];
        }

        $log = SyncLog::create([
            'social_account_id' => $account->id,
            'type' => 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $driver = $this->platforms->for($account);
        $caps = $driver->capabilities($account);
        $messages = [];
        $items = 0;
        $partial = false;

        try {
            // 1) Profil ve günlük hesap ölçümü
            if ($caps->can(Capability::ReadFollowers)) {
                $profile = $driver->fetchProfile($account);
                $account->fill([
                    'external_id' => $account->external_id ?: $profile->externalId,
                    'display_name' => $profile->displayName ?? $account->display_name,
                    'avatar_url' => $profile->avatarUrl ?? $account->avatar_url,
                    'profile_url' => $profile->profileUrl ?? $account->profile_url,
                    'followers_count' => $profile->followers,
                    'posts_count' => $profile->postsCount,
                ]);

                $snapshot = AccountMetricSnapshot::firstOrNew([
                    'social_account_id' => $account->id,
                    'captured_on' => now()->toDateString(),
                ]);
                $previousFollowers = $this->previousFollowers($account);
                $snapshot->fill([
                    'followers' => $profile->followers,
                    'following' => $profile->following,
                    'posts_count' => $profile->postsCount,
                    'source' => $account->is_demo ? 'demo' : 'api',
                    'is_demo' => $account->is_demo,
                ])->save();
                $items++;

                $this->checkFollowerDrop($account, $previousFollowers, $profile->followers);
            } else {
                $messages[] = 'Takipçi verisi: '.$caps->reason(Capability::ReadFollowers);
            }

            // 2) Gönderiler ve gönderi ölçümleri
            $dailyTotals = ['views' => null, 'likes' => null, 'comments' => null, 'shares' => null];
            if ($caps->can(Capability::ListPosts)) {
                foreach ($driver->fetchRecentPosts($account, 25) as $remote) {
                    $post = ExternalPost::updateOrCreate(
                        ['social_account_id' => $account->id, 'external_id' => $remote->externalId],
                        [
                            'title' => $remote->title,
                            'url' => $remote->url,
                            'thumbnail_url' => $remote->thumbnailUrl,
                            'published_at' => $remote->publishedAt,
                            'content_type' => $remote->contentType,
                            'views' => $remote->views,
                            'likes' => $remote->likes,
                            'comments' => $remote->comments,
                            'shares' => $remote->shares,
                            'source' => $account->is_demo ? 'demo' : 'api',
                            'is_demo' => $account->is_demo,
                        ],
                    );

                    PostMetricSnapshot::updateOrCreate(
                        ['external_post_id' => $post->id, 'captured_on' => now()->toDateString()],
                        [
                            'views' => $remote->views,
                            'likes' => $remote->likes,
                            'comments' => $remote->comments,
                            'shares' => $remote->shares,
                            'source' => $account->is_demo ? 'demo' : 'api',
                            'is_demo' => $account->is_demo,
                        ],
                    );
                    $items++;
                }
            } else {
                $messages[] = 'Gönderiler: '.$caps->reason(Capability::ListPosts);
            }

            // 3) Günlük analitik (yalnızca destekleyen platformlar; ör. YouTube Analytics)
            if ($driver instanceof ProvidesDailyAnalytics && $caps->can(Capability::ReadAnalytics)) {
                try {
                    $daily = $driver->fetchDailyAnalytics($account, now()->subDays(7), now()->subDay());
                    foreach ($daily as $day => $metrics) {
                        AccountMetricSnapshot::updateOrCreate(
                            ['social_account_id' => $account->id, 'captured_on' => $day],
                            $metrics + ['source' => 'api', 'is_demo' => false],
                        );
                    }
                } catch (QuotaExceededException|TokenExpiredException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    $partial = true;
                    $messages[] = 'Analitik alınamadı: '.$e->getMessage();
                }
            }

            // 4) Yorumlar
            if ($withComments && $caps->can(Capability::ReadComments)) {
                $items += $this->syncComments($account);
            }

            $account->fill([
                'status' => $account->connection_type === 'oauth' ? AccountStatus::Connected : $account->status,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();

            $status = $partial ? 'partial' : 'success';
            $message = $messages ? implode(' | ', $messages) : 'Senkronizasyon tamamlandı.';
        } catch (TokenExpiredException $e) {
            $account->forceFill(['status' => AccountStatus::NeedsReauth, 'last_error' => $e->getMessage()])->save();
            $this->notifications->notify(
                'account.reauth_required',
                "{$account->platform->label()} hesabı tekrar bağlanmalı",
                "@{$account->handle}: ".$e->getMessage(),
                'warning',
                '/accounts/'.$account->id,
                dedupeKey: 'reauth:'.$account->id.':'.now()->toDateString(),
                audience: 'admins',
            );
            [$status, $message] = ['failed', $e->getMessage()];
        } catch (QuotaExceededException $e) {
            $account->forceFill(['last_error' => $e->getMessage()])->save();
            $this->notifications->notify(
                'api.quota_exceeded',
                "{$account->platform->label()} API kotası doldu",
                $e->getMessage(),
                'warning',
                '/settings',
                dedupeKey: 'quota:'.$account->platform->value.':'.now()->toDateString(),
                audience: 'admins',
            );
            [$status, $message] = ['failed', $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Senkronizasyon hatası', ['account_id' => $account->id, 'error' => $e->getMessage()]);
            $account->forceFill(['status' => AccountStatus::Error, 'last_error' => $e->getMessage()])->save();
            $this->notifications->notify(
                'account.connection_error',
                "{$account->platform->label()} bağlantı hatası",
                "@{$account->handle}: ".$e->getMessage(),
                'error',
                '/accounts/'.$account->id,
                dedupeKey: 'syncerr:'.$account->id.':'.now()->format('Y-m-d-H'),
                audience: 'admins',
            );
            [$status, $message] = ['failed', $e->getMessage()];
        }

        $log->update([
            'status' => $status,
            'items_count' => $items,
            'message' => mb_substr($message, 0, 2000),
            'finished_at' => now(),
        ]);

        return ['status' => $status, 'message' => $message];
    }

    public function syncComments(SocialAccount $account): int
    {
        $driver = $this->platforms->for($account);
        $count = 0;

        $posts = $account->externalPosts()->latest('published_at')->limit(5)->get();
        foreach ($posts as $post) {
            try {
                $remoteComments = $driver->fetchComments($account, $post->external_id, 50);
            } catch (UnsupportedOperationException) {
                return $count;
            }

            foreach ($remoteComments as $rc) {
                $comment = Comment::firstOrCreate(
                    ['social_account_id' => $account->id, 'external_id' => $rc->externalId],
                    [
                        'external_post_id' => $post->id,
                        'parent_external_id' => $rc->parentExternalId,
                        'author_name' => $rc->authorName,
                        'author_avatar_url' => $rc->authorAvatarUrl,
                        'body' => $rc->body,
                        'like_count' => $rc->likeCount,
                        'commented_at' => $rc->commentedAt,
                        'is_demo' => $account->is_demo,
                    ],
                );
                if ($comment->wasRecentlyCreated) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function previousFollowers(SocialAccount $account): ?int
    {
        return AccountMetricSnapshot::where('social_account_id', $account->id)
            ->whereDate('captured_on', '<', now()->toDateString())
            ->whereNotNull('followers')
            ->latest('captured_on')
            ->value('followers');
    }

    private function checkFollowerDrop(SocialAccount $account, ?int $previous, ?int $current): void
    {
        if ($previous === null || $current === null || $previous < 100) {
            return;
        }

        if ($current < $previous * 0.95) {
            $this->notifications->notify(
                'account.followers_drop',
                "{$account->platform->label()} @{$account->handle}: belirgin takipçi kaybı",
                sprintf('%s → %s (%%%.1f)', number_format($previous, 0, ',', '.'), number_format($current, 0, ',', '.'), ($current - $previous) / $previous * 100),
                'warning',
                '/accounts/'.$account->id,
                dedupeKey: 'drop:'.$account->id.':'.now()->toDateString(),
            );
        }
    }
}
