<?php

namespace App\Services\Publishing;

use App\Enums\AccountStatus;
use App\Enums\TargetStatus;
use App\Jobs\PublishTargetJob;
use App\Models\ExternalPost;
use App\Models\PostPlatformTarget;
use App\Models\PublicationJob;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\Platforms\Exceptions\PlatformException;
use App\Services\Platforms\Exceptions\QuotaExceededException;
use App\Services\Platforms\Exceptions\TokenExpiredException;
use App\Services\Platforms\LinkPreviewService;
use App\Services\Platforms\PlatformManager;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Yayın akışının tek merkezi.
 * - API izni varsa kuyruğa alır (retry + backoff, tekrar yayın koruması).
 * - İzin yoksa hedefi "Manuel paylaşım bekliyor" durumuna getirir; asla otomatik yayınlandı göstermez.
 */
class PublishingService
{
    public function __construct(
        private PlatformManager $platforms,
        private NotificationService $notifications,
    ) {}

    /**
     * @return 'api'|'manual'
     */
    public function modeFor(PostPlatformTarget $target): string
    {
        $account = $target->socialAccount;
        $caps = $this->platforms->capabilities($account);

        return $caps->canPublish($target->content_type) ? 'api' : 'manual';
    }

    public function manualReason(PostPlatformTarget $target): ?string
    {
        $caps = $this->platforms->capabilities($target->socialAccount);

        return $caps->canPublish($target->content_type) ? null : $caps->publishReason($target->content_type);
    }

    public function schedule(PostPlatformTarget $target, CarbonInterface $at): void
    {
        $this->guardNotPublished($target);

        $target->fill([
            'status' => TargetStatus::Scheduled,
            'scheduled_at' => $at,
            'publish_mode' => $this->modeFor($target),
            'error_message' => null,
        ])->save();
        $target->post->refreshStatus();
    }

    /**
     * Şimdi yayınla: API destekliyse kuyruğa, değilse manuel akışa.
     */
    public function publishNow(PostPlatformTarget $target, ?User $user = null): string
    {
        $this->guardNotPublished($target);

        if (in_array($target->status, [TargetStatus::Queued, TargetStatus::Publishing], true)) {
            throw new RuntimeException('Bu hedef zaten yayın kuyruğunda.');
        }

        $mode = $this->modeFor($target);

        if ($mode === 'api') {
            $target->fill(['status' => TargetStatus::Queued, 'publish_mode' => 'api', 'error_message' => null])->save();
            PublishTargetJob::dispatch($target->id, $user?->id);
        } else {
            $reason = $this->manualReason($target);
            $target->fill([
                'status' => TargetStatus::ManualRequired,
                'publish_mode' => 'manual',
                'error_message' => null,
            ])->save();

            PublicationJob::create([
                'post_platform_target_id' => $target->id,
                'triggered_by' => $user?->id,
                'mode' => 'manual',
                'status' => 'manual',
                'started_at' => now(),
                'finished_at' => now(),
                'message' => 'Otomatik yayın yapılamıyor: '.$reason,
            ]);

            $this->notifications->notify(
                'publish.manual_required',
                "{$target->platform->label()} için manuel paylaşım gerekiyor",
                ($target->post->title ?: 'Gönderi').' — Paylaşım Asistanı ile tamamlayın.',
                'info',
                '/posts/'.$target->post_id,
                dedupeKey: 'manual:'.$target->id,
                audience: 'admins',
            );
        }

        $target->post->refreshStatus();

        return $mode;
    }

