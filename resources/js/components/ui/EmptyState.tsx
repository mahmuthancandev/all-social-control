import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({ icon: Icon, title, description, action }: { icon: LucideIcon; title: string; description?: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center px-6 py-14 text-center">
            <div className="mb-4 rounded-2xl border border-surface-700 bg-surface-800/60 p-3">
                <Icon className="size-6 text-slate-400" />
            </div>
            <h3 className="text-sm font-semibold text-white">{title}</h3>
            {description && <p className="mt-1 max-w-sm text-sm text-slate-400">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
