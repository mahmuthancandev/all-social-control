<?php

namespace App\Services\Analytics;

use App\Enums\Platform;
use App\Models\AccountMetricSnapshot;
use App\Models\ExternalPost;
use App\Models\Project;
use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Tarihsel ölçümlerden istatistik üretir. Veritabanından bağımsız (SQLite/MariaDB) olması için
 * gruplama PHP tarafında yapılır.
 *
 * Kurallar:
 * - Eksik/izinsiz metrik NULL kalır; toplamlarda "veri yok" ile "0" ayrılır.
 * - Takipçi (Instagram/TikTok/X) ve abone (YouTube) ayrı etiketlenir; toplamda kaynakları gösterilir.
 */
class AnalyticsService
{
    public const SUM_METRICS = ['views', 'reach', 'impressions', 'likes', 'comments', 'shares'];

    /** Koyu tema için doğrulanmış kategorik palet; sabit sırayla atanır, asla döngüye girmez. */
    public const PALETTE = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'];

    public const OTHER_COLOR = '#8b8f98';

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     * @return array<string, mixed>
     */
    public function kpis(Collection $accounts, int $days): array
    {
        $ids = $accounts->pluck('id');
        $to = CarbonImmutable::today();
        $from = $to->subDays($days - 1);
        $prevFrom = $from->subDays($days);

        $snapshots = AccountMetricSnapshot::whereIn('social_account_id', $ids)
            ->whereDate('captured_on', '>=', $prevFrom->toDateString())
            ->get();

        $current = $snapshots->filter(fn ($s) => $s->captured_on->gte($from));
        $previous = $snapshots->filter(fn ($s) => $s->captured_on->lt($from));

        $followers = $this->followersAt($snapshots, $ids, $to);
        $followersYesterday = $this->followersAt($snapshots, $ids, $to->subDay());
        $followersWeekAgo = $this->followersAt($snapshots, $ids, $to->subDays(7));
        $followersRangeStart = $this->followersAt($snapshots, $ids, $from->subDay());

        $sums = [];
        foreach (self::SUM_METRICS as $metric) {
            $sums[$metric] = $this->nullableSum($current->pluck($metric));
            $sums[$metric.'_previous'] = $this->nullableSum($previous->pluck($metric));
        }

        $engagementBase = $sums['views'];
        $engagementParts = array_filter([$sums['likes'], $sums['comments'], $sums['shares']], fn ($v) => $v !== null);
        $engagementRate = $engagementBase && $engagementParts ? round(array_sum($engagementParts) / $engagementBase * 100, 2) : null;

        $byPlatform = $accounts->groupBy(fn ($a) => $a->platform->value)->map(fn ($group, $platform) => [
            'platform' => $platform,
            'label' => Platform::from($platform)->label(),
            'follower_label' => Platform::from($platform)->followerLabel(),
            'value' => $this->nullableSum($group->pluck('followers_count')),
            'accounts' => $group->count(),
        ])->values();

        return [
            'followers' => $followers['total'],
            'followers_known_accounts' => $followers['known'],
            'followers_total_accounts' => $ids->count(),
            'followers_by_platform' => $byPlatform,
            'followers_change_day' => $this->diff($followers['total'], $followersYesterday['total']),
            'followers_change_week' => $this->diff($followers['total'], $followersWeekAgo['total']),
            'followers_change_range' => $this->diff($followers['total'], $followersRangeStart['total']),
            'engagement_rate' => $engagementRate,
            ...$sums,
        ];
    }

