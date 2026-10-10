<?php

namespace App\Services;

use App\Enums\TargetStatus;
use App\Models\ExternalPost;
use App\Models\NotificationRule;
use App\Models\PostPlatformTarget;
use App\Models\SocialAccount;

/**
 * Bildirim kurallarını değerlendirir. Her olay dedupe_key ile yalnızca bir kez bildirilir.
 */
class NotificationRuleEvaluator
{
    public function __construct(private NotificationService $notifications) {}

    public function run(): int
    {
        $sent = 0;

        foreach (NotificationRule::where('is_active', true)->get() as $rule) {
            $sent += match ($rule->type) {
                'schedule_reminder' => $this->scheduleReminder($rule),
                'views_threshold' => $this->viewsThreshold($rule),
                'followers_threshold' => $this->followersThreshold($rule),
                default => 0,
            };
        }

        return $sent;
    }

    private function scheduleReminder(NotificationRule $rule): int
    {
        $minutes = $rule->minutes_before ?? 30;
        $sent = 0;

        PostPlatformTarget::with(['post', 'socialAccount'])
            ->where('status', TargetStatus::Scheduled->value)
            ->whereBetween('scheduled_at', [now(), now()->addMinutes($minutes)])
            ->get()
            ->each(function (PostPlatformTarget $t) use (&$sent, $rule) {
                $manual = $t->publish_mode === 'manual';
                $sent += $this->notifications->notify(
                    'schedule.upcoming',
                    ($t->is_demo ? '[DEMO] ' : '')."Zamanlanmış paylaşım yaklaşıyor: {$t->platform->label()}",
                    ($t->title ?: $t->post->title ?: 'Gönderi').' — '.$t->scheduled_at->setTimezone(config('app.timezone'))->format('H:i')
                        .($manual ? ' (manuel paylaşım gerekecek; bilgisayarınızın başında olun)' : ''),
                    'info',
                    '/posts/'.$t->post_id,
                    dedupeKey: 'reminder:'.$t->id.':'.$t->scheduled_at->timestamp,
                    audience: 'admins',
                );
                $rule->forceFill(['last_triggered_at' => now()])->save();
            });

        return $sent;
    }

    private function viewsThreshold(NotificationRule $rule): int
    {
        $sent = 0;
        ExternalPost::with('socialAccount')
            ->where('views', '>=', $rule->threshold)
            ->when($rule->social_account_id, fn ($q) => $q->where('social_account_id', $rule->social_account_id))
            ->where('published_at', '>=', now()->subDays(30))
            ->get()
            ->each(function (ExternalPost $post) use (&$sent, $rule) {
                $count = $this->notifications->notify(
                    'milestone.views',
                    ($post->is_demo ? '[DEMO] ' : '').$post->socialAccount->platform->label().' gönderisi '.number_format($rule->threshold, 0, ',', '.').' görüntülenmeyi geçti',
                    ($post->title ?: 'Gönderi').' — '.number_format((int) $post->views, 0, ',', '.').' görüntülenme',
                    'success',
                    '/accounts/'.$post->social_account_id,
                    dedupeKey: 'views:'.$rule->id.':'.$post->id,
                );
                if ($count) {
                    $rule->forceFill(['last_triggered_at' => now()])->save();
                }
                $sent += $count;
            });

        return $sent;
    }

    private function followersThreshold(NotificationRule $rule): int
    {
        $account = $rule->social_account_id ? SocialAccount::find($rule->social_account_id) : null;
        if (! $account || $account->followers_count === null || $account->followers_count < $rule->threshold) {
            return 0;
        }

        $count = $this->notifications->notify(
            'milestone.followers',
            ($account->is_demo ? '[DEMO] ' : '')."@{$account->handle} {$account->platform->followerLabel()} hedefini geçti",
            number_format($rule->threshold, 0, ',', '.').' hedefi aşıldı: '.number_format($account->followers_count, 0, ',', '.'),
            'success',
            '/accounts/'.$account->id,
            dedupeKey: 'followers:'.$rule->id.':'.$rule->threshold,
        );
        if ($count) {
            $rule->forceFill(['last_triggered_at' => now()])->save();
        }

        return $count;
    }
}
