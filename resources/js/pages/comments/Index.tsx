import { router, useForm, usePage } from '@inertiajs/react';
import { CheckCheck, ExternalLink, Heart, MessageSquare, RefreshCw, Reply, Search } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { FilterBar } from '@/components/shared/Filters';
import { Pagination } from '@/components/shared/Pagination';
import { AccountAvatar, PlatformBadge } from '@/components/shared/Platform';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { FieldError, Input, Select } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';
import { buildQuery, cn, formatRelative } from '@/lib/utils';
import type { Account, Paginated, SharedProps } from '@/types';

interface CommentRow {
    id: number; body: string; author_name: string | null; author_avatar_url: string | null; like_count: number | null; commented_at: string | null;
    read_at: string | null; reply_body: string | null; replied_at: string | null; replier: string | null; is_demo: boolean;
    account: Account; post: { id: number; title: string | null; url: string | null } | null; can_reply: boolean; reply_reason: string | null;
}
interface Props {
    comments: Paginated<CommentRow>;
    filters: { account?: string; platform?: string; status?: string; search?: string; post?: string };
    accounts: Account[];
    platforms: Array<{ value: string; label: string }>;
    unreadCount: number;
    unsupported: Array<Account & { reason: string | null; comments_url: string }>;
}

function ReplyForm({ comment, onDone }: { comment: CommentRow; onDone: () => void }) {
    const form = useForm({ body: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/comments/${comment.id}/reply`, { preserveScroll: true, onSuccess: () => { form.reset(); onDone(); } });
    };
    return (
        <form onSubmit={submit} className="mt-3 space-y-2">
            <textarea rows={2} autoFocus value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} maxLength={1000} placeholder={`@${comment.author_name ?? 'kullanıcı'} adlı kişiye cevap…`}
                className="w-full rounded-lg border border-surface-700 bg-surface-900 px-3 py-2 text-sm text-slate-100 focus:border-brand-500 focus:outline-none" />
            <FieldError message={form.errors.body} />
            <div className="flex justify-end gap-2">
                <Button size="sm" variant="ghost" onClick={onDone}>Vazgeç</Button>
                <Button size="sm" type="submit" loading={form.processing} disabled={!form.data.body.trim()}>Gönder</Button>
            </div>
        </form>
    );
}

export default function CommentsIndex(props: Props) {
    const { comments, filters } = props;
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [search, setSearch] = useState(filters.search ?? '');
    const [replying, setReplying] = useState<number | null>(null);
    const go = (patch: Record<string, string | undefined>) => router.get('/comments', buildQuery({ ...filters, ...patch }), { preserveState: true, replace: true });

    useEffect(() => {
        if (search === (filters.search ?? '')) return;
        const t = window.setTimeout(() => go({ search }), 300);
        return () => window.clearTimeout(t);
    }, [search]);

    return (
        <AppLayout title="Yorumlar" description={`${props.unreadCount} okunmamış yorum · Yalnızca resmi API'nin izin verdiği platformlarda listelenir.`}
            actions={isAdmin && (
                <>
                    <Button variant="secondary" onClick={() => router.post('/comments/sync', {}, { preserveScroll: true })}><RefreshCw className="size-4" /> Yorumları çek</Button>
                    <Button variant="secondary" onClick={() => router.post('/comments/read-all', buildQuery({ account: filters.account }), { preserveScroll: true })}><CheckCheck className="size-4" /> Tümünü okundu yap</Button>
                </>
            )}>
            <FilterBar>
                <div className="relative w-full sm:w-60">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                    <Input className="h-9 pl-9" placeholder="Yorum veya kişi ara…" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
                <Select className="h-9 w-auto" value={filters.status ?? ''} onChange={(e) => go({ status: e.target.value || undefined })}>
                    <option value="">Tümü</option><option value="unread">Okunmamış</option><option value="unreplied">Cevaplanmamış</option><option value="replied">Cevaplanmış</option>
                </Select>
                <Select className="h-9 w-auto" value={filters.platform ?? ''} onChange={(e) => go({ platform: e.target.value || undefined, account: undefined })}>
                    <option value="">Tüm platformlar</option>{props.platforms.map((p) => <option key={p.value} value={p.value}>{p.label}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.account ?? ''} onChange={(e) => go({ account: e.target.value || undefined })}>
                    <option value="">Tüm hesaplar</option>{props.accounts.map((a) => <option key={a.id} value={a.id}>{a.platform_label} · @{a.handle}</option>)}
                </Select>
                {filters.post && <Button size="sm" variant="ghost" onClick={() => go({ post: undefined })}>Gönderi filtresini kaldır ×</Button>}
            </FilterBar>

            <div className="grid gap-4 xl:grid-cols-[1fr_300px]">
                <Card>
                    {comments.data.length === 0 ? <EmptyState icon={MessageSquare} title="Yorum yok" description="Filtreleri değiştirin veya yorumları çekin." /> : (
                        <ul className="divide-y divide-surface-800">
                            {comments.data.map((c) => (
                                <li key={c.id} className={cn('px-5 py-4', !c.read_at && 'bg-brand-500/[0.04]')}>
                                    <div className="flex gap-3">
                                        {c.author_avatar_url ? <img src={c.author_avatar_url} alt="" className="size-9 rounded-full" referrerPolicy="no-referrer" /> : <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface-700 text-xs text-white">{(c.author_name ?? '?').slice(0, 2).toUpperCase()}</span>}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                                <span className="font-semibold text-white">{c.author_name ?? 'Anonim'}</span>
                                                <span className="text-slate-500">{formatRelative(c.commented_at)}</span>
                                                {!c.read_at && <Badge tone="brand">yeni</Badge>}
                                                {c.is_demo && <DemoBadge />}
                                            </div>
                                            <p className="mt-1 text-sm whitespace-pre-line text-slate-200">{c.body}</p>
                                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                                <PlatformBadge platform={c.account.platform} label={`@${c.account.handle}`} />
                                                {c.post && <button onClick={() => go({ post: String(c.post!.id) })} className="max-w-60 truncate hover:text-white">↳ {c.post.title ?? 'Gönderi'}</button>}
                                                {c.like_count !== null && <span><Heart className="mr-0.5 inline size-3" />{c.like_count}</span>}
                                                {c.post?.url && <a href={c.post.url} target="_blank" rel="noreferrer noopener" className="hover:text-white"><ExternalLink className="inline size-3" /></a>}
                                            </div>
                                            {c.reply_body && (
                                                <div className="mt-3 rounded-lg border-l-2 border-brand-400 bg-surface-850 px-3 py-2 text-sm text-slate-300">
                                                    {c.reply_body}
                                                    <p className="mt-1 text-[11px] text-slate-500">{c.replier} · {formatRelative(c.replied_at)}</p>
                                                </div>
                                            )}
                                            {isAdmin && replying === c.id && <ReplyForm comment={c} onDone={() => setReplying(null)} />}
                                            {isAdmin && replying !== c.id && (
                                                <div className="mt-2 flex gap-1">
                                                    {!c.reply_body && (c.can_reply
                                                        ? <Button size="sm" variant="ghost" onClick={() => setReplying(c.id)}><Reply className="size-3.5" /> Cevapla</Button>
                                                        : <span className="px-2 py-1 text-[11px] text-slate-500" title={c.reply_reason ?? ''}>Cevap API ile desteklenmiyor</span>)}
                                                    <Button size="sm" variant="ghost" onClick={() => router.patch(`/comments/${c.id}/read`, {}, { preserveScroll: true, preserveState: true })}>{c.read_at ? 'Okunmadı yap' : 'Okundu'}</Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pagination data={comments} noun="yorum" />
                </Card>
                <Card className="self-start">
                    <CardHeader title="API ile desteklenmeyenler" description="Bu hesapların yorumlarını resmi sayfada yönetin" />
                    {props.unsupported.length === 0 ? <p className="px-5 py-4 text-xs text-slate-500">Tüm hesaplarda yorum okuma destekleniyor.</p> : (
                        <ul className="divide-y divide-surface-800">
                            {props.unsupported.map((a) => (
                                <li key={a.id} className="px-5 py-3">
                                    <div className="flex items-center gap-2">
                                        <AccountAvatar platform={a.platform} name={a.display_name} url={a.avatar_url} size="sm" />
                                        <span className="min-w-0 flex-1 truncate text-sm text-white">@{a.handle}</span>
                                        <a href={a.comments_url} target="_blank" rel="noreferrer noopener"><Button size="sm" variant="secondary"><ExternalLink className="size-3.5" /> Aç</Button></a>
                                    </div>
                                    <p className="mt-1 text-[11px] text-slate-500">{a.reason}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="border-t border-surface-800 px-5 py-3 text-[11px] text-slate-500">Toplu/otomatik cevap gönderilmez; spam koruması için dakikada en fazla 10 cevap.</p>
                </Card>
            </div>
        </AppLayout>
    );
}