    /**
     * Günlük zaman serisi. groupBy: platform | account | project | total
     *
     * @param  Collection<int, SocialAccount>  $accounts
     * @return array{dates: array<int, string>, series: array<int, array<string, mixed>>}
     */
    public function timeSeries(Collection $accounts, string $metric, int $days, string $groupBy = 'platform', string $interval = 'day'): array
    {
        $to = CarbonImmutable::today();
        $from = $to->subDays($days - 1);
        $dates = collect(CarbonPeriod::create($from, $to))->map(fn ($d) => $d->toDateString())->all();

        $snapshots = AccountMetricSnapshot::whereIn('social_account_id', $accounts->pluck('id'))
            ->whereDate('captured_on', '>=', $from->subDays(14)->toDateString())
            ->orderBy('captured_on')
            ->get()
            ->groupBy('social_account_id');

        $isLevel = in_array($metric, ['followers', 'following', 'posts_count'], true);

        // Hesap başına günlük değerler. Seviye metrikleri (takipçi) eksik günlerde en fazla 7 gün ileri taşınır.
        $perAccount = [];
        foreach ($accounts as $account) {
            $rows = ($snapshots[$account->id] ?? collect())->keyBy(fn ($s) => $s->captured_on->toDateString());
            $values = [];
            $last = null;
            $lastDate = null;
            foreach ($rows as $date => $row) {
                if ($date < $from->toDateString() && $isLevel && $row->{$metric} !== null) {
                    $last = $row->{$metric};
                    $lastDate = $date;
                }
            }
            foreach ($dates as $date) {
                $v = isset($rows[$date]) ? $rows[$date]->{$metric} : null;
                if ($isLevel) {
                    if ($v !== null) {
                        $last = $v;
                        $lastDate = $date;
                    } elseif ($last !== null && $lastDate !== null && CarbonImmutable::parse($lastDate)->diffInDays(CarbonImmutable::parse($date)) <= 7) {
                        $v = $last;
                    }
                }
                $values[$date] = $v;
            }
            $perAccount[$account->id] = $values;
        }

        $groups = match ($groupBy) {
            'account' => $accounts->sortBy('id')->values()->mapWithKeys(fn ($a) => [
                'a'.$a->id => ['label' => $a->platform->label().' · @'.$a->handle, 'color' => null, 'ids' => [$a->id]],
            ]),
            'project' => $this->projectGroups($accounts),
            'total' => collect(['total' => ['label' => 'Toplam', 'color' => self::PALETTE[0], 'ids' => $accounts->pluck('id')->all()]]),
            default => $accounts->groupBy(fn ($a) => $a->platform->value)->mapWithKeys(fn ($g, $p) => [
                $p => ['label' => Platform::from($p)->label(), 'color' => Platform::from($p)->color(), 'ids' => $g->pluck('id')->all()],
            ]),
        };

        // 8'den fazla grup "Diğer" altında toplanır; renkler sabit sırayla atanır.
        if ($groups->count() > 8) {
            $kept = $groups->take(7);
            $kept['other'] = ['label' => 'Diğer', 'color' => self::OTHER_COLOR, 'ids' => $groups->skip(7)->flatMap(fn ($g) => $g['ids'])->all()];
            $groups = $kept;
        }
        $slot = 0;
        $groups = $groups->map(function ($g) use (&$slot) {
            $g['color'] ??= self::PALETTE[$slot++];

            return $g;
        });

        $series = [];
        foreach ($groups as $key => $group) {
            $points = [];
            foreach ($dates as $date) {
                $vals = collect($group['ids'])->map(fn ($id) => $perAccount[$id][$date] ?? null);
                $points[$date] = $this->nullableSum($vals);
            }
            $series[] = ['key' => (string) $key, 'label' => $group['label'], 'color' => $group['color'], 'points' => $points];
        }

        if ($interval !== 'day') {
            return $this->regroup($dates, $series, $interval, $isLevel);
        }

        return [
            'dates' => $dates,
            'series' => array_map(fn ($s) => [...$s, 'points' => array_values($s['points'])], $series),
        ];
    }

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     * @return Collection<int, array<string, mixed>>
     */
    public function topPosts(Collection $accounts, int $days, int $limit = 5, string $orderBy = 'views', ?string $kind = null): Collection
    {
        return ExternalPost::with('socialAccount')
            ->whereIn('social_account_id', $accounts->pluck('id'))
            ->where('published_at', '>=', now()->subDays($days))
            ->when($kind === 'video', fn ($q) => $q->whereIn('content_type', ['youtube_video', 'youtube_short', 'instagram_reel', 'tiktok_video']))
            ->when($kind === 'post', fn ($q) => $q->whereNotIn('content_type', ['youtube_video', 'youtube_short', 'instagram_reel', 'tiktok_video']))
            ->get()
            ->sortByDesc(fn (ExternalPost $p) => $orderBy === 'engagement' ? ($p->engagementRate() ?? -1) : ($p->{$orderBy} ?? -1))
            ->take($limit)
            ->values()
            ->map(fn (ExternalPost $p) => $p->toSummary());
    }

