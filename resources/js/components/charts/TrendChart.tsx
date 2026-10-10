import { useMemo, useState } from 'react';
import { Area, AreaChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { SeriesData } from '@/types';
import { compactNumber, formatDate, formatNumber } from '@/lib/utils';
import { EmptyChart } from './EmptyChart';

interface Props {
    data: SeriesData;
    height?: number;
    area?: boolean;
    emptyText?: string;
}

/**
 * Zaman serisi: tek y ekseni, 2px çizgiler, null değerler boşluk olarak gösterilir (0 değil).
 * Birden fazla seride lejant her zaman vardır; çizgiye dokunulduğunda tooltip gösterilir.
 */
export function TrendChart({ data, height = 260, area = false, emptyText }: Props) {
    const [hidden, setHidden] = useState<string[]>([]);

    const rows = useMemo(
        () =>
            data.dates.map((date, i) => {
                const row: Record<string, string | number | null> = { date };
                data.series.forEach((s) => (row[s.key] = s.points[i] ?? null));
                return row;
            }),
        [data],
    );

    const hasData = data.series.some((s) => s.points.some((p) => p !== null));
    if (!hasData) return <EmptyChart height={height} text={emptyText} />;

    const visible = data.series.filter((s) => !hidden.includes(s.key));
    const monthly = data.dates[0]?.length === 7;
    const fmtX = (d: string) => (monthly ? formatDate(`${d}-01`, { month: 'short', year: '2-digit' }) : formatDate(d));
    const Chart = area ? AreaChart : LineChart;

    return (
        <div>
            {data.series.length > 1 && (
                <div className="mb-3 flex flex-wrap gap-x-4 gap-y-1.5" role="list" aria-label="Lejant">
                    {data.series.map((s) => {
                        const off = hidden.includes(s.key);
                        return (
                            <button
                                key={s.key}
                                role="listitem"
                                onClick={() => setHidden((h) => (off ? h.filter((k) => k !== s.key) : [...h, s.key]))}
                                className={`inline-flex items-center gap-1.5 text-xs ${off ? 'text-slate-600 line-through' : 'text-slate-300'}`}
                                aria-pressed={!off}
                            >
                                <span className="h-0.5 w-3.5 rounded-full" style={{ backgroundColor: s.color, opacity: off ? 0.3 : 1 }} />
                                {s.label}
                            </button>
                        );
                    })}
                </div>
            )}
            <div style={{ height }}>
                <ResponsiveContainer width="100%" height="100%">
                    <Chart data={rows} margin={{ top: 6, right: 8, left: -8, bottom: 0 }}>
                        <CartesianGrid stroke="#252b3d" strokeDasharray="0" vertical={false} />
                        <XAxis dataKey="date" tickFormatter={fmtX} stroke="#64748b" fontSize={11} tickLine={false} axisLine={false} minTickGap={24} />
                        <YAxis tickFormatter={(v) => compactNumber(v)} stroke="#64748b" fontSize={11} tickLine={false} axisLine={false} width={52} domain={['auto', 'auto']} />
                        <Tooltip
                            cursor={{ stroke: '#475569', strokeWidth: 1 }}
                            content={({ active, payload, label }) => {
                                if (!active || !payload?.length) return null;
                                return (
                                    <div className="rounded-lg border border-surface-700 bg-surface-900/95 px-3 py-2 text-xs shadow-xl backdrop-blur">
                                        <p className="mb-1.5 font-medium text-slate-300">{fmtX(String(label))}</p>
                                        {visible.map((s) => {
                                            const v = payload.find((p) => p.dataKey === s.key)?.value as number | null | undefined;
                                            return (
                                                <p key={s.key} className="flex items-center justify-between gap-4 text-slate-200">
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <span className="h-0.5 w-3 rounded-full" style={{ backgroundColor: s.color }} />
                                                        {s.label}
                                                    </span>
                                                    <span className="font-medium tabular-nums">{v === null || v === undefined ? 'veri yok' : formatNumber(v)}</span>
                                                </p>
                                            );
                                        })}
                                    </div>
                                );
                            }}
                        />
                        {visible.map((s) =>
                            area ? (
                                <Area key={s.key} type="monotone" dataKey={s.key} stroke={s.color} strokeWidth={2} fill={s.color} fillOpacity={0.12} dot={false} activeDot={{ r: 4, strokeWidth: 2, stroke: '#0f121b' }} connectNulls={false} isAnimationActive={false} />
                            ) : (
                                <Line key={s.key} type="monotone" dataKey={s.key} stroke={s.color} strokeWidth={2} dot={false} activeDot={{ r: 4, strokeWidth: 2, stroke: '#0f121b' }} connectNulls={false} isAnimationActive={false} />
                            ),
                        )}
                    </Chart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
