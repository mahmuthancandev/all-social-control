<?php

namespace Tests\Feature\Platforms;

use App\Enums\AccountStatus;
use App\Enums\Platform;
use App\Models\AccountCredential;
use App\Models\AccountMetricSnapshot;
use App\Models\ApiQuotaUsage;
use App\Models\AppNotification;
use App\Models\ExternalPost;
use App\Services\Sync\AccountSyncService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_api_key_sync_stores_snapshots_with_nulls(): void
    {
        Settings::set('youtube_api_key', 'AIza-test');
        $account = $this->account(Platform::YouTube, ['connection_type' => 'api_key', 'is_demo' => false, 'external_id' => 'UC9']);

        Http::fake([
            'www.googleapis.com/youtube/v3/channels*' => Http::sequence()
                ->push(['items' => [['id' => 'UC9', 'snippet' => ['title' => 'Kanal'], 'statistics' => ['subscriberCount' => '900', 'videoCount' => '3', 'hiddenSubscriberCount' => false], 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU9']]]]])
                ->push(['items' => [['id' => 'UC9', 'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU9']]]]]),
            'www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [['contentDetails' => ['videoId' => 'vid1']]]]),
            'www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [[
                'id' => 'vid1', 'snippet' => ['title' => 'Video 1', 'publishedAt' => '2026-10-01T10:00:00Z'],
                'statistics' => ['viewCount' => '1234', 'commentCount' => '5'], // likeCount gizli
                'contentDetails' => ['duration' => 'PT45S'],
            ]]]),
            'www.googleapis.com/youtube/v3/commentThreads*' => Http::response(['items' => []]),
        ]);

        $result = app(AccountSyncService::class)->sync($account);

        $this->assertSame('success', $result['status'], $result['message']);
        $snapshot = AccountMetricSnapshot::where('social_account_id', $account->id)->firstOrFail();
        $this->assertSame(900, $snapshot->followers);
        $this->assertNull($snapshot->views); // API key ile günlük görüntülenme yok → NULL, 0 değil

        $post = ExternalPost::where('external_id', 'vid1')->firstOrFail();
        $this->assertSame(1234, $post->views);
        $this->assertNull($post->likes);  // gizli beğeni sayısı NULL kalır
        $this->assertNull($post->shares); // Data API paylaşım sunmaz
        $this->assertSame('youtube_short', $post->content_type->value);
        Http::assertSent(fn ($r) => $r['key'] === 'AIza-test');
    }

    public function test_revoked_token_marks_account_for_reauth_and_notifies(): void
    {
        $this->admin();
        $account = $this->account(Platform::Instagram, ['connection_type' => 'oauth', 'is_demo' => false, 'granted_scopes' => ['instagram_business_basic']]);
        AccountCredential::create(['social_account_id' => $account->id, 'access_token' => 'tok', 'expires_at' => now()->addDays(30)]);

        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);

        $result = app(AccountSyncService::class)->sync($account->load('credential'));

        $this->assertSame('failed', $result['status']);
        $this->assertSame(AccountStatus::NeedsReauth, $account->fresh()->status);
        $this->assertTrue(AppNotification::where('type', 'account.reauth_required')->exists());
    }

    public function test_youtube_quota_guard_stops_calls(): void
    {
        $this->admin();
        Settings::set('youtube_api_key', 'AIza-test');
        Settings::set('youtube_daily_quota', 100);
        $account = $this->account(Platform::YouTube, ['connection_type' => 'api_key', 'is_demo' => false]);
        ApiQuotaUsage::create(['platform' => 'youtube', 'usage_date' => now('America/Los_Angeles')->toDateString(), 'units_used' => 100]);
        Http::fake();

        $result = app(AccountSyncService::class)->sync($account);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('kota', mb_strtolower($result['message']));
        Http::assertNothingSent();
        $this->assertTrue(AppNotification::where('type', 'api.quota_exceeded')->exists());
    }

    public function test_manual_accounts_are_not_synced(): void
    {
        Http::fake();
        $account = $this->account(Platform::X, ['connection_type' => 'manual', 'is_demo' => false]);

        $this->assertSame('skipped', app(AccountSyncService::class)->sync($account)['status']);
        Http::assertNothingSent();
    }
}
