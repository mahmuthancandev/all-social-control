export function cn(...classes: Array<string | false | null | undefined>): string {
    return classes.filter(Boolean).join(' ');
}

const TZ = 'Europe/Istanbul';

export function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('tr-TR', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: TZ,
    }).format(new Date(value));
}

export function formatRelative(value: string | null | undefined): string {
    if (!value) return '—';
    const diff = (new Date(value).getTime() - Date.now()) / 1000;
    const rtf = new Intl.RelativeTimeFormat('tr-TR', { numeric: 'auto' });
    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, seconds] of units) {
        if (Math.abs(diff) >= seconds) {
            return rtf.format(Math.round(diff / seconds), unit);
        }
    }
    return 'az önce';
}

/** null değerler "veri yok" olarak gösterilir, asla 0'a çevrilmez. */
export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('tr-TR').format(value);
}

export function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toLocaleUpperCase('tr-TR'))
        .join('');
}
