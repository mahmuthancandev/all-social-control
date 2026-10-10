import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, Copy, Download, ExternalLink, Hand, History, Pencil, RotateCcw, Send, Trash2, XCircle, Zap } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { CopyButton } from '@/components/shared/CopyButton';
import { MediaThumb } from '@/components/shared/MediaThumb';
import { AccountAvatar, PlatformBadge } from '@/components/shared/Platform';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { FieldError, Input, Label } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { formatDateTime, toLocalInput } from '@/lib/utils';
import type { Account, MediaItem, SharedProps } from '@/types';

interface Job { id: number; mode: string; status: string; attempt: number; message: string | null; user: string | null; started_at: string | null; finished_at: string | null }
interface Target {
    id: number; platform: Account['platform']; platform_label: string; content_type: string; content_type_label: string; account: Account;
    title: string | null; caption: string | null; hashtags: string[]; full_caption: string; status: string; status_label: string; publish_mode: string;
    can_auto_publish: boolean; manual_reason: string | null; auto_note: string | null; share_url: string; scheduled_at: string | null; published_at: string | null;
    external_url: string | null; error_message: string | null; attempts: number; media: MediaItem | null; thumbnail: MediaItem | null; is_demo: boolean; jobs: Job[];
}
interface Props {
    post: { id: number; title: string | null; body: string | null; hashtags: string[]; status: string; status_label: string; scheduled_at: string | null; is_demo: boolean; creator: string | null; media: MediaItem[]; project: { id: number; name: string; color: string } | null };
    targets: Target[];
}

function MarkPublishedModal({ target, onClose }: { target: Target | null; onClose: () => void }) {
    const form = useForm({ url: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/targets/${target!.id}/mark-published`, { preserveScroll: true, onSuccess: () => { onClose(); form.reset(); } });
    };
    return (
        <Modal open={target !== null} onClose={onClose} title="Manuel yayınlandı olarak işaretle" description="Yalnızca içeriği platformda gerçekten paylaştıysanız işaretleyin."
            footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button type="submit" form="mark-form" loading={form.processing}>İşaretle</Button></>}>
            <form id="mark-form" onSubmit={submit}>
                <Label htmlFor="mp-url">Gönderi linki (isteğe bağlı, istatistik takibi için önerilir)</Label>
                <Input id="mp-url" placeholder="https://..." value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} invalid={!!form.errors.url} />
                <FieldError message={form.errors.url} />
            </form>
        </Modal>
    );
}

function RescheduleModal({ target, onClose }: { target: Target | null; onClose: () => void }) {
    const form = useForm({ scheduled_at: '' });
    useEffect(() => {
        if (target) form.setData('scheduled_at', toLocalInput(target.scheduled_at));
    }, [target]);
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/targets/${target!.id}/reschedule`, { preserveScroll: true, onSuccess: () => onClose() });
    };
    return (
        <Modal open={target !== null} onClose={onClose} title="Yayın zamanını değiştir"
            footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button type="submit" form="rs-form" loading={form.processing}>Kaydet</Button></>}>
            <form id="rs-form" onSubmit={submit}>
                <Label htmlFor="rs-at">Yeni zaman (Europe/Istanbul)</Label>
                <Input id="rs-at" type="datetime-local" value={form.data.scheduled_at} onChange={(e) => form.setData('scheduled_at', e.target.value)} invalid={!!form.errors.scheduled_at} />
                <FieldError message={form.errors.scheduled_at} />
            </form>
        </Modal>
    );
}

function ShareAssistant({ t }: { t: Target }) {
    return (
        <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
            <p className="flex items-center gap-2 text-sm font-semibold text-amber-200"><Hand className="size-4" /> Paylaşım Asistanı</p>
            {t.manual_reason && <p className="mt-1 text-xs text-slate-400">Neden otomatik değil: {t.manual_reason}</p>}
            {t.error_message && t.status === 'manual_required' && <p className="mt-1 text-xs text-slate-300">{t.error_message}</p>}
            <ol className="mt-3 space-y-2 text-sm">
                <li className="flex flex-wrap items-center gap-2"><span className="text-slate-400">1.</span> Metni kopyalayın <CopyButton text={t.full_caption} label="Açıklama + hashtag" />{t.title && <CopyButton text={t.title} label="Başlık" />}</li>
                {t.media && (
                    <li className="flex flex-wrap items-center gap-2"><span className="text-slate-400">2.</span> Medyayı indirin
                        <a href={t.media.download_url}><Button size="sm" variant="secondary"><Download className="size-3.5" /> {t.media.original_name}</Button></a>
                        {t.thumbnail && <a href={t.thumbnail.download_url}><Button size="sm" variant="secondary"><Download className="size-3.5" /> Kapak</Button></a>}
                    </li>
                )}
                <li className="flex flex-wrap items-center gap-2"><span className="text-slate-400">{t.media ? 3 : 2}.</span> Resmi paylaşım sayfasını açın
                    <a href={t.share_url} target="_blank" rel="noreferrer noopener"><Button size="sm"><ExternalLink className="size-3.5" /> {t.platform_label} aç</Button></a>
                </li>
                <li className="flex flex-wrap items-center gap-2"><span className="text-slate-400">{t.media ? 4 : 3}.</span> Paylaştıktan sonra burada <b className="text-white">Manuel yayınlandı</b> olarak işaretleyin.</li>
            </ol>
        </div>
    );
}

