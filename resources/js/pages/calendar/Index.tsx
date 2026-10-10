import { Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, FileText, Hand, Plus } from 'lucide-react';
import { useState, type DragEvent } from 'react';
import { FilterBar, SegmentedControl } from '@/components/shared/Filters';
import { PlatformIcon, PLATFORM_COLORS } from '@/components/shared/Platform';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Select } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';
import { buildQuery, cn, dayKey, formatTime, toLocalInput } from '@/lib/utils';
import type { PlatformKey, SharedProps } from '@/types';

interface CalEvent {
    id: number; post_id: number; title: string; at: string; platform: PlatformKey; platform_label: string; handle: string; content_type_label: string;
    status: string; status_label: string; publish_mode: string; project: { name: string; color: string } | null; movable: boolean; is_demo: boolean;
}
interface Props {
    view: 'month' | 'week' | 'day';
    date: string; from: string; to: string;
    events: CalEvent[];
    drafts: Array<{ id: number; title: string; project: string | null; is_demo: boolean }>;
    filters: { project?: string; platform?: string; status?: string };
    projects: Array<{ id: number; name: string; color: string }>;
    platforms: Array<{ value: string; label: string }>;
    statuses: Array<{ value: string; label: string }>;
    timezone: string;
}

const DAYS = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
const STATUS_RING: Record<string, string> = { published: 'border-l-emerald-400', manually_published: 'border-l-emerald-400', failed: 'border-l-rose-400', manual_required: 'border-l-amber-400', scheduled: 'border-l-brand-400', draft: 'border-l-slate-500', cancelled: 'border-l-slate-600', queued: 'border-l-brand-400', publishing: 'border-l-brand-400' };

