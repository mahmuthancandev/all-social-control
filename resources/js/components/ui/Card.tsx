import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('rounded-xl border border-surface-700/70 bg-surface-900/80 shadow-sm shadow-black/20', className)}
            {...props}
        />
    );
}

export function CardHeader({ title, description, action }: { title: ReactNode; description?: ReactNode; action?: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4 border-b border-surface-700/70 px-5 py-4">
            <div>
                <h3 className="text-sm font-semibold text-white">{title}</h3>
                {description && <p className="mt-0.5 text-xs text-slate-400">{description}</p>}
            </div>
            {action}
        </div>
    );
}