    /**
     * Kuyruk işçisi tarafından çağrılır. Yeniden denenmesi gereken hatalarda istisna fırlatır.
     */
    public function execute(int $targetId, int $attempt = 1, ?int $userId = null, bool $isLastAttempt = false): void
    {
        // Atomik sahiplenme: yalnızca "Kuyrukta" olan hedef işlenir → çift yayın engellenir.
        $claimed = PostPlatformTarget::whereKey($targetId)
            ->where('status', TargetStatus::Queued->value)
            ->whereNull('external_id')
            ->update(['status' => TargetStatus::Publishing->value, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $target = PostPlatformTarget::with(['socialAccount.credential', 'post', 'mediaFile', 'thumbnail'])->findOrFail($targetId);
        $account = $target->socialAccount;

        $job = PublicationJob::create([
            'post_platform_target_id' => $target->id,
            'triggered_by' => $userId,
            'mode' => $account->is_demo ? 'demo' : 'api',
            'status' => 'running',
            'attempt' => $attempt,
            'started_at' => now(),
        ]);

        try {
            $result = $this->platforms->for($account)->publish($target);
        } catch (TokenExpiredException $e) {
            $account->forceFill(['status' => AccountStatus::NeedsReauth, 'last_error' => $e->getMessage()])->save();
            $this->fail($target, $job, $e->getMessage(), 'account.reauth_required');

            return;
        } catch (QuotaExceededException $e) {
            $this->fail($target, $job, $e->getMessage(), 'api.quota_exceeded');

            return;
        } catch (PlatformException $e) {
            if ($e->retryable && ! $isLastAttempt) {
                $target->forceFill(['status' => TargetStatus::Queued, 'error_message' => $e->getMessage()])->save();
                $job->update(['status' => 'failed', 'finished_at' => now(), 'message' => 'Tekrar denenecek: '.$e->getMessage()]);
                throw $e;
            }
            $this->fail($target, $job, $e->getMessage());

            return;
        } catch (Throwable $e) {
            Log::error('Yayın hatası', ['target_id' => $target->id, 'error' => $e->getMessage()]);
            if (! $isLastAttempt) {
                $target->forceFill(['status' => TargetStatus::Queued, 'error_message' => $e->getMessage()])->save();
                $job->update(['status' => 'failed', 'finished_at' => now(), 'message' => 'Tekrar denenecek: '.$e->getMessage()]);
                throw $e;
            }
            $this->fail($target, $job, $e->getMessage());

            return;
        }

        $label = $target->platform->label();
        $title = $target->title ?: $target->post->title ?: 'Gönderi';

        if ($result->completed) {
            $target->forceFill([
                'status' => TargetStatus::Published,
                'published_at' => now(),
                'external_id' => $result->externalId,
                'external_url' => $result->url,
                'error_message' => $result->note,
                'is_demo' => $account->is_demo,
            ])->save();

            ExternalPost::updateOrCreate(
                ['social_account_id' => $account->id, 'external_id' => $result->externalId],
                [
                    'post_platform_target_id' => $target->id,
                    'title' => mb_substr($title, 0, 500),
                    'url' => $result->url,
                    'content_type' => $target->content_type,
                    'published_at' => now(),
                    'source' => $account->is_demo ? 'demo' : 'api',
                    'is_demo' => $account->is_demo,
                ],
            );

            $job->update(['status' => 'succeeded', 'finished_at' => now(), 'message' => $result->note ?? 'Yayınlandı.', 'response' => $result->raw ?: null]);

            $this->notifications->notify(
                'publish.succeeded',
                ($account->is_demo ? '[DEMO] ' : '')."{$label}: yayınlandı",
                $title.($result->note ? ' — '.$result->note : ''),
                'success',
                '/posts/'.$target->post_id,
                dedupeKey: 'published:'.$target->id,
            );
        } else {
            // İçerik platforma ulaştı fakat yayın kullanıcı tarafından tamamlanmalı (ör. TikTok gelen kutusu).
            $target->forceFill([
                'status' => TargetStatus::ManualRequired,
                'external_id' => null,
                'error_message' => $result->note,
                'settings' => array_merge($target->settings ?? [], ['platform_upload_id' => $result->externalId]),
            ])->save();
            $job->update(['status' => 'manual', 'finished_at' => now(), 'message' => $result->note, 'response' => $result->raw ?: null]);

            $this->notifications->notify(
                'publish.draft_sent',
                "{$label}: taslak gönderildi, yayını tamamlayın",
                $title.' — '.$result->note,
                'info',
                '/posts/'.$target->post_id,
                dedupeKey: 'draft:'.$target->id,
                audience: 'admins',
            );
        }

        $target->post->refreshStatus();
    }

    public function markFailed(int $targetId, string $message): void
    {
        $target = PostPlatformTarget::with('post')->find($targetId);
        if (! $target || $target->status->isFinal() || $target->status === TargetStatus::Failed) {
            return;
        }

        $job = PublicationJob::create([
            'post_platform_target_id' => $target->id,
            'mode' => 'api',
            'status' => 'running',
            'attempt' => $target->attempts,
            'started_at' => now(),
        ]);
        $this->fail($target, $job, $message);
    }

    public function markManuallyPublished(PostPlatformTarget $target, ?string $url, ?User $user): void
    {
        $this->guardNotPublished($target);

        $target->fill([
            'status' => TargetStatus::ManuallyPublished,
            'publish_mode' => 'manual',
            'published_at' => now(),
            'external_url' => $url,
            'error_message' => null,
        ])->save();

        PublicationJob::create([
            'post_platform_target_id' => $target->id,
            'triggered_by' => $user?->id,
            'mode' => 'manual',
            'status' => 'succeeded',
            'started_at' => now(),
            'finished_at' => now(),
            'message' => 'Kullanıcı tarafından manuel yayınlandı olarak işaretlendi.'.($url ? ' Link: '.$url : ''),
        ]);

        if ($url) {
            ExternalPost::updateOrCreate(
                ['social_account_id' => $target->social_account_id, 'external_id' => app(LinkPreviewService::class)->extractId($url, $target->platform)],
                [
                    'post_platform_target_id' => $target->id,
                    'title' => mb_substr((string) ($target->title ?: $target->post->title ?: $target->caption), 0, 500),
                    'url' => $url,
                    'content_type' => $target->content_type,
                    'published_at' => now(),
                    'source' => 'manual',
                    'is_demo' => $target->socialAccount->is_demo,
                ],
            );
        }

        Audit::log('post.manually_published', $target, ['platform' => $target->platform->value, 'url' => $url]);
        $target->post->refreshStatus();
    }

    public function cancel(PostPlatformTarget $target): void
    {
        if ($target->status->isPublished()) {
            throw new RuntimeException('Yayınlanmış bir hedef iptal edilemez.');
        }
        if ($target->status === TargetStatus::Publishing) {
            throw new RuntimeException('Yayınlanmakta olan hedef iptal edilemez.');
        }

        $target->fill(['status' => TargetStatus::Cancelled])->save();
        $target->post->refreshStatus();
    }

    public function revertToDraft(PostPlatformTarget $target): void
    {
        if (in_array($target->status, [TargetStatus::Published, TargetStatus::ManuallyPublished, TargetStatus::Publishing, TargetStatus::Queued], true)) {
            return;
        }
        $target->fill(['status' => TargetStatus::Draft, 'scheduled_at' => null])->save();
    }

    /**
     * Zamanı gelen planlı hedefleri işler. Scheduler her dakika çağırır.
     */
    public function dispatchDue(): int
    {
        $count = 0;
        PostPlatformTarget::with(['socialAccount', 'post'])
            ->where('status', TargetStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get()
            ->each(function (PostPlatformTarget $target) use (&$count) {
                try {
                    $this->publishNow($target);
                    $count++;
                } catch (Throwable $e) {
                    Log::warning('Planlı yayın başlatılamadı', ['target_id' => $target->id, 'error' => $e->getMessage()]);
                }
            });

        return $count;
    }

    private function fail(PostPlatformTarget $target, PublicationJob $job, string $message, string $type = 'publish.failed'): void
    {
        $target->forceFill(['status' => TargetStatus::Failed, 'error_message' => mb_substr($message, 0, 2000)])->save();
        $job->update(['status' => 'failed', 'finished_at' => now(), 'message' => mb_substr($message, 0, 2000)]);

        $title = $target->title ?: $target->post->title ?: 'Gönderi';
        $this->notifications->notify(
            $type,
            match ($type) {
                'account.reauth_required' => "{$target->platform->label()} hesabı tekrar bağlanmalı",
                'api.quota_exceeded' => "{$target->platform->label()} API kotası doldu",
                default => "{$target->platform->label()}: yayın başarısız",
            },
            $title.' — '.$message,
            'error',
            '/posts/'.$target->post_id,
            dedupeKey: 'failed:'.$target->id.':'.$target->attempts,
            audience: 'admins',
        );

        $target->post->refreshStatus();
    }

    private function guardNotPublished(PostPlatformTarget $target): void
    {
        if ($target->status->isPublished() || $target->external_id !== null) {
            throw new RuntimeException('Bu içerik bu hesapta zaten yayınlanmış; tekrar yayın engellendi.');
        }
    }
}
