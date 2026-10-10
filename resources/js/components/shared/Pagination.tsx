import { Link } from '@inertiajs/react';
import type { Paginated } from '@/types';
import { cn } from '@/lib/utils';

export function Pagination<T>({ data, noun = 'kayıt' }: { data: Paginated<T>; noun?: string }) {
    if (data.last_page <= 1) return null;
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-surface-800 px-4 py-3 text-xs text-slate-400">
            <span>
                {data.total} {noun} · {data.from}–{data.to}
            </span>
            <div className="flex flex-wrap gap-1">
                {data.links.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={cn('rounded-md px-2.5 py-1', link.active ? 'bg-brand-500 text-white' : 'hover:bg-surface-800')}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span key={i} className="px-2.5 py-1 text-slate-600" dangerouslySetInnerHTML={{ __html: link.label }} />
                    ),
                )}
            </div>
        </div>
    );
}