export default function PostShow({ post, targets }: Props) {
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [marking, setMarking] = useState<Target | null>(null);
    const [rescheduling, setRescheduling] = useState<Target | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [busy, setBusy] = useState<string | null>(null);
    const action = (url: string, key: string) => router.post(url, {}, { preserveScroll: true, onStart: () => setBusy(key), onFinish: () => setBusy(null) });

    return (
        <AppLayout
            title={post.title ?? 'Başlıksız gönderi'}
            back={{ href: '/posts', label: 'Gönderiler' }}
            badge={<>{post.is_demo && <DemoBadge />}<StatusBadge status={post.status} label={post.status_label} /></>}
            description={<>{post.project && <span className="mr-2">{post.project.name} ·</span>}{post.creator && `Oluşturan: ${post.creator}`}{post.scheduled_at && ` · Plan: ${formatDateTime(post.scheduled_at)}`}</>}
            actions={isAdmin && (
                <>
                    <Link href={`/posts/${post.id}/edit`}><Button variant="secondary"><Pencil className="size-4" /> Düzenle</Button></Link>
                    <Button variant="secondary" onClick={() => router.post(`/posts/${post.id}/duplicate`)}><Copy className="size-4" /> Kopyala</Button>
                    <Button variant="secondary" onClick={() => setDeleting(true)} aria-label="Sil"><Trash2 className="size-4" /></Button>
                </>
            )}
        >
            <div className="grid gap-4 xl:grid-cols-[1fr_340px]">
                <div className="space-y-4">
                    {targets.map((t) => (
                        <Card key={t.id}>
                            <div className="flex flex-wrap items-center gap-3 border-b border-surface-800 px-5 py-4">
                                <AccountAvatar platform={t.platform} name={t.account.display_name} url={t.account.avatar_url} />
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium text-white">@{t.account.handle} <span className="text-sm font-normal text-slate-400">· {t.content_type_label}</span></p>
                                    <p className="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                        {t.can_auto_publish ? <Badge tone="success"><Zap className="size-3" /> Otomatik yayın</Badge> : <Badge tone="warning"><Hand className="size-3" /> Manuel paylaşım</Badge>}
                                        {t.scheduled_at && <span><CalendarClock className="mr-1 inline size-3" />{formatDateTime(t.scheduled_at)}</span>}
                                        {t.published_at && <span><CheckCircle2 className="mr-1 inline size-3" />{formatDateTime(t.published_at)}</span>}
                                        {t.is_demo && <DemoBadge />}
                                    </p>
                                </div>
                                <StatusBadge status={t.status} label={t.status_label} />
                            </div>
                            <div className="grid gap-4 p-5 md:grid-cols-[140px_1fr]">
                                <div>{t.media ? <MediaThumb media={t.media} className="aspect-square" /> : <div className="flex aspect-square items-center justify-center rounded-lg bg-surface-850 text-xs text-slate-500">Medyasız</div>}</div>
                                <div className="min-w-0 space-y-3">
                                    {t.title && <p className="font-semibold text-white">{t.title}</p>}
                                    <p className="text-sm whitespace-pre-line text-slate-300">{t.full_caption || <span className="text-slate-500">Metin yok</span>}</p>
                                    {t.external_url && <a href={t.external_url} target="_blank" rel="noreferrer noopener" className="inline-flex items-center gap-1 text-sm text-brand-300 hover:underline">Platformda görüntüle <ExternalLink className="size-3.5" /></a>}
                                    {t.status === 'published' && t.error_message && <p className="rounded-lg bg-surface-850 p-2 text-xs text-slate-400">{t.error_message}</p>}
                                    {t.status === 'failed' && t.error_message && <p className="rounded-lg bg-rose-500/10 p-3 text-xs text-rose-200"><XCircle className="mr-1 inline size-3.5" />{t.error_message} ({t.attempts} deneme)</p>}
                                    {t.can_auto_publish && t.auto_note && !['published', 'manually_published'].includes(t.status) && <p className="text-xs text-slate-500">Not: {t.auto_note}</p>}
                                    {isAdmin && (t.status === 'manual_required' || (!t.can_auto_publish && ['draft', 'scheduled', 'failed'].includes(t.status))) && <ShareAssistant t={t} />}
                                </div>
                            </div>
                            {isAdmin && (
                                <div className="flex flex-wrap gap-2 border-t border-surface-800 px-5 py-3">
                                    {['draft', 'scheduled', 'cancelled'].includes(t.status) && (
                                        <Button size="sm" loading={busy === `p${t.id}`} onClick={() => action(`/targets/${t.id}/publish`, `p${t.id}`)}>
                                            <Send className="size-3.5" /> {t.can_auto_publish ? 'Şimdi yayınla' : 'Manuel paylaşıma hazırla'}
                                        </Button>
                                    )}
                                    {t.status === 'failed' && <Button size="sm" loading={busy === `r${t.id}`} onClick={() => action(`/targets/${t.id}/retry`, `r${t.id}`)}><RotateCcw className="size-3.5" /> Yeniden dene</Button>}
                                    {['manual_required', 'failed', 'draft', 'scheduled'].includes(t.status) && <Button size="sm" variant="secondary" onClick={() => setMarking(t)}><Hand className="size-3.5" /> Manuel yayınlandı</Button>}
                                    {['draft', 'scheduled', 'failed', 'manual_required', 'cancelled'].includes(t.status) && <Button size="sm" variant="secondary" onClick={() => setRescheduling(t)}><CalendarClock className="size-3.5" /> Zamanla</Button>}
                                    {['draft', 'scheduled', 'failed', 'manual_required'].includes(t.status) && <Button size="sm" variant="ghost" onClick={() => action(`/targets/${t.id}/cancel`, `c${t.id}`)}>İptal et</Button>}
                                </div>
                            )}
                            {t.jobs.length > 0 && (
                                <details className="border-t border-surface-800 px-5 py-3 text-xs">
                                    <summary className="cursor-pointer text-slate-400"><History className="mr-1 inline size-3.5" />Yayın geçmişi ({t.jobs.length})</summary>
                                    <ul className="mt-2 space-y-1.5">
                                        {t.jobs.map((j) => (
                                            <li key={j.id} className="flex flex-wrap gap-2 text-slate-400">
                                                <Badge tone={j.status === 'succeeded' ? 'success' : j.status === 'failed' ? 'danger' : j.status === 'manual' ? 'warning' : 'neutral'}>{j.mode} · {j.status}</Badge>
                                                <span>{formatDateTime(j.finished_at ?? j.started_at)}</span>
                                                {j.user && <span>· {j.user}</span>}
                                                {j.message && <span className="w-full text-slate-500">{j.message}</span>}
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </Card>
                    ))}
                </div>
                <div className="space-y-4">
                    <Card>
                        <CardHeader title="Ortak içerik" />
                        <div className="space-y-3 p-5 text-sm">
                            <p className="whitespace-pre-line text-slate-300">{post.body || <span className="text-slate-500">Açıklama yok</span>}</p>
                            {post.hashtags.length > 0 && <p className="text-brand-300">{post.hashtags.map((h) => `#${h}`).join(' ')}</p>}
                        </div>
                    </Card>
                    <Card>
                        <CardHeader title="Medya" description={`${post.media.length} dosya`} />
                        <div className="grid grid-cols-3 gap-2 p-5">
                            {post.media.map((m) => <a key={m.id} href={m.download_url} title={`${m.original_name} — indir`}><MediaThumb media={m} className="aspect-square" /></a>)}
                            {post.media.length === 0 && <p className="col-span-3 text-xs text-slate-500">Medya yok</p>}
                        </div>
                    </Card>
                    <Card className="p-5 text-xs text-slate-500">
                        <p><PlatformBadge platform="x" label="X" /> için yayın her zaman Paylaşım Asistanı ile yapılır (X API ücretli).</p>
                        <p className="mt-2">Manuel hedefler, siz işaretleyene kadar asla "yayınlandı" görünmez.</p>
                    </Card>
                </div>
            </div>
            <MarkPublishedModal target={marking} onClose={() => setMarking(null)} />
            <RescheduleModal target={rescheduling} onClose={() => setRescheduling(null)} />
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} title="Gönderiyi sil" confirmLabel="Sil" onConfirm={() => router.delete(`/posts/${post.id}`)}>
                Bu gönderi ve yayın geçmişi panelden silinecek. Platformlarda yayınlanmış içerikler etkilenmez.
            </ConfirmDialog>
        </AppLayout>
    );
}
