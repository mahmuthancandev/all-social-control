import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn, buildQuery } from '@/lib/utils';

/** 7/30/90 gün seçici. Diğer sorgu parametrelerini korur. */
export function RangeSelector({ value, url, params = {} }: { value: number; url: string; params?: Record<string, string | number | null | undefined> }) {
    return (
        <div className="inline-flex rounded-lg border border-surface-700 bg-surface-900 p-0.5" role="group" aria-label="Zaman aralığı">
            {[7, 30, 90].map((d) => (
                <button
                    key={d}
                    onClick={() => router.get(url, buildQuery({ ...params, range: d }), { preserveState: true, preserveScroll: true, replace: true })}
                    className={cn('rounded-md px-3 py-1.5 text-xs font-medium transition-colors', value === d ? 'bg-brand-500 text-white' : 'text-slate-400 hover:text-white')}
                    aria-pressed={value === d}
                >
                    {d} gün
                </button>
            ))}
        </div>
    );
}

export function FilterBar({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('mb-5 flex flex-wrap items-center gap-2', className)}>{children}</div>;
}

export function SegmentedControl<T extends string>({ value, options, onChange }: { value: T; options: Array<{ value: T; label: string }>; onChange: (v: T) => void }) {
    return (
        <div className="inline-flex rounded-lg border border-surface-700 bg-surface-900 p-0.5">
            {options.map((o) => (
                <button
                    key={o.value}
                    onClick={() => onChange(o.value)}
                    className={cn('rounded-md px-3 py-1.5 text-xs font-medium transition-colors', value === o.value ? 'bg-surface-700 text-white' : 'text-slate-400 hover:text-white')}
                    aria-pressed={value === o.value}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}
