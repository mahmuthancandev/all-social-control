import { router } from '@inertiajs/react';
import { CompareBars } from '@/components/charts/CompareBars';
import { TrendChart } from '@/components/charts/TrendChart';
import { FilterBar, RangeSelector } from '@/components/shared/Filters';
import { MetricValue } from '@/components/shared/NullValue';
import { DemoBadge } from '@/components/ui/Badge';
import { Card, CardHeader } from '@/components/ui/Card';
import { Select } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';
import { formatPercent, signed } from '@/lib/utils';
import type { ComparisonRow, SeriesData } from '@/types';

interface Props { range: number; demo: string; platforms: ComparisonRow[]; projects: ComparisonRow[]; platformFollowers: SeriesData; projectViews: SeriesData; projectFollowers: SeriesData }

function Table({ rows, nameLabel }: { rows: ComparisonRow[]; nameLabel: string }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-sm">
                <thead><tr className="border-b border-surface-800 text-left text-xs text-slate-500">
                    <th className="px-5 py-2 font-medium">{nameLabel}</th><th className="px-3 py-2 text-right font-medium">Hesap</th><th className="px-3 py-2 text-right font-medium">Takipçi</th><th className="px-3 py-2 text-right font-medium">Değişim</th>
                    <th className="px-3 py-2 text-right font-medium">Görüntülenme</th><th className="px-3 py-2 text-right font-medium">Yayın</th><th className="px-3 py-2 text-right font-medium">Ort. görüntülenme</th><th className="px-5 py-2 text-right font-medium">Etkileşim</th>
                </tr></thead>
                <tbody className="divide-y divide-surface-800">
                    {rows.map((r) => (
                        <tr key={r.key}>
                            <td className="px-5 py-2.5"><span className="inline-flex items-center gap-2 text-white"><span className="size-2.5 rounded-full" style={{ backgroundColor: r.color }} />{r.label}{r.is_demo && <DemoBadge />}</span></td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{r.accounts}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={r.followers} /></td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{signed(r.followers_change)}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={r.views} /></td>
                            <td className="px-3 py-2.5 text-right tabular-nums">{r.posts_published}</td>
                            <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={r.avg_views_per_post} /></td>
                            <td className="px-5 py-2.5 text-right tabular-nums">{formatPercent(r.engagement_rate)}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function CompareIndex(props: Props) {
    return (
        <AppLayout title="Karşılaştırmalar" description="Platformları ve oyun/uygulama projelerini aynı dönemde yan yana karşılaştırın. Her metrik kendi grafiğinde (tek eksen).">
            <FilterBar>
                <RangeSelector value={props.range} url="/compare" params={{ demo: props.demo === '0' ? '0' : null }} />
                <Select className="h-9 w-auto" value={props.demo} onChange={(e) => router.get('/compare', { range: props.range, ...(e.target.value === '0' ? { demo: '0' } : {}) }, { preserveState: true, replace: true })}>
                    <option value="1">Demo dahil</option><option value="0">Yalnızca gerçek</option>
                </Select>
            </FilterBar>

            <h2 className="mb-3 text-sm font-semibold tracking-wide text-slate-400 uppercase">Platform karşılaştırması</h2>
            <div className="grid gap-4 lg:grid-cols-3">
                <Card><CardHeader title="Görüntülenme" /><div className="p-5"><CompareBars items={props.platforms.map((p) => ({ key: p.key, label: p.label, color: p.color, value: p.views }))} /></div></Card>
                <Card><CardHeader title="Etkileşim oranı" /><div className="p-5"><CompareBars percent items={props.platforms.map((p) => ({ key: p.key, label: p.label, color: p.color, value: p.engagement_rate }))} /></div></Card>
                <Card><CardHeader title="Takipçi değişimi" description={`son ${props.range} gün`} /><div className="p-5"><CompareBars items={props.platforms.map((p) => ({ key: p.key, label: p.label, color: p.color, value: p.followers_change }))} /></div></Card>
            </div>
            <Card className="mt-4"><CardHeader title="Takipçi gelişimi" description="YouTube abone; diğerleri takipçi" /><div className="p-5"><TrendChart data={props.platformFollowers} /></div></Card>
            <Card className="mt-4"><Table rows={props.platforms} nameLabel="Platform" /></Card>

            <h2 className="mt-8 mb-3 text-sm font-semibold tracking-wide text-slate-400 uppercase">Oyun ve uygulama karşılaştırması</h2>
            {props.projects.length === 0 ? <Card className="p-8 text-center text-sm text-slate-500">Proje yok.</Card> : (
                <>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Card><CardHeader title="Görüntülenme trendi" /><div className="p-5"><TrendChart data={props.projectViews} area /></div></Card>
                        <Card><CardHeader title="Takipçi + abone trendi" /><div className="p-5"><TrendChart data={props.projectFollowers} /></div></Card>
                    </div>
                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <Card><CardHeader title="Görüntülenme" /><div className="p-5"><CompareBars items={props.projects.map((p) => ({ key: p.key, label: p.label, color: p.color, value: p.views }))} /></div></Card>
                        <Card><CardHeader title="Etkileşim oranı" /><div className="p-5"><CompareBars percent items={props.projects.map((p) => ({ key: p.key, label: p.label, color: p.color, value: p.engagement_rate }))} /></div></Card>
                    </div>
                    <Card className="mt-4"><Table rows={props.projects} nameLabel="Proje" /></Card>
                </>
            )}
        </AppLayout>
    );
}
