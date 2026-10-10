<?php

namespace Tests\Feature\Publishing;

use App\Enums\ContentType;
use App\Enums\Platform;
use App\Enums\TargetStatus;
use App\Jobs\PublishTargetJob;
use App\Models\AppNotification;
use App\Models\ExternalPost;
use App\Models\PublicationJob;
use App\Services\Publishing\PublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublishingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_capable_target_is_queued_and_published_by_job(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $target = $this->target($this->account(Platform::YouTube), ContentType::YouTubeShort, $this->media());

        $this->actingAs($admin)->post("/targets/{$target->id}/publish")->assertSessionHas('success');

        $this->assertSame(TargetStatus::Queued, $target->fresh()->status);
        Queue::assertPushed(PublishTargetJob::class, fn ($job) => $job->targetId === $target->id);

        // Kuyruk işçisinin yaptığı işi çalıştır.
        app(PublishingService::class)->execute($target->id);

        $fresh = $target->fresh();
        $this->assertSame(TargetStatus::Published, $fresh->status);
        $this->assertNotNull($fresh->external_id);
        $this->assertDatabaseHas('external_posts', ['post_platform_target_id' => $target->id, 'is_demo' => true]);
        $this->assertDatabaseHas('publication_jobs', ['post_platform_target_id' => $target->id, 'status' => 'succeeded', 'mode' => 'demo']);
        $this->assertTrue(AppNotification::where('type', 'publish.succeeded')->exists());
    }

    public function test_x_target_is_never_auto_published_and_requires_manual_flow(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $target = $this->target($this->account(Platform::X), ContentType::XPost);

        $this->actingAs($admin)->post("/targets/{$target->id}/publish");

        Queue::assertNothingPushed();
        $fresh = $target->fresh();
        $this->assertSame(TargetStatus::ManualRequired, $fresh->status);
        $this->assertSame('manual', $fresh->publish_mode);
        $this->assertNull($fresh->published_at);
    }

    public function test_real_manual_account_goes_to_manual_flow_with_reason(): void
    {
        Queue::fake();
        $account = $this->account(Platform::Instagram, ['connection_type' => 'manual', 'is_demo' => false, 'status' => 'manual']);
        $target = $this->target($account, ContentType::InstagramReel, $this->media());

        $mode = app(PublishingService::class)->publishNow($target->load('socialAccount', 'post'));

        $this->assertSame('manual', $mode);
        $this->assertSame(TargetStatus::ManualRequired, $target->fresh()->status);
        $job = PublicationJob::where('post_platform_target_id', $target->id)->first();
        $this->assertStringContainsString('Manuel hesap', $job->message);
    }

    public function test_manual_mark_published_records_link_and_external_post(): void
    {
        $admin = $this->admin();
        $target = $this->target($this->account(Platform::TikTok), ContentType::TikTokVideo, $this->media(), TargetStatus::ManualRequired);

        $this->actingAs($admin)->post("/targets/{$target->id}/mark-published", ['url' => 'https://www.tiktok.com/@x/video/7312345678901234567'])
            ->assertSessionHas('success');

        $fresh = $target->fresh();
        $this->assertSame(TargetStatus::ManuallyPublished, $fresh->status);
        $this->assertSame('https://www.tiktok.com/@x/video/7312345678901234567', $fresh->external_url);
        $this->assertTrue(ExternalPost::where('external_id', '7312345678901234567')->where('source', 'manual')->exists());
    }

    public function test_duplicate_publish_is_blocked(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $target = $this->target($this->account(Platform::YouTube), ContentType::YouTubeVideo, $this->media());
        app(PublishingService::class)->publishNow($target->load('socialAccount', 'post'));
        app(PublishingService::class)->execute($target->id);

        $this->actingAs($admin)->post("/targets/{$target->id}/publish")->assertSessionHas('error');

        // İkinci kez execute çağrılsa bile (ör. tekrar eden kuyruk işi) yeni yayın yapılmaz.
        app(PublishingService::class)->execute($target->id);
        $this->assertSame(1, PublicationJob::where('post_platform_target_id', $target->id)->where('status', 'succeeded')->count());
    }

    public function test_job_twice_in_queue_publishes_once(): void
    {
        $target = $this->target($this->account(Platform::YouTube), ContentType::YouTubeVideo, $this->media(), TargetStatus::Queued);

        app(PublishingService::class)->execute($target->id);
        app(PublishingService::class)->execute($target->id);

        $this->assertSame(1, PublicationJob::where('post_platform_target_id', $target->id)->count());
    }

    public function test_scheduled_targets_are_dispatched_when_due(): void
    {
        Queue::fake();
        $due = $this->target($this->account(Platform::YouTube), ContentType::YouTubeShort, $this->media(), TargetStatus::Scheduled);
        $due->update(['scheduled_at' => now()->subMinute()]);
        $later = $this->target($this->account(Platform::YouTube), ContentType::YouTubeShort, $this->media(), TargetStatus::Scheduled);
        $later->update(['scheduled_at' => now()->addHour()]);

        $this->artisan('socialhub:dispatch-scheduled')->assertSuccessful();

        $this->assertSame(TargetStatus::Queued, $due->fresh()->status);
        $this->assertSame(TargetStatus::Scheduled, $later->fresh()->status);
        Queue::assertPushed(PublishTargetJob::class, 1);
    }

    public function test_failed_job_marks_target_failed_and_notifies(): void
    {
        $this->admin();
        $target = $this->target($this->account(Platform::YouTube), ContentType::YouTubeVideo, $this->media(), TargetStatus::Publishing);

        (new PublishTargetJob($target->id))->failed(new \RuntimeException('HTTP 503'));

        $this->assertSame(TargetStatus::Failed, $target->fresh()->status);
        $this->assertTrue(AppNotification::where('type', 'publish.failed')->exists());
    }

    public function test_viewer_cannot_publish_or_mark(): void
    {
        $viewer = $this->viewer();
        $target = $this->target($this->account(Platform::YouTube), ContentType::YouTubeVideo, $this->media());

        $this->actingAs($viewer)->post("/targets/{$target->id}/publish")->assertForbidden();
        $this->actingAs($viewer)->post("/targets/{$target->id}/mark-published")->assertForbidden();
        $this->assertSame(TargetStatus::Draft, $target->fresh()->status);
    }

    public function test_job_has_retry_and_backoff(): void
    {
        $job = new PublishTargetJob(1);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff());
    }
}
