import { Link, router, usePage } from '@inertiajs/react';
import { FileText, PenSquare, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterBar } from '@/components/shared/Filters';
import { Pagination } from '@/components/shared/Pagination';
import { PlatformIcon, PLATFORM_COLORS } from '@/components/shared/Platform';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input, Select } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';
import { buildQuery, formatDateTime, formatRelative } from '@/lib/utils';
import type { Paginated, PlatformKey, SharedProps } from '@/types';

interface PostRow {
    id: number;
    title: string | null;
    excerpt: string;
    status: string;
    status_label: string;
    scheduled_at: string | null;
    updated_at: string | null;
    is_demo: boolean;
    project: { id: number; name: string; color: string } | null;
    targets: Array<{ id: number; platform: PlatformKey; handle: string | null; status: string; status_label: string; content_type_label: string }>;
}

interface Props {
    posts: Paginated<PostRow>;
    filters: { search?: string; status?: string; project?: string; platform?: string };
    statuses: Array<{ value: string; label: string }>;
    projects: Array<{ id: number; name: string }>;
    platforms: Array<{ value: string; label: string }>;
}

const TONE: Record<string, string> = {
    published: 'ring-emerald-500/60', manually_published: 'ring-emerald-500/60', failed: 'ring-rose-500/70', manual_required: 'ring-amber-400/70',
    scheduled: 'ring-brand-400/60', queued: 'ring-brand-400/60', publishing: 'ring-brand-400/60', draft: 'ring-surface-600', cancelled: 'ring-surface-600',
};

export default function PostsIndex({ posts, filters, statuses, projects, platforms }: Props) {
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [search, setSearch] = useState(filters.search ?? '');
    const go = (patch: Record<string, string | undefined>) => router.get('/posts', buildQuery({ ...filters, ...patch }), { preserveState: true, replace: true });

    useEffect(() => {
        if (search === (filters.search ?? '')) return;
        const t = window.setTimeout(() => go({ search }), 300);
        return () => window.clearTimeout(t);
    }, [search]);

    return (
        <AppLayout title="Gönderiler" description="Tüm taslaklar, planlı ve yayınlanmış içerikler ile yayın geçmişi."
            actions={isAdmin && <Link href="/posts/create"><Button><PenSquare className="size-4" /> İçerik Oluştur</Button></Link>}>
            <FilterBar>
                <div className="relative w-full sm:w-64">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                    <Input className="h-9 pl-9" placeholder="Ara…" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
                <Select className="h-9 w-auto" value={filters.status ?? ''} onChange={(e) => go({ status: e.target.value || undefined })}>
                    <option value="">Tüm durumlar</option>
                    {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.project ?? ''} onChange={(e) => go({ project: e.target.value || undefined })}>
                    <option value="">Tüm projeler</option>
                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.platform ?? ''} onChange={(e) => go({ platform: e.target.value || undefined })}>
                    <option value="">Tüm platformlar</option>
                    {platforms.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                </Select>
            </FilterBar>

            <Card>
                {posts.data.length === 0 ? (
                    <EmptyState icon={FileText} title="Gönderi bulunamadı" description="Filtreleri değiştirin veya yeni içerik oluşturun." action={isAdmin && <Link href="/posts/create"><Button><PenSquare className="size-4" /> İçerik Oluştur</Button></Link>} />
                ) : (
                    <ul className="divide-y divide-surface-800">
                        {posts.data.map((p) => (
                            <li key={p.id}>
                                <Link href={`/posts/${p.id}`} className="flex flex-col gap-3 px-5 py-4 hover:bg-surface-850/60 sm:flex-row sm:items-center">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="truncate font-medium text-white">{p.title ?? 'Başlıksız gönderi'}</p>
                                            {p.is_demo && <DemoBadge />}
                                            {p.project && <span className="rounded-md px-2 py-0.5 text-[11px] text-white" style={{ backgroundColor: `${p.project.color}40` }}>{p.project.name}</span>}
                                        </div>
                                        {p.excerpt && <p className="mt-0.5 truncate text-sm text-slate-400">{p.excerpt}</p>}
                                        <p className="mt-1 text-xs text-slate-500">{p.scheduled_at ? `Plan: ${formatDateTime(p.scheduled_at)}` : `Güncellendi ${formatRelative(p.updated_at)}`}</p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="flex gap-1.5">
                                            {p.targets.map((t) => (
                                                <span key={t.id} title={`@${t.handle} · ${t.content_type_label} · ${t.status_label}`}
                                                    className={`flex size-7 items-center justify-center rounded-full bg-surface-800 ring-2 ${TONE[t.status] ?? 'ring-surface-600'}`} style={{ color: PLATFORM_COLORS[t.platform] }}>
                                                    <PlatformIcon platform={t.platform} className="size-3.5" />
                                                </span>
                                            ))}
                                        </div>
                                        <StatusBadge status={p.status} label={p.status_label} />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <Pagination data={posts} noun="gönderi" />
            </Card>
        </AppLayout>
    );
}
