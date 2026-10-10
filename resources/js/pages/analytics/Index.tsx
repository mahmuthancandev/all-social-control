import { router } from '@inertiajs/react';
import { Download, Eye, Heart, MessageCircle, Percent, Share2, Users } from 'lucide-react';
import { TrendChart } from '@/components/charts/TrendChart';
import { BestTimes } from '@/components/charts/BestTimes';
import { FilterBar, RangeSelector, SegmentedControl } from '@/components/shared/Filters';
import { KpiCard } from '@/components/shared/KpiCard';
import { MetricValue } from '@/components/shared/NullValue';
import { PlatformBadge } from '@/components/shared/Platform';
import { DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Select } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';
import { buildQuery, formatDateTime, formatPercent, percentChange, signed } from '@/lib/utils';
import type { Account, ExternalPostSummary, Kpis, SeriesData } from '@/types';

interface Props {
    range: number; metric: string; interval: 'day' | 'week' | 'month'; group: string;
    metrics: Record<string, string>;
    filters: { project: string | null; platform: string | null; demo: string };
    projects: Array<{ id: number; name: string }>;
    platforms: Array<{ value: string; label: string }>;
    kpis: Kpis;
    series: SeriesData;
    followersSeries: SeriesData;
    coverage: Array<{ id: number; label: string; has_data: boolean; is_demo: boolean }>;
    accountsTable: Array<Account & { followers_change: number | null; views: number | null; likes: number | null; comments: number | null; shares: number | null; engagement_rate: number | null }>;
    posts: ExternalPostSummary[];
    bestTimes: Parameters<typeof BestTimes>[0]['data'];
}