function addDays(iso: string, n: number): string {
    const d = new Date(`${iso}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() + n);
    return d.toISOString().slice(0, 10);
}
function shift(date: string, view: Props['view'], dir: 1 | -1): string {
    if (view === 'day') return addDays(date, dir);
    if (view === 'week') return addDays(date, 7 * dir);
    const d = new Date(`${date}T12:00:00Z`);
    d.setUTCDate(1);
    d.setUTCMonth(d.getUTCMonth() + dir);
    return d.toISOString().slice(0, 10);
}

function EventChip({ e, compact, isAdmin }: { e: CalEvent; compact: boolean; isAdmin: boolean }) {
    const draggable = isAdmin && e.movable;
    return (
        <Link
            href={`/posts/${e.post_id}`}
            draggable={draggable}
            onDragStart={(ev: DragEvent) => { ev.dataTransfer.setData('text/plain', JSON.stringify({ id: e.id, at: e.at })); ev.dataTransfer.effectAllowed = 'move'; }}
            title={`${e.title} · @${e.handle} · ${e.content_type_label} · ${e.status_label}`}
            className={cn('block rounded-md border-l-2 bg-surface-800/80 px-1.5 py-1 text-[11px] hover:bg-surface-700', STATUS_RING[e.status] ?? 'border-l-slate-500', draggable && 'cursor-grab active:cursor-grabbing')}
        >
            <span className="flex items-center gap-1">
                <span style={{ color: PLATFORM_COLORS[e.platform] }}><PlatformIcon platform={e.platform} className="size-3" /></span>
                <span className="text-slate-400 tabular-nums">{formatTime(e.at)}</span>
                {e.publish_mode === 'manual' && !['published', 'manually_published'].includes(e.status) && <Hand className="size-3 text-amber-300" aria-label="manuel" />}
                {e.is_demo && <span className="text-[9px] font-bold text-fuchsia-300">D</span>}
            </span>
            <span className={cn('block truncate text-slate-200', compact ? '' : 'mt-0.5')}>{e.title}</span>
            {!compact && <span className="mt-1 block"><StatusBadge status={e.status} label={e.status_label} /></span>}
        </Link>
    );
}

export default function CalendarIndex(props: Props) {
    const { view, date, events, filters } = props;
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [dragOver, setDragOver] = useState<string | null>(null);
    const go = (patch: Record<string, string | undefined>) => router.get('/calendar', buildQuery({ view, date, ...filters, ...patch }), { preserveState: true, preserveScroll: true, replace: true });

    const days: string[] = [];
    for (let d = props.from; d <= props.to; d = addDays(d, 1)) days.push(d);
    const byDay = days.reduce<Record<string, CalEvent[]>>((acc, d) => ({ ...acc, [d]: [] }), {});
    events.forEach((e) => { const k = dayKey(e.at); (byDay[k] ??= []).push(e); });

    const today = dayKey(new Date());
    const month = date.slice(0, 7);
    const title = new Intl.DateTimeFormat('tr-TR', view === 'month' ? { month: 'long', year: 'numeric' } : { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${date}T12:00:00`));

    const onDrop = (ev: DragEvent, day: string) => {
        ev.preventDefault();
        setDragOver(null);
        try {
            const { id, at } = JSON.parse(ev.dataTransfer.getData('text/plain')) as { id: number; at: string };
            const time = toLocalInput(at).slice(11) || '10:00';
            if (dayKey(at) === day) return;
            router.post(`/targets/${id}/reschedule`, { scheduled_at: `${day}T${time}` }, { preserveScroll: true, preserveState: true });
        } catch { /* geçersiz sürükleme */ }
    };

    const dayCell = (d: string, tall: boolean) => (
        <div
            key={d}
            onDragOver={(ev) => { if (isAdmin) { ev.preventDefault(); setDragOver(d); } }}
            onDragLeave={() => setDragOver(null)}
            onDrop={(ev) => onDrop(ev, d)}
            className={cn('group flex flex-col gap-1 bg-surface-900 p-1.5', tall ? 'min-h-72' : 'min-h-28', dragOver === d && 'bg-brand-500/10 ring-1 ring-brand-500 ring-inset', view === 'month' && d.slice(0, 7) !== month && 'opacity-40')}
        >
            <div className="flex items-center justify-between">
                <span className={cn('flex size-6 items-center justify-center rounded-full text-xs', d === today ? 'bg-brand-500 font-semibold text-white' : 'text-slate-400')}>{Number(d.slice(8))}</span>
                {isAdmin && d >= today && (
                    <Link href={`/posts/create?date=${d}T10:00`} className="rounded p-0.5 text-slate-500 opacity-0 group-hover:opacity-100 hover:bg-surface-800 hover:text-white" aria-label="Bu güne içerik ekle"><Plus className="size-3.5" /></Link>
                )}
            </div>
            {(byDay[d] ?? []).slice(0, tall ? undefined : 3).map((e) => <EventChip key={e.id} e={e} compact={!tall} isAdmin={isAdmin} />)}
            {!tall && (byDay[d]?.length ?? 0) > 3 && (
                <button onClick={() => go({ view: 'day', date: d })} className="rounded px-1 text-left text-[11px] text-brand-300 hover:underline">+{byDay[d].length - 3} daha</button>
            )}
        </div>
    );

    return (
        <AppLayout title="İçerik Takvimi" description={`Saat dilimi: ${props.timezone}. Planlı paylaşımlar yalnızca bilgisayar açıkken ve SocialHub çalışırken işlenir.`}
            actions={isAdmin && <Link href="/posts/create"><Button><Plus className="size-4" /> İçerik Oluştur</Button></Link>}>
            <FilterBar>
                <div className="flex items-center gap-1">
                    <Button variant="secondary" size="sm" onClick={() => go({ date: shift(date, view, -1) })} aria-label="Önceki"><ChevronLeft className="size-4" /></Button>
                    <Button variant="secondary" size="sm" onClick={() => go({ date: today })}>Bugün</Button>
                    <Button variant="secondary" size="sm" onClick={() => go({ date: shift(date, view, 1) })} aria-label="Sonraki"><ChevronRight className="size-4" /></Button>
                </div>
                <h2 className="px-2 text-base font-semibold text-white capitalize">{title}</h2>
                <SegmentedControl value={view} onChange={(v) => go({ view: v })} options={[{ value: 'month', label: 'Ay' }, { value: 'week', label: 'Hafta' }, { value: 'day', label: 'Gün' }]} />
                <div className="ml-auto flex flex-wrap gap-2">
                    <Select className="h-9 w-auto" value={filters.project ?? ''} onChange={(e) => go({ project: e.target.value || undefined })}>
                        <option value="">Tüm projeler</option>{props.projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                    </Select>
                    <Select className="h-9 w-auto" value={filters.platform ?? ''} onChange={(e) => go({ platform: e.target.value || undefined })}>
                        <option value="">Tüm platformlar</option>{props.platforms.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                    </Select>
                    <Select className="h-9 w-auto" value={filters.status ?? ''} onChange={(e) => go({ status: e.target.value || undefined })}>
                        <option value="">Tüm durumlar</option>{props.statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </Select>
                </div>
            </FilterBar>

            <div className="grid gap-4 xl:grid-cols-[1fr_260px]">
                <Card className="overflow-hidden">
                    {view === 'day' ? (
                        <div className="divide-y divide-surface-800">
                            {(byDay[date] ?? []).length === 0 && <p className="px-5 py-12 text-center text-sm text-slate-500">Bu gün için içerik yok.</p>}
                            {(byDay[date] ?? []).map((e) => (
                                <Link key={e.id} href={`/posts/${e.post_id}`} className="flex items-center gap-4 px-5 py-3 hover:bg-surface-850/60">
                                    <span className="w-12 text-sm font-medium text-slate-300 tabular-nums">{formatTime(e.at)}</span>
                                    <span style={{ color: PLATFORM_COLORS[e.platform] }}><PlatformIcon platform={e.platform} /></span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm text-white">{e.title}</p>
                                        <p className="text-xs text-slate-500">@{e.handle} · {e.content_type_label}{e.project && ` · ${e.project.name}`}</p>
                                    </div>
                                    {e.is_demo && <DemoBadge />}
                                    <StatusBadge status={e.status} label={e.status_label} />
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <div className="min-w-[720px]">
                                <div className="grid grid-cols-7 border-b border-surface-800 bg-surface-850 text-center text-xs font-medium text-slate-400">
                                    {DAYS.map((d, i) => <div key={d} className="py-2">{d}{view === 'week' && <span className="ml-1 text-slate-500">{Number(days[i]?.slice(8))}</span>}</div>)}
                                </div>
                                <div className="grid grid-cols-7 gap-px bg-surface-800">{days.map((d) => dayCell(d, view === 'week'))}</div>
                            </div>
                        </div>
                    )}
                </Card>
                <div className="space-y-4">
                    <Card>
                        <CardHeader title="Taslaklar" description="Henüz planlanmamış" />
                        {props.drafts.length === 0 ? <p className="px-5 py-6 text-center text-xs text-slate-500">Taslak yok</p> : (
                            <ul className="divide-y divide-surface-800">
                                {props.drafts.map((d) => (
                                    <li key={d.id}>
                                        <Link href={isAdmin ? `/posts/${d.id}/edit` : `/posts/${d.id}`} className="flex items-center gap-2 px-5 py-2.5 hover:bg-surface-850/60">
                                            <FileText className="size-3.5 shrink-0 text-slate-500" />
                                            <span className="min-w-0 flex-1 truncate text-sm text-slate-200">{d.title}</span>
                                            {d.is_demo && <DemoBadge />}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                    <Card className="space-y-1.5 p-4 text-xs text-slate-400">
                        <p className="font-medium text-slate-300">Gösterim</p>
                        <p><span className="mr-1.5 inline-block h-3 w-0.5 bg-brand-400 align-middle" />Planlandı</p>
                        <p><span className="mr-1.5 inline-block h-3 w-0.5 bg-emerald-400 align-middle" />Yayınlandı</p>
                        <p><span className="mr-1.5 inline-block h-3 w-0.5 bg-amber-400 align-middle" />Manuel paylaşım bekliyor</p>
                        <p><span className="mr-1.5 inline-block h-3 w-0.5 bg-rose-400 align-middle" />Başarısız</p>
                        <p><Hand className="mr-1 inline size-3 text-amber-300" />Manuel paylaşım gerekecek</p>
                        {isAdmin && <p className="pt-1 text-slate-500">İpucu: Planlı içeriği başka bir güne sürükleyerek tarihini değiştirebilirsiniz.</p>}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
