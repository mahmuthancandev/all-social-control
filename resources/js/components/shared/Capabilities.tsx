import { CheckCircle2, MinusCircle } from 'lucide-react';
import type { CapabilityItem } from '@/types';

/** Yetenek listesi: her özellik için açık/kapalı ve kapalıysa nedeni. */
export function CapabilityList({ items, compact = false }: { items: CapabilityItem[]; compact?: boolean }) {
    return (
        <ul className={compact ? 'grid gap-1 sm:grid-cols-2' : 'space-y-2'}>
            {items.map((c) => (
                <li key={c.key} className="flex items-start gap-2 text-xs" title={c.reason ?? undefined}>
                    {c.enabled ? <CheckCircle2 className="mt-0.5 size-3.5 shrink-0 text-emerald-400" /> : <MinusCircle className="mt-0.5 size-3.5 shrink-0 text-slate-600" />}
                    <span className="min-w-0">
                        <span className={c.enabled ? 'text-slate-200' : 'text-slate-500'}>{c.label}</span>
                        {!compact && c.reason && <span className="block text-slate-500">{c.reason}</span>}
                    </span>
                </li>
            ))}
        </ul>
    );
}
