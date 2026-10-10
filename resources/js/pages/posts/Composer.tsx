import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, Check, FileText, Hand, Images, Save, Send, Sparkles, Trash2, Zap } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { HashtagInput } from '@/components/shared/HashtagInput';
import { MediaThumb } from '@/components/shared/MediaThumb';
import { AccountAvatar, PlatformBadge, PlatformIcon, PLATFORM_COLORS } from '@/components/shared/Platform';
import { UploadButton } from '@/components/shared/UploadButton';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Checkbox, FieldError, Input, Label, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { cn, toLocalInput } from '@/lib/utils';
import type { Account, MediaItem, SharedProps } from '@/types';

interface ContentTypeOption {
    value: string;
    label: string;
    platform: string;
    media_kind: 'video' | 'image' | 'any' | 'none';
    requirements: { title_max: number | null; caption_max: number | null; hashtags_max: number | null; aspect: string | null; max_duration: number | null; needs_title: boolean };
}

type ComposerAccount = Account & { project_ids: number[]; content_types: Array<{ value: string; auto: boolean; reason: string | null }> };

interface TargetState {
    id?: number;
    social_account_id: number;
    content_type: string;
    override: boolean;
    title: string;
    caption: string;
    hashtags: string[];
    media_file_id: number | null;
    thumbnail_media_id: number | null;
    settings: Record<string, unknown>;
    locked?: boolean;
    status?: string;
}

interface Props {
    projects: Array<{ id: number; name: string; color: string }>;
    accounts: ComposerAccount[];
    contentTypes: ContentTypeOption[];
    media: MediaItem[];
    timezone: string;
    post: null | {
        id: number; project_id: number | null; title: string | null; body: string | null; hashtags: string[]; media_ids: number[]; scheduled_at: string | null; is_demo: boolean;
        targets: Array<Omit<TargetState, 'override'> & { status: string; locked: boolean }>;
    };
    defaults: null | { project_id: number | null; media_ids: number[]; scheduled_at: string | null };
}

function pickMedia(kind: ContentTypeOption['media_kind'], media: MediaItem[]): number | null {
    if (kind === 'none') return null;
    const match = media.find((m) => kind === 'any' || m.type === kind);
    return match?.id ?? null;
}

function MediaPickerModal({ open, onClose, media, selected, onChange }: { open: boolean; onClose: () => void; media: MediaItem[]; selected: number[]; onChange: (ids: number[]) => void }) {
    const [filter, setFilter] = useState<'all' | 'video' | 'image'>('all');
    const list = media.filter((m) => filter === 'all' || m.type === filter);
    return (
        <Modal open={open} onClose={onClose} size="lg" title="Medya kütüphanesinden seç" footer={<Button onClick={onClose}>Tamam ({selected.length})</Button>}>
            <div className="mb-3 flex items-center gap-2">
                <Select className="h-9 w-auto" value={filter} onChange={(e) => setFilter(e.target.value as typeof filter)}>
                    <option value="all">Tümü</option><option value="video">Videolar</option><option value="image">Görseller</option>
                </Select>
                <span className="ml-auto"><UploadButton label="Yeni yükle" /></span>
            </div>
            {list.length === 0 ? <p className="py-10 text-center text-sm text-slate-500">Medya yok.</p> : (
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    {list.map((m) => {
                        const on = selected.includes(m.id);
                        return (
                            <button key={m.id} type="button" onClick={() => onChange(on ? selected.filter((x) => x !== m.id) : [...selected, m.id])}
                                className={cn('relative rounded-lg text-left ring-2 transition', on ? 'ring-brand-500' : 'ring-transparent hover:ring-surface-600')}>
                                <MediaThumb media={m} className="aspect-square" />
                                <p className="mt-1 truncate text-[11px] text-slate-400">{m.original_name}</p>
                                {on && <span className="absolute top-1 left-1 rounded-full bg-brand-500 p-0.5 text-white"><Check className="size-3" /></span>}
                            </button>
                        );
                    })}
                </div>
            )}
        </Modal>
    );
}