export default function AnalyticsIndex(props: Props) {
    const { kpis, range, filters } = props;
    const base = { range, metric: props.metric, interval: props.interval, group: props.group, project: filters.project, platform: filters.platform, demo: filters.demo === '0' ? '0' : null };
    const go = (patch: Record<string, string | number | null>) => router.get('/analytics', buildQuery({ ...base, ...patch }), { preserveState: true, preserveScroll: true, replace: true });
    const exportQuery = new URLSearchParams(buildQuery({ range, project: filters.project, platform: filters.platform, demo: filters.demo === '0' ? '0' : null }) as Record<string, string>).toString();
    const missing = props.coverage.filter((c) => !c.has_data);

    return (
        <AppLayout title="İstatistikler" description="Kaydedilen geçmiş ölçümlere dayalı performans analizi."
            actions={
                <>
                    <a href={`/analytics/export/daily?${exportQuery}`}><Button variant="secondary"><Download className="size-4" /> Günlük CSV</Button></a>
                    <a href={`/analytics/export/posts?${exportQuery}`}><Button variant="secondary"><Download className="size-4" /> Gönderi CSV</Button></a>
                </>
            }>
            <FilterBar>
                <RangeSelector value={range} url="/analytics" params={{ ...base, range: undefined }} />
                <SegmentedControl value={props.interval} onChange={(v) => go({ interval: v })} options={[{ value: 'day', label: 'Günlük' }, { value: 'week', label: 'Haftalık' }, { value: 'month', label: 'Aylık' }]} />
                <Select className="h-9 w-auto" value={filters.project ?? ''} onChange={(e) => go({ project: e.target.value || null })}>
                    <option value="">Tüm projeler</option>{props.projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.platform ?? ''} onChange={(e) => go({ platform: e.target.value || null })}>
                    <option value="">Tüm platformlar</option>{props.platforms.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.demo} onChange={(e) => go({ demo: e.target.value === '0' ? '0' : null })}>
                    <option value="1">Demo dahil</option><option value="0">Yalnızca gerçek</option>
                </Select>
            </FilterBar>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
                <KpiCard icon={Users} label="Takipçi değişimi" value={kpis.followers_change_range} display={kpis.followers_change_range === null ? undefined : signed(kpis.followers_change_range, true)} hint={`son ${range} gün`} />
                <KpiCard icon={Eye} label="Görüntülenme" value={kpis.views} change={percentChange(kpis.views, kpis.views_previous)} changeIsPercent changeLabel="önceki döneme göre" />
                <KpiCard icon={Heart} label="Beğeni" value={kpis.likes} change={percentChange(kpis.likes, kpis.likes_previous)} changeIsPercent />
                <KpiCard icon={MessageCircle} label="Yorum" value={kpis.comments} change={percentChange(kpis.comments, kpis.comments_previous)} changeIsPercent />
                <KpiCard icon={Share2} label="Paylaşım" value={kpis.shares} change={percentChange(kpis.shares, kpis.shares_previous)} changeIsPercent />
                <KpiCard icon={Percent} label="Etkileşim oranı" value={kpis.engagement_rate} display={kpis.engagement_rate === null ? undefined : formatPercent(kpis.engagement_rate)} />
            </div>

            <Card className="mt-4">
                <CardHeader title="Trend" description="Metrik ve gruplama seçin"
                    action={
                        <div className="flex flex-wrap gap-2">
                            <Select className="h-8 w-auto text-xs" value={props.metric} onChange={(e) => go({ metric: e.target.value })}>{Object.entries(props.metrics).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select>
                            <Select className="h-8 w-auto text-xs" value={props.group} onChange={(e) => go({ group: e.target.value })}>
                                <option value="platform">Platforma göre</option><option value="account">Hesaba göre</option><option value="project">Projeye göre</option><option value="total">Toplam</option>
                            </Select>
                        </div>
                    } />
                <div className="p-5">
                    <TrendChart data={props.series} height={300} />
                    {missing.length > 0 && <p className="mt-3 text-xs text-slate-500">"{props.metrics[props.metric]}" verisi olmayan hesaplar (grafikte yok, 0 sayılmadı): {missing.map((m) => m.label).join(', ')}</p>}
                </div>
            </Card>

            <div className="mt-4 grid gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader title="Takipçi artışı / azalışı" description="Platform bazlı (YouTube: abone)" />
                    <div className="p-5"><TrendChart data={props.followersSeries} /></div>
                </Card>
                <Card>
                    <CardHeader title="En iyi paylaşım saatleri" description="Son 180 günün gönderilerine göre (geçmiş veri analizi)" />
                    <div className="p-5"><BestTimes data={props.bestTimes} /></div>
                </Card>
            </div>

            <Card className="mt-4">
                <CardHeader title="Hesap performansı" description={`Son ${range} gün`} />
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-surface-800 text-left text-xs text-slate-500">
                            <th className="px-5 py-2 font-medium">Hesap</th><th className="px-3 py-2 text-right font-medium">Takipçi</th><th className="px-3 py-2 text-right font-medium">Değişim</th>
                            <th className="px-3 py-2 text-right font-medium">Görüntülenme</th><th className="px-3 py-2 text-right font-medium">Beğeni</th><th className="px-3 py-2 text-right font-medium">Yorum</th><th className="px-3 py-2 text-right font-medium">Paylaşım</th><th className="px-5 py-2 text-right font-medium">Etkileşim</th>
                        </tr></thead>
                        <tbody className="divide-y divide-surface-800">
                            {props.accountsTable.map((a) => (
                                <tr key={a.id} className="hover:bg-surface-850/50">
                                    <td className="px-5 py-2.5"><span className="inline-flex items-center gap-2"><PlatformBadge platform={a.platform} label={`@${a.handle}`} />{a.is_demo && <DemoBadge />}</span></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={a.followers_count} /></td>
                                    <td className={`px-3 py-2.5 text-right tabular-nums ${a.followers_change !== null && a.followers_change < 0 ? 'text-rose-400' : 'text-emerald-400'}`}>{signed(a.followers_change)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={a.views} /></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={a.likes} /></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={a.comments} /></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={a.shares} /></td>
                                    <td className="px-5 py-2.5 text-right tabular-nums">{formatPercent(a.engagement_rate)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card className="mt-4">
                <CardHeader title="Video ve gönderi performansı" description="İlk 25"
                    action={<SegmentedControl value={new URLSearchParams(window.location.search).get('sort') === 'engagement' ? 'engagement' : 'views'} onChange={(v) => go({ sort: v })} options={[{ value: 'views', label: 'Görüntülenme' }, { value: 'engagement', label: 'Etkileşim' }]} />} />
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead><tr className="border-b border-surface-800 text-left text-xs text-slate-500">
                            <th className="px-5 py-2 font-medium">İçerik</th><th className="px-3 py-2 font-medium">Yayın</th><th className="px-3 py-2 text-right font-medium">Görüntülenme</th><th className="px-3 py-2 text-right font-medium">Beğeni</th><th className="px-3 py-2 text-right font-medium">Yorum</th><th className="px-3 py-2 text-right font-medium">Paylaşım</th><th className="px-5 py-2 text-right font-medium">Etkileşim</th>
                        </tr></thead>
                        <tbody className="divide-y divide-surface-800">
                            {props.posts.map((p) => (
                                <tr key={p.id} className="hover:bg-surface-850/50">
                                    <td className="max-w-sm px-5 py-2.5">
                                        <p className="truncate text-white">{p.url ? <a href={p.url} target="_blank" rel="noreferrer noopener" className="hover:underline">{p.title ?? 'Başlıksız'}</a> : p.title ?? 'Başlıksız'}</p>
                                        <p className="flex items-center gap-2 text-xs text-slate-500">{p.account && <PlatformBadge platform={p.account.platform} label={`@${p.account.handle}`} />}{p.content_type_label}{p.is_demo && ' · DEMO'}</p>
                                    </td>
                                    <td className="px-3 py-2.5 text-xs whitespace-nowrap text-slate-400">{formatDateTime(p.published_at)}</td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.views} /></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.likes} /></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.comments} /></td>
                                    <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.shares} /></td>
                                    <td className="px-5 py-2.5 text-right tabular-nums">{formatPercent(p.engagement_rate)}</td>
                                </tr>
                            ))}
                            {props.posts.length === 0 && <tr><td colSpan={7} className="px-5 py-8 text-center text-sm text-slate-500">Bu dönemde gönderi yok.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AppLayout>
    );
}
