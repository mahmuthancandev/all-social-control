<?php

namespace Tests\Feature\Modules;

use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\TargetStatus;
use App\Models\AppNotification;
use App\Models\Comment;
use App\Models\ExternalPost;
use App\Models\NotificationRule;
use App\Services\NotificationRuleEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    private function comment(Platform $platform): Comment
    {
        $account = $this->account($platform);

        return Comment::create(['social_account_id' => $account->id, 'external_id' => 'c'.uniqid(), 'body' => 'Harika!', 'is_demo' => true]);
    }

    public function test_reply_on_supported_platform(): void
    {
        $comment = $this->comment(Platform::YouTube);

        $this->actingAs($this->admin())->post("/comments/{$comment->id}/reply", ['body' => 'Teşekkürler!'])->assertSessionHas('success');

        $this->assertSame('Teşekkürler!', $comment->fresh()->reply_body);
        $this->assertNotNull($comment->fresh()->read_at);
    }

    public function test_reply_on_unsupported_platform_is_refused(): void
    {
        $comment = $this->comment(Platform::TikTok);

        $this->actingAs($this->admin())->post("/comments/{$comment->id}/reply", ['body' => 'Selam'])->assertSessionHas('error');
        $this->assertNull($comment->fresh()->reply_body);
    }

    public function test_cannot_reply_twice_and_spam_is_rate_limited(): void
    {
        $admin = $this->admin();
        $first = $this->comment(Platform::YouTube);
        $this->actingAs($admin)->post("/comments/{$first->id}/reply", ['body' => 'a']);
        $this->actingAs($admin)->post("/comments/{$first->id}/reply", ['body' => 'b'])->assertSessionHas('error');

        for ($i = 0; $i < 10; $i++) {
            $c = $this->comment(Platform::YouTube);
            $this->actingAs($admin)->post("/comments/{$c->id}/reply", ['body' => 'x']);
        }
        $last = $this->comment(Platform::YouTube);
        $this->actingAs($admin)->post("/comments/{$last->id}/reply", ['body' => 'x'])->assertSessionHas('error', fn ($m) => str_contains($m, 'hızlı'));
    }

    public function test_viewer_cannot_reply(): void
    {
        $comment = $this->comment(Platform::YouTube);
        $this->actingAs($this->viewer())->post("/comments/{$comment->id}/reply", ['body' => 'x'])->assertForbidden();
    }

    public function test_notification_read_state_is_per_user(): void
    {
        $a = $this->admin();
        $b = $this->admin();
        $n = AppNotification::create(['user_id' => $a->id, 'type' => 't', 'title' => 'x']);

        $this->actingAs($b)->patch("/notifications/{$n->id}/read")->assertForbidden();
        $this->actingAs($a)->patch("/notifications/{$n->id}/read");
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_rules_trigger_once(): void
    {
        $this->admin();
        $account = $this->account(Platform::YouTube, ['followers_count' => 12000]);
        NotificationRule::create(['type' => 'followers_threshold', 'social_account_id' => $account->id, 'threshold' => 10000, 'is_active' => true]);
        NotificationRule::create(['type' => 'views_threshold', 'threshold' => 1000, 'is_active' => true]);
        NotificationRule::create(['type' => 'schedule_reminder', 'minutes_before' => 30, 'is_active' => true]);
        ExternalPost::create(['social_account_id' => $account->id, 'external_id' => 'v1', 'views' => 5000, 'published_at' => now()->subDay()]);
        $target = $this->target($account, ContentType::YouTubeShort, $this->media(), TargetStatus::Scheduled);
        $target->update(['scheduled_at' => now()->addMinutes(10)]);

        $evaluator = app(NotificationRuleEvaluator::class);
        $first = $evaluator->run();
        $second = $evaluator->run();

        $this->assertSame(3, $first);
        $this->assertSame(0, $second);
        $this->assertTrue(AppNotification::where('type', 'milestone.followers')->exists());
        $this->assertTrue(AppNotification::where('type', 'schedule.upcoming')->exists());
    }
}
