import { BarChart3 } from 'lucide-react';

export function EmptyChart({ height = 240, text }: { height?: number; text?: string }) {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-surface-700 text-center" style={{ height }}>
            <BarChart3 className="mb-2 size-5 text-slate-600" />
            <p className="max-w-xs text-xs text-slate-500">{text ?? 'Bu dönem için veri yok. Hesap bu metriği sunmuyor olabilir veya henüz senkronize edilmedi.'}</p>
        </div>
    );
}
