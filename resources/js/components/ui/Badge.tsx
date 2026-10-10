import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger' | 'demo';

const tones: Record<Tone, string> = {
    neutral: 'bg-surface-700/60 text-slate-300 ring-surface-600',
    brand: 'bg-brand-500/15 text-brand-300 ring-brand-500/30',
    success: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/30',
    warning: 'bg-amber-500/15 text-amber-300 ring-amber-500/30',
    danger: 'bg-rose-500/15 text-rose-300 ring-rose-500/30',
    demo: 'bg-fuchsia-500/15 text-fuchsia-300 ring-fuchsia-500/40 uppercase tracking-wider',
};

export function Badge({ tone = 'neutral', className, children }: { tone?: Tone; className?: string; children: ReactNode }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}

/** Demo verileri her zaman açıkça işaretlemek için kullanılır. */
export function DemoBadge() {
    return <Badge tone="demo">Demo</Badge>;
}