function Preview({ account, type, title, caption, media }: { account: ComposerAccount; type: ContentTypeOption; title: string; caption: string; media: MediaItem | null }) {
    const vertical = ['youtube_short', 'instagram_reel', 'tiktok_video'].includes(type.value);
    return (
        <div className={cn('mx-auto overflow-hidden rounded-xl border border-surface-700 bg-black', vertical ? 'max-w-[220px]' : 'max-w-sm')}>
            <div className="flex items-center gap-2 bg-surface-900 px-3 py-2">
                <AccountAvatar platform={account.platform} name={account.display_name} url={account.avatar_url} size="sm" />
                <span className="truncate text-xs font-medium text-white">@{account.handle}</span>
                <span className="ml-auto text-[10px] text-slate-500">{type.label}</span>
            </div>
            {media ? (
                <div className={cn('relative bg-surface-800', vertical ? 'aspect-[9/16]' : type.value === 'instagram_post' ? 'aspect-square' : 'aspect-video')}>
                    {media.thumbnail_url ? <img src={media.thumbnail_url} alt="" className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-xs text-slate-500">{media.type === 'video' ? 'Video önizlemesi (FFmpeg ile kapak üretilir)' : 'Görsel'}</div>}
                    {vertical && <p className="absolute right-2 bottom-2 left-2 line-clamp-3 text-[11px] text-white drop-shadow">{caption}</p>}
                </div>
            ) : type.media_kind !== 'none' ? (
                <div className="flex aspect-video items-center justify-center bg-surface-850 text-xs text-amber-300">Medya seçilmedi</div>
            ) : null}
            {!vertical && (
                <div className="bg-surface-900 px-3 py-2.5">
                    {title && <p className="text-sm font-semibold text-white">{title}</p>}
                    <p className="mt-1 line-clamp-6 text-xs whitespace-pre-line text-slate-300">{caption || <span className="text-slate-600">Açıklama yok</span>}</p>
                </div>
            )}
        </div>
    );
}

export default function Composer(props: Props) {
    const { errors } = usePage<SharedProps>().props;
    const types = useMemo(() => Object.fromEntries(props.contentTypes.map((t) => [t.value, t])), [props.contentTypes]);
    const accountsById = useMemo(() => Object.fromEntries(props.accounts.map((a) => [a.id, a])), [props.accounts]);

    const [projectId, setProjectId] = useState<number | null>(props.post?.project_id ?? props.defaults?.project_id ?? null);
    const [title, setTitle] = useState(props.post?.title ?? '');
    const [body, setBody] = useState(props.post?.body ?? '');
    const [hashtags, setHashtags] = useState<string[]>(props.post?.hashtags ?? []);
    const [mediaIds, setMediaIds] = useState<number[]>(props.post?.media_ids ?? props.defaults?.media_ids ?? []);
    const [scheduledAt, setScheduledAt] = useState(toLocalInput(props.post?.scheduled_at ?? props.defaults?.scheduled_at ?? null));
    const [targets, setTargets] = useState<TargetState[]>(() =>
        (props.post?.targets ?? []).map((t) => ({
            ...t,
            title: t.title ?? '',
            caption: t.caption ?? '',
            hashtags: t.hashtags ?? [],
            settings: (t.settings as Record<string, unknown>) ?? {},
            override: (t.caption ?? '') !== (props.post?.body ?? '') || JSON.stringify(t.hashtags ?? []) !== JSON.stringify(props.post?.hashtags ?? []) || (t.title ?? '') !== (props.post?.title ?? ''),
        })),
    );
    const [active, setActive] = useState<number | null>(targets[0]?.social_account_id ?? null);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [submitting, setSubmitting] = useState<string | null>(null);
    const [onlyProject, setOnlyProject] = useState(true);

    const selectedMedia = mediaIds.map((id) => props.media.find((m) => m.id === id)).filter(Boolean) as MediaItem[];

    // Yeni yüklenen dosyaları otomatik seç.
    const knownIds = useRef(new Set(props.media.map((m) => m.id)));
    useEffect(() => {
        const fresh = props.media.filter((m) => !knownIds.current.has(m.id)).map((m) => m.id);
        if (fresh.length) setMediaIds((ids) => Array.from(new Set([...ids, ...fresh])));
        knownIds.current = new Set(props.media.map((m) => m.id));
    }, [props.media]);

    const resolved = (t: TargetState) => ({
        title: t.override ? t.title : title,
        caption: t.override ? t.caption : body,
        hashtags: t.override ? t.hashtags : hashtags,
    });

    const toggleAccount = (a: ComposerAccount) => {
        if (targets.some((t) => t.social_account_id === a.id)) {
            const t = targets.find((x) => x.social_account_id === a.id)!;
            if (t.locked) return;
            setTargets((ts) => ts.filter((x) => x.social_account_id !== a.id));
            if (active === a.id) setActive(targets.find((x) => x.social_account_id !== a.id)?.social_account_id ?? null);
            return;
        }
        const ct = a.content_types.find((c) => c.auto) ?? a.content_types[0];
        const type = types[ct.value];
        setTargets((ts) => [...ts, {
            social_account_id: a.id,
            content_type: ct.value,
            override: false,
            title, caption: body, hashtags,
            media_file_id: pickMedia(type.media_kind, selectedMedia),
            thumbnail_media_id: null,
            settings: a.platform === 'youtube' ? { privacy: 'private', made_for_kids: false } : a.platform === 'tiktok' ? { privacy: 'SELF_ONLY' } : {},
        }]);
        setActive(a.id);
    };

    const update = (accountId: number, patch: Partial<TargetState>) => setTargets((ts) => ts.map((t) => (t.social_account_id === accountId ? { ...t, ...patch } : t)));

    const issues = (t: TargetState): string[] => {
        const type = types[t.content_type];
        const r = resolved(t);
        const out: string[] = [];
        const full = `${r.caption}${r.hashtags.length ? '\n\n' + r.hashtags.map((h) => '#' + h).join(' ') : ''}`.trim();
        if (type.requirements.needs_title && !r.title.trim()) out.push('Başlık zorunlu');
        if (type.requirements.title_max && r.title.length > type.requirements.title_max) out.push(`Başlık ${type.requirements.title_max} karakteri aşıyor`);
        if (type.requirements.caption_max && full.length > type.requirements.caption_max) out.push(`Metin ${full.length}/${type.requirements.caption_max} karakter`);
        if (type.requirements.hashtags_max && r.hashtags.length > type.requirements.hashtags_max) out.push(`En fazla ${type.requirements.hashtags_max} hashtag`);
        const media = props.media.find((m) => m.id === t.media_file_id);
        if (type.media_kind !== 'none' && !media) out.push('Medya seçilmedi');
        if (media && type.media_kind === 'video' && media.type !== 'video') out.push('Video gerekli');
        if (media && type.media_kind === 'image' && media.type !== 'image') out.push('Görsel gerekli');
        if (type.media_kind === 'none' && media) out.push('Bu tür medya içermez');
        if (media && type.requirements.max_duration && media.duration_seconds && media.duration_seconds > type.requirements.max_duration) out.push(`En fazla ${type.requirements.max_duration} sn`);
        if (t.content_type === 'x_post' && !r.caption.trim()) out.push('Metin zorunlu');
        return out;
    };

    const autoCount = targets.filter((t) => accountsById[t.social_account_id]?.content_types.find((c) => c.value === t.content_type)?.auto).length;
    const manualCount = targets.length - autoCount;
    const blocking = targets.some((t) => !t.locked && issues(t).length > 0);

    const submit = (action: 'draft' | 'schedule' | 'publish') => {
        const payload = {
            action,
            project_id: projectId,
            title: title || null,
            body: body || null,
            hashtags,
            media_ids: mediaIds,
            scheduled_at: scheduledAt || null,
            targets: targets.map((t) => ({
                social_account_id: t.social_account_id,
                content_type: t.content_type,
                ...resolved(t),
                media_file_id: t.media_file_id,
                thumbnail_media_id: t.thumbnail_media_id,
                settings: t.settings as Record<string, string | boolean>,
            })),
        };
        const opts = { preserveScroll: true, onStart: () => setSubmitting(action), onFinish: () => setSubmitting(null) };
        if (props.post) router.put(`/posts/${props.post.id}`, payload, opts);
        else router.post('/posts', payload, opts);
    };

    const visibleAccounts = props.accounts.filter((a) => !onlyProject || !projectId || a.project_ids.includes(projectId));
    const activeTarget = targets.find((t) => t.social_account_id === active) ?? null;
    const activeAccount = activeTarget ? accountsById[activeTarget.social_account_id] : null;
    const activeType = activeTarget ? types[activeTarget.content_type] : null;
    const errorList = Object.entries(errors);

    return (
        <AppLayout
            title={props.post ? 'Gönderiyi düzenle' : 'İçerik Oluştur'}
            badge={props.post?.is_demo ? <DemoBadge /> : undefined}
            back={props.post ? { href: `/posts/${props.post.id}`, label: 'Gönderi' } : undefined}
            description="Bir kez hazırlayın, her platform için ayrı düzenleyin. Otomatik yayın yalnızca resmi API izni olan hesaplarda yapılır."
        >
            {errorList.length > 0 && (
                <div className="mb-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-200">
                    <p className="mb-1 font-semibold">Kaydedilemedi:</p>
                    <ul className="list-inside list-disc space-y-0.5 text-xs">{errorList.map(([k, v]) => <li key={k}>{v}</li>)}</ul>
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-[1fr_380px]">
                <div className="space-y-4">
                    <Card>
                        <CardHeader title="1. Ortak içerik" description="Seçtiğiniz tüm platformlara varsayılan olarak uygulanır." />
                        <div className="grid gap-4 p-5 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="c-project">Proje</Label>
                                <Select id="c-project" value={projectId ?? ''} onChange={(e) => setProjectId(e.target.value ? Number(e.target.value) : null)}>
                                    <option value="">Proje yok</option>
                                    {props.projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="c-title">Başlık</Label>
                                <Input id="c-title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="ör. Yeni sezon fragmanı" maxLength={255} />
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="c-body">Açıklama</Label>
                                <textarea id="c-body" rows={5} value={body} onChange={(e) => setBody(e.target.value)} placeholder="Gönderi metni..."
                                    className="w-full rounded-lg border border-surface-700 bg-surface-900 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/40 focus:outline-none" />
                                <p className="mt-1 text-right text-[11px] text-slate-500">{body.length} karakter</p>
                            </div>
                            <div className="sm:col-span-2">
                                <Label htmlFor="c-tags">Hashtagler</Label>
                                <HashtagInput id="c-tags" value={hashtags} onChange={setHashtags} />
                            </div>
                            <div className="sm:col-span-2">
                                <div className="mb-1.5 flex items-center justify-between">
                                    <Label className="mb-0">Medya (video / görsel / kapak)</Label>
                                    <div className="flex gap-2">
                                        <Button size="sm" variant="secondary" onClick={() => setPickerOpen(true)}><Images className="size-3.5" /> Kütüphaneden seç</Button>
                                        <UploadButton projectId={projectId} label="Yükle" />
                                    </div>
                                </div>
                                {selectedMedia.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-surface-700 py-6 text-center text-xs text-slate-500">Henüz medya seçilmedi. Video veya görsel yükleyin.</p>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {selectedMedia.map((m) => (
                                            <div key={m.id} className="relative w-24">
                                                <MediaThumb media={m} className="aspect-square" />
                                                <button type="button" onClick={() => setMediaIds((ids) => ids.filter((x) => x !== m.id))} className="absolute top-1 left-1 rounded bg-black/70 p-0.5 text-white" aria-label="Kaldır"><Trash2 className="size-3" /></button>
                                                <p className="mt-0.5 truncate text-[10px] text-slate-500">{m.original_name}</p>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <CardHeader title="2. Hesap seçimi" description="Bir video birden fazla platform için seçilebilir."
                            action={projectId ? <Checkbox label="Yalnızca projenin hesapları" checked={onlyProject} onChange={(e) => setOnlyProject(e.target.checked)} /> : undefined} />
                        <div className="grid gap-2 p-5 sm:grid-cols-2 lg:grid-cols-3">
                            {visibleAccounts.map((a) => {
                                const on = targets.some((t) => t.social_account_id === a.id);
                                const anyAuto = a.content_types.some((c) => c.auto);
                                return (
                                    <button key={a.id} type="button" onClick={() => toggleAccount(a)}
                                        className={cn('flex items-center gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors', on ? 'border-brand-500/60 bg-brand-500/10' : 'border-surface-700 bg-surface-850 hover:border-surface-600')}>
                                        <AccountAvatar platform={a.platform} name={a.display_name} url={a.avatar_url} size="sm" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm text-white">@{a.handle}</p>
                                            <p className="text-[11px] text-slate-500">{anyAuto ? <span className="text-emerald-400">otomatik yayın</span> : <span className="text-amber-300">manuel paylaşım</span>}{a.is_demo && ' · demo'}</p>
                                        </div>
                                        <span className={cn('flex size-5 items-center justify-center rounded border', on ? 'border-brand-500 bg-brand-500 text-white' : 'border-surface-600')}>{on && <Check className="size-3" />}</span>
                                    </button>
                                );
                            })}
                            {visibleAccounts.length === 0 && <p className="col-span-full text-sm text-slate-500">Bu projeye bağlı hesap yok. <Link href="/accounts" className="text-brand-300">Hesap ekle</Link></p>}
                        </div>
                    </Card>

                    {targets.length > 0 && activeTarget && activeAccount && activeType && (
                        <Card>
                            <CardHeader title="3. Platforma özel düzenleme" description="Her hesap için içerik türü, metin, medya ve ayarları ayrı düzenleyin." />
                            <div className="flex gap-1 overflow-x-auto border-b border-surface-800 px-3 pt-2">
                                {targets.map((t) => {
                                    const a = accountsById[t.social_account_id];
                                    const n = t.locked ? 0 : issues(t).length;
                                    return (
                                        <button key={t.social_account_id} type="button" onClick={() => setActive(t.social_account_id)}
                                            className={cn('flex shrink-0 items-center gap-1.5 rounded-t-lg border-b-2 px-3 py-2 text-xs', active === t.social_account_id ? 'border-brand-400 text-white' : 'border-transparent text-slate-400 hover:text-white')}>
                                            <span style={{ color: PLATFORM_COLORS[a.platform] }}><PlatformIcon platform={a.platform} className="size-3.5" /></span>
                                            @{a.handle}
                                            {n > 0 && <span className="rounded-full bg-amber-500/20 px-1.5 text-[10px] text-amber-300">{n}</span>}
                                        </button>
                                    );
                                })}
                            </div>
                            <div className="space-y-4 p-5">
                                {activeTarget.locked && <p className="rounded-lg bg-surface-850 p-3 text-xs text-slate-400">Bu hedef yayınlandı veya işleniyor; değiştirilemez.</p>}
                                <fieldset disabled={activeTarget.locked} className="space-y-4">
                                    <div>
                                        <Label>İçerik türü</Label>
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {activeAccount.content_types.map((c) => {
                                                const ct = types[c.value];
                                                const on = activeTarget.content_type === c.value;
                                                return (
                                                    <button key={c.value} type="button"
                                                        onClick={() => update(activeAccount.id, { content_type: c.value, media_file_id: ct.media_kind === 'none' ? null : (props.media.find((m) => m.id === activeTarget.media_file_id && (ct.media_kind === 'any' || m.type === ct.media_kind)) ? activeTarget.media_file_id : pickMedia(ct.media_kind, selectedMedia)) })}
                                                        className={cn('rounded-lg border p-3 text-left', on ? 'border-brand-500/60 bg-brand-500/10' : 'border-surface-700 hover:border-surface-600')}>
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="text-sm text-white">{ct.label}</span>
                                                            {c.auto ? <Badge tone="success"><Zap className="size-3" /> Otomatik</Badge> : <Badge tone="warning"><Hand className="size-3" /> Manuel</Badge>}
                                                        </div>
                                                        {c.reason && <p className="mt-1 text-[11px] text-slate-500">{c.reason}</p>}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>

                                    <Checkbox label="Bu platform için metni ayrı düzenle" checked={activeTarget.override}
                                        onChange={(e) => update(activeAccount.id, e.target.checked ? { override: true, title, caption: body, hashtags } : { override: false })} />

                                    {activeTarget.override && (
                                        <div className="space-y-3 rounded-lg border border-surface-700 p-4">
                                            {(activeType.requirements.needs_title || activeType.requirements.title_max) && (
                                                <div>
                                                    <Label>Başlık <span className="text-slate-500">({activeTarget.title.length}/{activeType.requirements.title_max})</span></Label>
                                                    <Input value={activeTarget.title} onChange={(e) => update(activeAccount.id, { title: e.target.value })} />
                                                </div>
                                            )}
                                            <div>
                                                <Label>Açıklama</Label>
                                                <textarea rows={4} value={activeTarget.caption} onChange={(e) => update(activeAccount.id, { caption: e.target.value })}
                                                    className="w-full rounded-lg border border-surface-700 bg-surface-900 px-3 py-2 text-sm text-slate-100 focus:border-brand-500 focus:outline-none" />
                                            </div>
                                            <div>
                                                <Label>Hashtagler</Label>
                                                <HashtagInput value={activeTarget.hashtags} onChange={(v) => update(activeAccount.id, { hashtags: v })} />
                                            </div>
                                        </div>
                                    )}

                                    {activeType.media_kind !== 'none' && (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <Label>Medya</Label>
                                                <Select value={activeTarget.media_file_id ?? ''} onChange={(e) => update(activeAccount.id, { media_file_id: e.target.value ? Number(e.target.value) : null })}>
                                                    <option value="">Seçin</option>
                                                    {selectedMedia.filter((m) => activeType.media_kind === 'any' || m.type === activeType.media_kind).map((m) => <option key={m.id} value={m.id}>{m.original_name}</option>)}
                                                </Select>
                                            </div>
                                            {(activeAccount.platform === 'youtube' || activeTarget.content_type === 'instagram_reel') && (
                                                <div>
                                                    <Label>Kapak / küçük resim</Label>
                                                    <Select value={activeTarget.thumbnail_media_id ?? ''} onChange={(e) => update(activeAccount.id, { thumbnail_media_id: e.target.value ? Number(e.target.value) : null })}>
                                                        <option value="">Otomatik</option>
                                                        {selectedMedia.filter((m) => m.type === 'image').map((m) => <option key={m.id} value={m.id}>{m.original_name}</option>)}
                                                    </Select>
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    {activeAccount.platform === 'youtube' && (
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <Label>Görünürlük</Label>
                                                <Select value={String(activeTarget.settings.privacy ?? 'private')} onChange={(e) => update(activeAccount.id, { settings: { ...activeTarget.settings, privacy: e.target.value } })}>
                                                    <option value="private">Özel</option><option value="unlisted">Liste dışı</option><option value="public">Herkese açık</option>
                                                </Select>
                                                <p className="mt-1 text-[11px] text-slate-500">Denetlenmemiş Google projelerinde API yüklemeleri özel kalır.</p>
                                            </div>
                                            <div className="pt-7">
                                                <Checkbox label="Çocuklar için yapıldı" checked={Boolean(activeTarget.settings.made_for_kids)} onChange={(e) => update(activeAccount.id, { settings: { ...activeTarget.settings, made_for_kids: e.target.checked } })} />
                                            </div>
                                        </div>
                                    )}
                                    {activeAccount.platform === 'tiktok' && (
                                        <div>
                                            <Label>Görünürlük (doğrudan yayın modunda)</Label>
                                            <Select value={String(activeTarget.settings.privacy ?? 'SELF_ONLY')} onChange={(e) => update(activeAccount.id, { settings: { ...activeTarget.settings, privacy: e.target.value } })}>
                                                <option value="SELF_ONLY">Yalnızca ben</option><option value="MUTUAL_FOLLOW_FRIENDS">Arkadaşlar</option><option value="FOLLOWER_OF_CREATOR">Takipçiler</option><option value="PUBLIC_TO_EVERYONE">Herkes</option>
                                            </Select>
                                        </div>
                                    )}
                                </fieldset>

                                {!activeTarget.locked && issues(activeTarget).length > 0 && (
                                    <ul className="space-y-1 rounded-lg bg-amber-500/10 p-3 text-xs text-amber-200">
                                        {issues(activeTarget).map((i) => <li key={i} className="flex gap-1.5"><AlertTriangle className="size-3.5 shrink-0" />{i}</li>)}
                                    </ul>
                                )}
                                {activeType.requirements.aspect && <p className="text-xs text-slate-500">Önerilen oran: {activeType.requirements.aspect}{activeType.requirements.max_duration ? ` · en fazla ${activeType.requirements.max_duration} sn` : ''}</p>}
                            </div>
                        </Card>
                    )}
                </div>

                <div className="space-y-4 xl:sticky xl:top-20 xl:self-start">
                    <Card>
                        <CardHeader title="Önizleme" description={activeAccount ? `@${activeAccount.handle}` : 'Hesap seçin'} />
                        <div className="p-5">
                            {activeTarget && activeAccount && activeType ? (
                                <Preview account={activeAccount} type={activeType} title={resolved(activeTarget).title}
                                    caption={`${resolved(activeTarget).caption}${resolved(activeTarget).hashtags.length ? '\n\n' + resolved(activeTarget).hashtags.map((h) => '#' + h).join(' ') : ''}`}
                                    media={props.media.find((m) => m.id === activeTarget.media_file_id) ?? null} />
                            ) : (
                                <div className="flex flex-col items-center py-10 text-center text-sm text-slate-500"><Sparkles className="mb-2 size-5" />Önizleme için bir hesap seçin.</div>
                            )}
                        </div>
                    </Card>
                    <Card>
                        <CardHeader title="4. Yayın" />
                        <div className="space-y-4 p-5">
                            <div className="grid grid-cols-2 gap-2 text-center text-xs">
                                <div className="rounded-lg bg-emerald-500/10 p-2 text-emerald-200"><Zap className="mx-auto mb-1 size-4" />{autoCount} otomatik</div>
                                <div className="rounded-lg bg-amber-500/10 p-2 text-amber-200"><Hand className="mx-auto mb-1 size-4" />{manualCount} manuel</div>
                            </div>
                            {targets.map((t) => {
                                const a = accountsById[t.social_account_id];
                                return <div key={t.social_account_id} className="flex items-center justify-between text-xs"><PlatformBadge platform={a.platform} label={`@${a.handle}`} /><span className="text-slate-500">{types[t.content_type].label}</span></div>;
                            })}
                            {manualCount > 0 && <p className="text-[11px] text-slate-500">Manuel hedefler hiçbir zaman "yayınlandı" gösterilmez; Paylaşım Asistanı ile siz tamamlarsınız.</p>}
                            <div>
                                <Label htmlFor="c-at">Yayın zamanı ({props.timezone})</Label>
                                <Input id="c-at" type="datetime-local" value={scheduledAt} onChange={(e) => setScheduledAt(e.target.value)} invalid={!!errors.scheduled_at} />
                                <FieldError message={errors.scheduled_at} />
                                <p className="mt-1 text-[11px] text-slate-500">Zamanlanmış yayın yalnızca bilgisayar açık ve SocialHub çalışırken gerçekleşir.</p>
                            </div>
                            <div className="grid gap-2">
                                <Button variant="secondary" onClick={() => submit('draft')} loading={submitting === 'draft'} disabled={targets.length === 0 || submitting !== null}><Save className="size-4" /> Taslak kaydet</Button>
                                <Button variant="secondary" onClick={() => submit('schedule')} loading={submitting === 'schedule'} disabled={targets.length === 0 || !scheduledAt || blocking || submitting !== null}><CalendarClock className="size-4" /> Planla</Button>
                                <Button onClick={() => submit('publish')} loading={submitting === 'publish'} disabled={targets.length === 0 || blocking || submitting !== null}><Send className="size-4" /> Şimdi yayınla</Button>
                            </div>
                            {blocking && <p className="text-[11px] text-amber-300">Planlama/yayın için uyarıları giderin. Taslak her zaman kaydedilebilir.</p>}
                        </div>
                    </Card>
                    {props.post && (
                        <Link href={`/posts/${props.post.id}`} className="flex items-center justify-center gap-1.5 text-xs text-slate-400 hover:text-white"><FileText className="size-3.5" /> Yayın durumunu görüntüle</Link>
                    )}
                </div>
            </div>

            <MediaPickerModal open={pickerOpen} onClose={() => setPickerOpen(false)} media={props.media} selected={mediaIds} onChange={setMediaIds} />
        </AppLayout>
    );
}
