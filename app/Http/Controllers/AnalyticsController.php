<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Models\AccountMetricSnapshot;
use App\Models\ExternalPost;
use App\Models\Project;
use App\Models\SocialAccount;
use App\Services\Analytics\AnalyticsService;
use App\Support\AccountScope;
use App\Support\Range;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public const METRICS = [
        'followers' => 'Takipçi / abone',
        'views' => 'Görüntülenme',
        'reach' => 'Erişim',
        'impressions' => 'Gösterim',
        'likes' => 'Beğeni',
        'comments' => 'Yorum',
        'shares' => 'Paylaşım',
    ];

    public function index(Request $request, AnalyticsService $analytics): Response
    {
        $days = Range::days($request, 30);
        $metric = array_key_exists((string) $request->query('metric'), self::METRICS) ? $request->query('metric') : 'views';
        $interval = in_array($request->query('interval'), ['day', 'week', 'month'], true) ? $request->query('interval') : 'day';
        $groupBy = in_array($request->query('group'), ['platform', 'account', 'project', 'total'], true) ? $request->query('group') : 'platform';
        $accounts = AccountScope::query($request)->get();

        // Metriği hiç sunmayan hesaplar (NULL) kullanıcıya açıkça gösterilir.
        $coverage = $accounts->map(function (SocialAccount $a) use ($metric, $days) {
            $has = AccountMetricSnapshot::where('social_account_id', $a->id)
                ->where('captured_on', '>=', now()->subDays($days)->toDateString())
                ->whereNotNull($metric)->exists();

            return ['id' => $a->id, 'label' => $a->platform->label().' · @'.$a->handle, 'has_data' => $has, 'is_demo' => $a->is_demo];
        });

        return Inertia::render('analytics/Index', [
            'range' => $days,
            'metric' => $metric,
            'interval' => $interval,
            'group' => $groupBy,
            'metrics' => self::METRICS,
            'filters' => ['project' => $request->query('project'), 'platform' => $request->query('platform'), 'demo' => $request->query('demo', '1')],
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::options(),
            'kpis' => $analytics->kpis($accounts, $days),
            'series' => $analytics->timeSeries($accounts, $metric, $days, $groupBy, $interval),
            'followersSeries' => $analytics->timeSeries($accounts, 'followers', $days, 'platform', $interval),
            'coverage' => $coverage,
            'accountsTable' => $accounts->map(function (SocialAccount $a) use ($analytics, $days) {
                $k = $analytics->kpis(collect([$a]), $days);

                return $a->toSummary() + [
                    'followers_change' => $k['followers_change_range'],
                    'views' => $k['views'],
                    'likes' => $k['likes'],
                    'comments' => $k['comments'],
                    'shares' => $k['shares'],
                    'engagement_rate' => $k['engagement_rate'],
                ];
            }),
            'posts' => $analytics->topPosts($accounts, $days, 25, $request->query('sort') === 'engagement' ? 'engagement' : 'views'),
            'bestTimes' => $analytics->bestPostingTimes($accounts),
        ]);
    }

    public function exportDaily(Request $request): StreamedResponse
    {
        $days = Range::days($request, 90);
        $accounts = AccountScope::query($request)->get()->keyBy('id');

        $rows = AccountMetricSnapshot::whereIn('social_account_id', $accounts->keys())
            ->where('captured_on', '>=', now()->subDays($days - 1)->toDateString())
            ->orderBy('captured_on')->orderBy('social_account_id')
            ->get()
            ->map(fn ($s) => [
                $s->captured_on->toDateString(),
                $accounts[$s->social_account_id]->platform->label(),
                '@'.$accounts[$s->social_account_id]->handle,
                $s->followers, $s->views, $s->reach, $s->impressions, $s->likes, $s->comments, $s->shares,
                $s->source,
                $s->is_demo ? 'DEMO' : '',
            ]);

        return $this->csv('socialhub-gunluk-'.now()->format('Ymd').'.csv',
            ['Tarih', 'Platform', 'Hesap', 'Takipçi/Abone', 'Görüntülenme', 'Erişim', 'Gösterim', 'Beğeni', 'Yorum', 'Paylaşım', 'Kaynak', 'Demo'],
            $rows);
    }

    public function exportPosts(Request $request): StreamedResponse
    {
        $days = Range::days($request, 90);
        $accounts = AccountScope::query($request)->get();

        $rows = ExternalPost::with('socialAccount')
            ->whereIn('social_account_id', $accounts->pluck('id'))
            ->where('published_at', '>=', now()->subDays($days))
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (ExternalPost $p) => [
                $p->published_at?->setTimezone(config('app.timezone'))->format('Y-m-d H:i'),
                $p->socialAccount->platform->label(),
                '@'.$p->socialAccount->handle,
                $p->content_type?->label(),
                $p->title,
                $p->url,
                $p->views, $p->likes, $p->comments, $p->shares,
                $p->engagementRate(),
                $p->is_demo ? 'DEMO' : '',
            ]);

        return $this->csv('socialhub-gonderiler-'.now()->format('Ymd').'.csv',
            ['Yayın zamanı', 'Platform', 'Hesap', 'Tür', 'Başlık', 'Link', 'Görüntülenme', 'Beğeni', 'Yorum', 'Paylaşım', 'Etkileşim %', 'Demo'],
            $rows);
    }

    /**
     * Excel (TR) uyumlu CSV: UTF-8 BOM + noktalı virgül. NULL değerler boş hücre olarak yazılır (0 değil).
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $v === null ? '' : (is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v), $row), ';', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
