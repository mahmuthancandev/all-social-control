import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { TrendingDown, TrendingUp } from 'lucide-react';
import { Card } from '@/components/ui/Card';
import { DemoBadge } from '@/components/ui/Badge';
import { cn, compactNumber, formatNumber } from '@/lib/utils';

interface Props {
    icon: LucideIcon;
    label: string;
    value: number | null;
    display?: string;
    change?: number | null;
    changeLabel?: string;
    changeIsPercent?: boolean;
    hint?: ReactNode;
    demo?: boolean;
}

export function KpiCard({ icon: Icon, label, value, display, change, changeLabel, changeIsPercent = false, hint, demo }: Props) {
    const hasChange = change !== null && change !== undefined;
    const up = hasChange && change! > 0;
    const down = hasChange && change! < 0;

    return (
        <Card className="p-4 sm:p-5">
            <div className="flex items-start justify-between gap-2">
                <p className="text-xs font-medium text-slate-400 sm:text-sm">{label}</p>
                <div className="flex items-center gap-1.5">
                    {demo && <DemoBadge />}
                    <span className="rounded-lg bg-surface-800 p-1.5 text-slate-300">
                        <Icon className="size-4" />
                    </span>
                </div>
            </div>
            <p className="mt-2 text-2xl font-semibold tracking-tight text-white tabular-nums sm:text-3xl" title={value !== null ? formatNumber(value) : 'Veri yok'}>
                {display ?? (value === null ? <span className="text-slate-500">—</span> : compactNumber(value))}
            </p>
            <div className="mt-1.5 flex min-h-5 flex-wrap items-center gap-x-2 text-xs">
                {hasChange && (
                    <span className={cn('inline-flex items-center gap-0.5 font-medium', up ? 'text-emerald-400' : down ? 'text-rose-400' : 'text-slate-400')}>
                        {up ? <TrendingUp className="size-3.5" /> : down ? <TrendingDown className="size-3.5" /> : null}
                        {change! > 0 ? '+' : change! < 0 ? '−' : ''}
                        {changeIsPercent ? `%${Math.abs(change!).toLocaleString('tr-TR', { maximumFractionDigits: 1 })}` : compactNumber(Math.abs(change!))}
                    </span>
                )}
                {changeLabel && <span className="text-slate-500">{changeLabel}</span>}
                {hint && !hasChange && <span className="text-slate-500">{hint}</span>}
            </div>
        </Card>
    );
}
