import { formatNumber, compactNumber } from '@/lib/utils';

/** NULL metrikler "veri yok" olarak gösterilir; asla 0'a çevrilmez. */
export function MetricValue({ value, compact = false, className }: { value: number | null | undefined; compact?: boolean; className?: string }) {
    if (value === null || value === undefined) {
        return (
            <span className={className ?? 'text-slate-500'} title="Veri yok: bu metrik için API izni yok veya henüz ölçülmedi.">
                —
            </span>
        );
    }
    return <span className={className}>{compact ? compactNumber(value) : formatNumber(value)}</span>;
}