    /**
     * Platform bazlı karşılaştırma (aynı dönem).
     *
     * @param  Collection<int, SocialAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    public function platformComparison(Collection $accounts, int $days): array
    {
        return $accounts->groupBy(fn ($a) => $a->platform->value)->map(function (Collection $group, string $platform) use ($days) {
            $k = $this->kpis($group, $days);
            $posts = ExternalPost::whereIn('social_account_id', $group->pluck('id'))->where('published_at', '>=', now()->subDays($days))->get();

            return [
                'key' => $platform,
                'label' => Platform::from($platform)->label(),
                'color' => Platform::from($platform)->color(),
                'follower_label' => Platform::from($platform)->followerLabel(),
                'accounts' => $group->count(),
                'followers' => $k['followers'],
                'followers_change' => $k['followers_change_range'],
                'views' => $k['views'],
                'likes' => $k['likes'],
                'comments' => $k['comments'],
                'shares' => $k['shares'],
                'engagement_rate' => $k['engagement_rate'],
                'posts_published' => $posts->count(),
                'avg_views_per_post' => $this->avg($posts->pluck('views')),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function projectComparison(int $days, bool $includeDemo = true): array
    {
        $slot = 0;

        return Project::with('socialAccounts')
            ->when(! $includeDemo, fn ($q) => $q->where('is_demo', false))
            ->orderBy('id')->get()
            ->map(function (Project $project) use ($days, &$slot) {
                $accounts = $project->socialAccounts;
                $k = $this->kpis($accounts, $days);
                $posts = ExternalPost::whereIn('social_account_id', $accounts->pluck('id'))->where('published_at', '>=', now()->subDays($days))->get();

                return [
                    'key' => 'p'.$project->id,
                    'id' => $project->id,
                    'label' => $project->name,
                    'color' => self::PALETTE[$slot] ?? self::OTHER_COLOR,
                    'badge_color' => $project->color,
                    'is_demo' => $project->is_demo,
                    'accounts' => $accounts->count(),
                    'followers' => $k['followers'],
                    'followers_change' => $k['followers_change_range'],
                    'views' => $k['views'],
                    'likes' => $k['likes'],
                    'comments' => $k['comments'],
                    'shares' => $k['shares'],
                    'engagement_rate' => $k['engagement_rate'],
                    'posts_published' => $posts->count(),
                    'avg_views_per_post' => $this->avg($posts->pluck('views')),
                ] + ['_slot' => $slot++];
            })->map(fn ($r) => collect($r)->except('_slot')->all())->all();
    }

    /**
     * Geçmiş gönderilerin yayın saatine göre ortalama performansı (Europe/Istanbul).
     * Yeterli veri yoksa (min 8 gönderi) null döner — tahmin uydurulmaz.
     *
     * @param  Collection<int, SocialAccount>  $accounts
     * @return array<string, mixed>|null
     */
    public function bestPostingTimes(Collection $accounts, int $days = 180): ?array
    {
        $posts = ExternalPost::whereIn('social_account_id', $accounts->pluck('id'))
            ->where('published_at', '>=', now()->subDays($days))
            ->whereNotNull('views')
            ->get();

        if ($posts->count() < 8) {
            return null;
        }

        $tz = config('app.timezone');
        $median = $posts->pluck('views')->median() ?: 1;
        $grid = [];
        foreach ($posts as $post) {
            $at = $post->published_at->copy()->setTimezone($tz);
            $day = ($at->dayOfWeekIso - 1);
            $hour = $at->hour;
            // Hesaplar arası ölçek farkını azaltmak için görüntülenmeyi medyana göre normalize et.
            $grid[$day][$hour][] = $post->views / $median;
        }

        $cells = [];
        foreach ($grid as $day => $hours) {
            foreach ($hours as $hour => $vals) {
                $cells[] = ['day' => $day, 'hour' => $hour, 'score' => round(array_sum($vals) / count($vals), 2), 'count' => count($vals)];
            }
        }

        $hourly = collect($cells)->groupBy('hour')->map(fn ($g, $h) => [
            'hour' => (int) $h,
            'score' => round($g->sum(fn ($c) => $c['score'] * $c['count']) / max(1, $g->sum('count')), 2),
            'count' => $g->sum('count'),
        ])->sortBy('hour')->values();

        $top = collect($cells)->filter(fn ($c) => $c['count'] >= 2)->sortByDesc('score')->take(3)->values();

        return [
            'sample_size' => $posts->count(),
            'cells' => $cells,
            'hourly' => $hourly,
            'top' => $top,
        ];
    }

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     */
    private function projectGroups(Collection $accounts): Collection
    {
        $accounts->loadMissing('projects');
        $groups = [];
        foreach ($accounts as $account) {
            foreach ($account->projects as $project) {
                $groups['p'.$project->id] ??= ['label' => $project->name, 'color' => null, 'ids' => []];
                $groups['p'.$project->id]['ids'][] = $account->id;
            }
        }

        ksort($groups, SORT_NATURAL);

        return collect($groups);
    }

