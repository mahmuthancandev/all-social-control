<?php

namespace App\Http\Controllers;

use App\Enums\TargetStatus;
use App\Models\PostPlatformTarget;
use App\Services\Publishing\PublishingService;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Tek bir platform hedefi üzerindeki işlemler.
 */
class TargetController extends Controller
{
    public function __construct(private PublishingService $publishing) {}

    public function publish(Request $request, PostPlatformTarget $target): RedirectResponse
    {
        return $this->attempt(function () use ($request, $target) {
            $mode = $this->publishing->publishNow($target->load(['socialAccount', 'post']), $request->user());

            return $mode === 'api' ? 'Yayın kuyruğa alındı.' : 'Otomatik yayın yapılamıyor; Paylaşım Asistanı ile tamamlayın.';
        });
    }

    public function retry(Request $request, PostPlatformTarget $target): RedirectResponse
    {
        if ($target->status !== TargetStatus::Failed) {
            return back()->with('error', 'Yalnızca başarısız hedefler yeniden denenebilir.');
        }

        return $this->publish($request, $target);
    }

    public function markPublished(Request $request, PostPlatformTarget $target): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'url:https', 'max:1000'],
        ], [], ['url' => 'gönderi linki']);

        return $this->attempt(function () use ($request, $target, $data) {
            $this->publishing->markManuallyPublished($target->load(['socialAccount', 'post']), $data['url'] ?? null, $request->user());

            return 'Manuel yayınlandı olarak işaretlendi.';
        });
    }

    public function cancel(PostPlatformTarget $target): RedirectResponse
    {
        return $this->attempt(function () use ($target) {
            $this->publishing->cancel($target->load('post'));
            Audit::log('target.cancelled', $target);

            return 'Hedef iptal edildi.';
        });
    }

    public function reschedule(Request $request, PostPlatformTarget $target): RedirectResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ], ['scheduled_at.after' => 'Yeni zaman gelecekte olmalı.'], ['scheduled_at' => 'yayın zamanı']);

        if (! in_array($target->status, [TargetStatus::Draft, TargetStatus::Scheduled, TargetStatus::Failed, TargetStatus::ManualRequired, TargetStatus::Cancelled], true)) {
            return back()->with('error', 'Bu hedefin zamanı değiştirilemez ('.$target->status->label().').');
        }

        return $this->attempt(function () use ($target, $data) {
            $at = CarbonImmutable::parse($data['scheduled_at'], config('app.timezone'));
            $this->publishing->schedule($target->load(['socialAccount', 'post']), $at);
            Audit::log('target.rescheduled', $target, ['scheduled_at' => $at->toIso8601String()]);

            return 'Yayın zamanı '.$at->format('d.m.Y H:i').' olarak güncellendi.';
        });
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            return back()->with('success', $action());
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
