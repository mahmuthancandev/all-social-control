import type { PlatformKey } from '@/types';
import { cn } from '@/lib/utils';

const LABELS: Record<PlatformKey, string> = { youtube: 'YouTube', instagram: 'Instagram', tiktok: 'TikTok', x: 'X' };
export const PLATFORM_COLORS: Record<PlatformKey, string> = { youtube: '#d95926', instagram: '#9085e9', tiktok: '#199e70', x: '#3987e5' };

/** Platform logosu (basit, marka renginde değil; tanım için harf + şekil). */
export function PlatformIcon({ platform, className }: { platform: PlatformKey; className?: string }) {
    const common = { className: cn('size-4 shrink-0', className), viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': true } as const;
    switch (platform) {
        case 'youtube':
            return (
                <svg {...common}>
                    <path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3L10 15Z" />
                </svg>
            );
        case 'instagram':
            return (
                <svg {...common} fill="none" stroke="currentColor" strokeWidth={2}>
                    <rect x="3" y="3" width="18" height="18" rx="5" />
                    <circle cx="12" cy="12" r="4" />
                    <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none" />
                </svg>
            );
        case 'tiktok':
            return (
                <svg {...common}>
                    <path d="M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-1.8-2.5V9.7a5.7 5.7 0 1 0 4.9 5.7V9a7.4 7.4 0 0 0 4.3 1.4V7.3a4.3 4.3 0 0 1-3.2-1.5Z" />
                </svg>
            );
        case 'x':
            return (
                <svg {...common}>
                    <path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.3L5.3 21H2.2l7.2-8.3L2 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z" />
                </svg>
            );
    }
}

export function PlatformBadge({ platform, label, className }: { platform: PlatformKey; label?: string; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-xs font-medium text-slate-300', className)}>
            <span className="flex size-5 items-center justify-center rounded-md" style={{ backgroundColor: `${PLATFORM_COLORS[platform]}26`, color: PLATFORM_COLORS[platform] }}>
                <PlatformIcon platform={platform} className="size-3" />
            </span>
            {label ?? LABELS[platform]}
        </span>
    );
}

export function AccountAvatar({ platform, name, url, size = 'md' }: { platform: PlatformKey; name: string; url?: string | null; size?: 'sm' | 'md' | 'lg' }) {
    const dims = { sm: 'size-7', md: 'size-9', lg: 'size-14' }[size];
    return (
        <span className={cn('relative inline-flex shrink-0', dims)}>
            {url ? (
                <img src={url} alt="" className={cn('rounded-full object-cover', dims)} referrerPolicy="no-referrer" />
            ) : (
                <span className={cn('flex items-center justify-center rounded-full bg-surface-700 text-xs font-semibold text-white', dims)}>
                    {name.slice(0, 2).toLocaleUpperCase('tr-TR')}
                </span>
            )}
            <span
                className="absolute -right-0.5 -bottom-0.5 flex size-4 items-center justify-center rounded-full ring-2 ring-surface-900"
                style={{ backgroundColor: PLATFORM_COLORS[platform], color: '#fff' }}
            >
                <PlatformIcon platform={platform} className="size-2.5" />
            </span>
        </span>
    );
}