    /**
     * @param  array<int, string>  $dates
     * @param  array<int, array<string, mixed>>  $series
     * @return array{dates: array<int, string>, series: array<int, array<string, mixed>>}
     */
    private function regroup(array $dates, array $series, string $interval, bool $isLevel): array
    {
        $bucket = fn (string $d) => $interval === 'month'
            ? substr($d, 0, 7)
            : CarbonImmutable::parse($d)->startOfWeek()->toDateString();

        $buckets = array_values(array_unique(array_map($bucket, $dates)));
        $out = [];
        foreach ($series as $s) {
            $points = [];
            foreach ($buckets as $b) {
                $vals = collect($s['points'])->filter(fn ($v, $d) => $bucket($d) === $b);
                $points[] = $isLevel ? $vals->filter(fn ($v) => $v !== null)->last() : $this->nullableSum($vals);
            }
            $out[] = [...$s, 'points' => $points];
        }

        return ['dates' => $buckets, 'series' => $out];
    }

    /**
     * @param  Collection<int, AccountMetricSnapshot>  $snapshots
     * @param  Collection<int, int>  $ids
     * @return array{total: ?int, known: int}
     */
    private function followersAt(Collection $snapshots, Collection $ids, CarbonImmutable $date): array
    {
        $total = null;
        $known = 0;
        foreach ($ids as $id) {
            $value = $snapshots
                ->where('social_account_id', $id)
                ->filter(fn ($s) => $s->followers !== null && $s->captured_on->lte($date) && $s->captured_on->gte($date->subDays(7)))
                ->sortByDesc(fn ($s) => $s->captured_on->timestamp)
                ->first()?->followers;
            if ($value !== null) {
                $total = ($total ?? 0) + $value;
                $known++;
            }
        }

        return ['total' => $total, 'known' => $known];
    }

    private function diff(?int $current, ?int $previous): ?int
    {
        return $current === null || $previous === null ? null : $current - $previous;
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function nullableSum(Collection $values): ?int
    {
        $filtered = $values->filter(fn ($v) => $v !== null);

        return $filtered->isEmpty() ? null : (int) $filtered->sum();
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function avg(Collection $values): ?int
    {
        $filtered = $values->filter(fn ($v) => $v !== null);

        return $filtered->isEmpty() ? null : (int) round($filtered->avg());
    }
}
