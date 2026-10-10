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

export function compactNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('tr-TR', { notation: 'compact', maximumFractionDigits: 1 }).format(value);
}

export function formatPercent(value: number | null | undefined, digits = 2): string {
    if (value === null || value === undefined) return '—';
    return `%${new Intl.NumberFormat('tr-TR', { maximumFractionDigits: digits }).format(value)}`;
}

export function signed(value: number | null | undefined, compact = false): string {
    if (value === null || value === undefined) return '—';
    const text = compact ? compactNumber(Math.abs(value)) : formatNumber(Math.abs(value));
    return `${value > 0 ? '+' : value < 0 ? '−' : ''}${text}`;
}

export function percentChange(current: number | null | undefined, previous: number | null | undefined): number | null {
    if (current === null || current === undefined || previous === null || previous === undefined || previous === 0) return null;
    return ((current - previous) / previous) * 100;
}

export function formatBytes(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i++;
    }
    return `${new Intl.NumberFormat('tr-TR', { maximumFractionDigits: i === 0 ? 0 : 1 }).format(n)} ${units[i]}`;
}

export function formatDuration(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined) return '—';
    const s = Math.round(seconds);
    const m = Math.floor(s / 60);
    return `${m}:${String(s % 60).padStart(2, '0')}`;
}

export function formatDate(value: string | null | undefined, opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' }): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('tr-TR', { ...opts, timeZone: TZ }).format(new Date(value.length === 10 ? `${value}T12:00:00` : value));
}

export function formatTime(value: string | null | undefined): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('tr-TR', { hour: '2-digit', minute: '2-digit', timeZone: TZ }).format(new Date(value));
}

/** ISO zamanı Europe/Istanbul'a göre "YYYY-MM-DDTHH:mm" (datetime-local input) biçimine çevirir. */
export function toLocalInput(value: string | null | undefined): string {
    if (!value) return '';
    const parts = new Intl.DateTimeFormat('sv-SE', {
        year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', timeZone: TZ, hourCycle: 'h23',
    }).format(new Date(value));
    return parts.replace(' ', 'T');
}

/** Bir tarihin Europe/Istanbul'daki gün anahtarı (YYYY-MM-DD). */
export function dayKey(value: string | Date): string {
    return new Intl.DateTimeFormat('sv-SE', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: TZ }).format(new Date(value));
}

export function buildQuery(params: Record<string, string | number | null | undefined>): Record<string, string | number> {
    const out: Record<string, string | number> = {};
    for (const [k, v] of Object.entries(params)) {
        if (v !== null && v !== undefined && v !== '') out[k] = v;
    }
    return out;
}
