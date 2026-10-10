import { AlertTriangle, CheckCircle2, Clock, Hand, Loader2, PenLine, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';

type Tone = 'neutral' | 'brand' | 'success' | 'warning' | 'danger';

const MAP: Record<string, { tone: Tone; icon: typeof Clock }> = {
    draft: { tone: 'neutral', icon: PenLine },
    scheduled: { tone: 'brand', icon: Clock },
    queued: { tone: 'brand', icon: Loader2 },
    publishing: { tone: 'brand', icon: Loader2 },
    in_progress: { tone: 'brand', icon: Loader2 },
    published: { tone: 'success', icon: CheckCircle2 },
    manually_published: { tone: 'success', icon: Hand },
    partially_published: { tone: 'warning', icon: AlertTriangle },
    manual_required: { tone: 'warning', icon: Hand },
    failed: { tone: 'danger', icon: XCircle },
    cancelled: { tone: 'neutral', icon: XCircle },
    connected: { tone: 'success', icon: CheckCircle2 },
    needs_reauth: { tone: 'warning', icon: AlertTriangle },
    error: { tone: 'danger', icon: XCircle },
    disconnected: { tone: 'neutral', icon: XCircle },
    manual: { tone: 'neutral', icon: Hand },
};

/** Durum her zaman ikon + metinle gösterilir; yalnızca renge dayanmaz. */
export function StatusBadge({ status, label }: { status: string; label: string }) {
    const { tone, icon: Icon } = MAP[status] ?? { tone: 'neutral' as Tone, icon: Clock };
    return (
        <Badge tone={tone}>
            <Icon className={`size-3 ${status === 'publishing' || status === 'queued' || status === 'in_progress' ? 'animate-spin' : ''}`} />
            {label}
        </Badge>
    );
}
