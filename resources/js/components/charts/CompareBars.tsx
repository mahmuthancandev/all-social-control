import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { compactNumber, formatNumber } from '@/lib/utils';
import { EmptyChart } from './EmptyChart';

interface Item {
    key: string;
    label: string;
    color: string;
    value: number | null;
}

/** Yatay çubuk karşılaştırma. Tek ölçü, sıfırdan başlayan eksen, değer etiketleri metin renginde. */
export function CompareBars({ items, height, valueSuffix = '', percent = false }: { items: Item[]; height?: number; valueSuffix?: string; percent?: boolean }) {
    const data = items.filter((i) => i.value !== null);
    if (data.length === 0) return <EmptyChart height={height ?? 160} text="Karşılaştırılacak veri yok." />;
    const fmt = (v: number) => (percent ? `%${v.toLocaleString('tr-TR', { maximumFractionDigits: 2 })}` : compactNumber(v) + valueSuffix);
    const missing = items.filter((i) => i.value === null);

    return (
        <div>
            <div style={{ height: height ?? Math.max(120, data.length * 44) }}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} layout="vertical" margin={{ top: 0, right: 56, left: 0, bottom: 0 }} barCategoryGap={8}>
                        <CartesianGrid stroke="#252b3d" horizontal={false} />
                        <XAxis type="number" tickFormatter={fmt} stroke="#64748b" fontSize={11} tickLine={false} axisLine={false} />
                        <YAxis type="category" dataKey="label" width={110} stroke="#94a3b8" fontSize={12} tickLine={false} axisLine={false} />
                        <Tooltip
                            cursor={{ fill: '#ffffff08' }}
                            content={({ active, payload }) =>
                                active && payload?.length ? (
                                    <div className="rounded-lg border border-surface-700 bg-surface-900/95 px-3 py-2 text-xs text-slate-200 shadow-xl">
                                        {String(payload[0].payload.label)}: <b>{percent ? fmt(Number(payload[0].value)) : formatNumber(Number(payload[0].value))}</b>
                                    </div>
                                ) : null
                            }
                        />
                        <Bar dataKey="value" radius={[0, 4, 4, 0]} maxBarSize={22} isAnimationActive={false} label={{ position: 'right', fill: '#cbd5e1', fontSize: 11, formatter: (v: unknown) => fmt(Number(v)) }}>
                            {data.map((d) => (
                                <Cell key={d.key} fill={d.color} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
            {missing.length > 0 && <p className="mt-2 text-xs text-slate-500">Veri yok: {missing.map((m) => m.label).join(', ')}</p>}
        </div>
    );
}
