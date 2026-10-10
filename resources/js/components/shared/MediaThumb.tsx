import { Film, ImageIcon } from 'lucide-react';
import type { MediaItem } from '@/types';
import { cn, formatDuration } from '@/lib/utils';

export function MediaThumb({ media, className }: { media: MediaItem; className?: string }) {
    return (
        <div className={cn('relative overflow-hidden rounded-lg bg-surface-800', className)}>
            {media.thumbnail_url ? (
                <img src={media.thumbnail_url} alt={media.original_name} className="h-full w-full object-cover" loading="lazy" />
            ) : (
                <div className="flex h-full w-full items-center justify-center text-slate-500">
                    {media.type === 'video' ? <Film className="size-6" /> : <ImageIcon className="size-6" />}
                </div>
            )}
            <span className="absolute bottom-1 left-1 inline-flex items-center gap-1 rounded bg-black/70 px-1.5 py-0.5 text-[10px] text-white">
                {media.type === 'video' ? <Film className="size-3" /> : <ImageIcon className="size-3" />}
                {media.type === 'video' && media.duration_seconds ? formatDuration(media.duration_seconds) : media.extension.toUpperCase()}
            </span>
            {media.is_demo && <span className="absolute top-1 right-1 rounded bg-fuchsia-600/90 px-1 text-[9px] font-bold text-white">DEMO</span>}
        </div>
    );
}
