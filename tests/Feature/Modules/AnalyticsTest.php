<?php

namespace Tests\Feature\Modules;

use App\Enums\Platform;
use App\Models\AccountMetricSnapshot;
use App\Services\Analytics\AnalyticsService;
use App\Services\Import\MetricsCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_metrics_stay_null_not_zero(): void
    {
        $account = $this->account(Platform::TikTok, ['is_demo' => false, 'connection_type' => 'manual']);
        AccountMetricSnapshot::create(['social_account_id' => $account->id, 'captured_on' => today(), 'followers' => 500, 'source' => 'manual']);

        $kpis = app(AnalyticsService::class)->kpis(collect([$account]), 7);

        $this->assertSame(500, $kpis['followers']);
        $this->assertNull($kpis['views']);
        $this->assertNull($kpis['reach']);
        $this->assertNull($kpis['engagement_rate']);

        $series = app(AnalyticsService::class)->timeSeries(collect([$account]), 'views', 7);
        $this->assertSame([null, null, null, null, null, null, null], $series['series'][0]['points']);
    }

    public function test_zero_is_kept_as_real_zero(): void
    {
        $account = $this->account(Platform::YouTube, ['is_demo' => false]);
        AccountMetricSnapshot::create(['social_account_id' => $account->id, 'captured_on' => today(), 'views' => 0, 'likes' => 0, 'source' => 'manual']);

        $kpis = app(AnalyticsService::class)->kpis(collect([$account]), 7);
        $this->assertSame(0, $kpis['views']);
    }

    public function test_followers_change_uses_history(): void
    {
        $account = $this->account(Platform::Instagram, ['is_demo' => false]);
        AccountMetricSnapshot::create(['social_account_id' => $account->id, 'captured_on' => today()->subDays(7), 'followers' => 1000]);
        AccountMetricSnapshot::create(['social_account_id' => $account->id, 'captured_on' => today()->subDay(), 'followers' => 1080]);
        AccountMetricSnapshot::create(['social_account_id' => $account->id, 'captured_on' => today(), 'followers' => 1100]);

        $kpis = app(AnalyticsService::class)->kpis(collect([$account]), 30);
        $this->assertSame(20, $kpis['followers_change_day']);
        $this->assertSame(100, $kpis['followers_change_week']);
    }

    public function test_csv_import_maps_turkish_headers_and_keeps_blanks_null(): void
    {
        $account = $this->account(Platform::X, ['is_demo' => false, 'connection_type' => 'manual']);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBFTarih;Takipçi;Görüntüleme;Beğeni\n01.10.2026;1.200;5.400;\n2026-10-02;1210;;33\nbozuk;1;2;3\n");

        $result = app(MetricsCsvImporter::class)->import($account, $path);

        $this->assertSame(2, $result['rows']);
        $this->assertSame(1, $result['skipped']);
        $first = AccountMetricSnapshot::where('social_account_id', $account->id)->whereDate('captured_on', '2026-10-01')->firstOrFail();
        $this->assertSame(1200, $first->followers);
        $this->assertSame(5400, $first->views);
        $this->assertNull($first->likes);
        $this->assertSame(1210, $account->fresh()->followers_count);
    }

    public function test_csv_export_streams_with_bom_and_empty_nulls(): void
    {
        $account = $this->account(Platform::TikTok, ['is_demo' => false]);
        AccountMetricSnapshot::create(['social_account_id' => $account->id, 'captured_on' => today(), 'followers' => 10, 'views' => null]);

        $response = $this->actingAs($this->viewer())->get('/analytics/export/daily?range=7');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(';10;;', $csv);
    }

    public function test_manual_metric_entry_by_admin_only(): void
    {
        $account = $this->account(Platform::X, ['is_demo' => false, 'connection_type' => 'manual']);

        $this->actingAs($this->viewer())->post("/accounts/{$account->id}/metrics", ['captured_on' => today()->toDateString(), 'followers' => 5])->assertForbidden();
        $this->actingAs($this->admin())->post("/accounts/{$account->id}/metrics", ['captured_on' => today()->toDateString(), 'followers' => 5])->assertSessionHas('success');
        $this->assertSame(5, $account->fresh()->followers_count);
    }
}
