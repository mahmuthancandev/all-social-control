import { Link, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, ExternalLink, Info, KeyRound, Link2, Plus, RefreshCw, Share2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { CapabilityList } from '@/components/shared/Capabilities';
import { MetricValue } from '@/components/shared/NullValue';
import { AccountAvatar, PlatformBadge, PlatformIcon, PLATFORM_COLORS } from '@/components/shared/Platform';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { FieldError, Input, Label, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { formatRelative } from '@/lib/utils';
import type { Account, CapabilityItem, PlatformKey, SharedProps } from '@/types';

type Row = Account & { projects: Array<{ id: number; name: string; color: string }>; capabilities: CapabilityItem[] };

interface Props {
    accounts: Row[];
    platforms: Array<{ value: PlatformKey; label: string }>;
    overview: Array<{ platform: PlatformKey; label: string; configured: boolean; notes: string[] }>;
    projects: Array<{ id: number; name: string }>;
    youtubeApiKeySet: boolean;
}

function AddAccountModal({ open, onClose, platforms, overview, projects, youtubeApiKeySet }: Omit<Props, 'accounts'> & { open: boolean; onClose: () => void }) {
    const [platform, setPlatform] = useState<PlatformKey>('youtube');
    const form = useForm({ platform: 'youtube', handle: '', display_name: '', connection_type: 'manual', followers_count: '', project_ids: [] as number[] });
    const info = overview.find((o) => o.platform === platform)!;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, platform, followers_count: d.followers_count === '' ? null : Number(d.followers_count) }));
        form.post('/accounts', { onSuccess: () => { onClose(); form.reset(); } });
    };

    return (
        <Modal open={open} onClose={onClose} size="lg" title="Hesap ekle" description="Sosyal medya şifreniz asla istenmez. Bağlantı resmi OAuth ekranında yapılır.">
            <div className="mb-5 grid grid-cols-4 gap-2">
                {platforms.map((p) => (
                    <button key={p.value} onClick={() => setPlatform(p.value)} className={`flex flex-col items-center gap-1.5 rounded-lg border p-3 text-xs transition-colors ${platform === p.value ? 'border-brand-500/60 bg-brand-500/10 text-white' : 'border-surface-700 text-slate-400 hover:text-white'}`}>
                        <span style={{ color: PLATFORM_COLORS[p.value] }}><PlatformIcon platform={p.value} className="size-5" /></span>
                        {p.label}
                    </button>
                ))}
            </div>

            <div className="space-y-4">
                <div className="rounded-lg border border-surface-700 bg-surface-850 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <p className="text-sm font-semibold text-white">1. Resmi OAuth ile bağla <Badge tone="success">önerilen</Badge></p>
                            <p className="mt-1 text-xs text-slate-400">Takipçi, gönderi, yorum ve (izin varsa) yayınlama özellikleri bağlantının verdiği izinlere göre açılır.</p>
                        </div>
                        {info.configured ? (
                            <a href={`/oauth/${platform}/redirect`}><Button><Link2 className="size-4" /> Bağlan</Button></a>
                        ) : (
                            <Link href="/settings"><Button variant="secondary"><KeyRound className="size-4" /> Önce ayarla</Button></Link>
                        )}
                    </div>
                    {!info.configured && <p className="mt-2 text-xs text-amber-300/90">{platform === 'x' ? 'X API ücretli olduğu için kapalı. Manuel hesap ekleyin.' : `${info.label} uygulama bilgileri Ayarlar'da girilmemiş.`}</p>}
                    <ul className="mt-3 space-y-1">
                        {info.notes.map((n, i) => <li key={i} className="flex gap-2 text-xs text-slate-500"><Info className="mt-0.5 size-3 shrink-0" />{n}</li>)}
                    </ul>
                </div>

                <form onSubmit={submit} className="rounded-lg border border-surface-700 bg-surface-850 p-4">
                    <p className="text-sm font-semibold text-white">2. {platform === 'youtube' ? 'API anahtarı (herkese açık veri) veya manuel' : 'Manuel hesap'}</p>
                    <p className="mt-1 text-xs text-slate-400">
                        {platform === 'youtube'
                            ? 'Ücretsiz YouTube API anahtarıyla kanalın herkese açık abone/video/yorum verileri okunur. Yayın için OAuth gerekir.'
                            : 'API bağlantısı olmadan: metrikleri elle veya platformun kendi analitik CSV dışa aktarımıyla girersiniz; paylaşımlar Paylaşım Asistanı ile yapılır.'}
                    </p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="a-handle">Kullanıcı adı</Label>
                            <Input id="a-handle" placeholder="@kullaniciadi" value={form.data.handle} onChange={(e) => form.setData('handle', e.target.value)} invalid={!!form.errors.handle} />
                            <FieldError message={form.errors.handle} />
                        </div>
                        <div>
                            <Label htmlFor="a-type">Bağlantı türü</Label>
                            <Select id="a-type" value={form.data.connection_type} onChange={(e) => form.setData('connection_type', e.target.value)}>
                                <option value="manual">Manuel (API yok)</option>
                                {platform === 'youtube' && <option value="api_key" disabled={!youtubeApiKeySet}>Herkese açık API anahtarı{youtubeApiKeySet ? '' : ' (Ayarlar\'da girilmeli)'}</option>}
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="a-name">Görünen ad (isteğe bağlı)</Label>
                            <Input id="a-name" value={form.data.display_name} onChange={(e) => form.setData('display_name', e.target.value)} />
                        </div>
                        {form.data.connection_type === 'manual' && (
                            <div>
                                <Label htmlFor="a-f">Güncel takipçi/abone (isteğe bağlı)</Label>
                                <Input id="a-f" type="number" min={0} value={form.data.followers_count} onChange={(e) => form.setData('followers_count', e.target.value)} />
                            </div>
                        )}
                        <div className="sm:col-span-2">
                            <Label>Projeler</Label>
                            <div className="flex flex-wrap gap-2">
                                {projects.map((p) => (
                                    <label key={p.id} className="inline-flex items-center gap-1.5 rounded-md border border-surface-700 px-2 py-1 text-xs text-slate-300">
                                        <input type="checkbox" className="accent-brand-500" checked={form.data.project_ids.includes(p.id)}
                                            onChange={() => form.setData('project_ids', form.data.project_ids.includes(p.id) ? form.data.project_ids.filter((x) => x !== p.id) : [...form.data.project_ids, p.id])} />
                                        {p.name}
                                    </label>
                                ))}
                                {projects.length === 0 && <span className="text-xs text-slate-500">Proje yok</span>}
                            </div>
                        </div>
                    </div>
                    <div className="mt-4 flex justify-end">
                        <Button type="submit" variant="secondary" loading={form.processing}><Plus className="size-4" /> Ekle</Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}

export default function AccountsIndex(props: Props) {
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [open, setOpen] = useState(false);
    const [syncing, setSyncing] = useState<number | null>(null);
    const grouped = props.platforms.map((p) => ({ ...p, items: props.accounts.filter((a) => a.platform === p.value) })).filter((g) => g.items.length);

    return (
        <AppLayout
            title="Sosyal Medya Hesapları"
            description={`${props.accounts.length} hesap · Özellikler her hesabın gerçek API izinlerine göre açılır.`}
            actions={isAdmin && <Button onClick={() => setOpen(true)}><Plus className="size-4" /> Hesap Ekle</Button>}
        >
            {props.accounts.length === 0 ? (
                <Card><EmptyState icon={Share2} title="Henüz hesap yok" description="YouTube, Instagram, TikTok veya X hesabınızı ekleyin." action={isAdmin && <Button onClick={() => setOpen(true)}><Plus className="size-4" /> Hesap Ekle</Button>} /></Card>
            ) : (
                <div className="space-y-6">
                    {grouped.map((g) => (
                        <section key={g.value}>
                            <h2 className="mb-3"><PlatformBadge platform={g.value} label={`${g.label} · ${g.items.length}`} className="text-sm" /></h2>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {g.items.map((a) => (
                                    <Card key={a.id} className="flex flex-col p-5">
                                        <div className="flex items-start gap-3">
                                            <AccountAvatar platform={a.platform} name={a.display_name} url={a.avatar_url} size="lg" />
                                            <div className="min-w-0 flex-1">
                                                <Link href={`/accounts/${a.id}`} className="block truncate font-semibold text-white hover:underline">{a.display_name}</Link>
                                                <a href={a.profile_url} target="_blank" rel="noreferrer noopener" className="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white">@{a.handle} <ExternalLink className="size-3" /></a>
                                                <div className="mt-1.5 flex flex-wrap gap-1.5">
                                                    {a.is_demo ? <DemoBadge /> : <StatusBadge status={a.status} label={a.status_label} />}
                                                    <Badge>{a.connection_label}</Badge>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="mt-4 grid grid-cols-2 gap-2 text-center">
                                            <div className="rounded-lg bg-surface-850 py-2">
                                                <p className="text-[11px] text-slate-500">{a.follower_label}</p>
                                                <p className="font-semibold text-white"><MetricValue value={a.followers_count} compact /></p>
                                            </div>
                                            <div className="rounded-lg bg-surface-850 py-2">
                                                <p className="text-[11px] text-slate-500">Gönderi</p>
                                                <p className="font-semibold text-white"><MetricValue value={a.posts_count} compact /></p>
                                            </div>
                                        </div>
                                        {a.status === 'needs_reauth' || a.status === 'error' ? (
                                            <p className="mt-3 flex gap-1.5 rounded-lg bg-amber-500/10 p-2 text-xs text-amber-200"><AlertTriangle className="size-3.5 shrink-0" /> {a.last_error ?? 'Bağlantı sorunu'}</p>
                                        ) : null}
                                        <div className="mt-3"><CapabilityList items={a.capabilities} compact /></div>
                                        {a.projects.length > 0 && (
                                            <div className="mt-3 flex flex-wrap gap-1.5">
                                                {a.projects.map((p) => <span key={p.id} className="rounded-md px-2 py-0.5 text-[11px] text-white" style={{ backgroundColor: `${p.color}40` }}>{p.name}</span>)}
                                            </div>
                                        )}
                                        <div className="mt-auto flex items-center justify-between gap-2 border-t border-surface-800 pt-3 text-xs text-slate-500">
                                            <span>Son güncelleme: {a.last_synced_at ? formatRelative(a.last_synced_at) : 'hiç'}</span>
                                            <div className="flex gap-1">
                                                {isAdmin && a.connection_type === 'oauth' && a.status !== 'connected' && !a.is_demo && (
                                                    <a href={`/oauth/${a.platform}/redirect?account=${a.id}`}><Button size="sm" variant="secondary">Yeniden bağla</Button></a>
                                                )}
                                                {isAdmin && a.connection_type !== 'manual' && (
                                                    <Button size="sm" variant="ghost" loading={syncing === a.id} aria-label="Senkronize et"
                                                        onClick={() => router.post(`/accounts/${a.id}/sync`, {}, { preserveScroll: true, onStart: () => setSyncing(a.id), onFinish: () => setSyncing(null) })}>
                                                        <RefreshCw className="size-3.5" />
                                                    </Button>
                                                )}
                                                <Link href={`/accounts/${a.id}`}><Button size="sm" variant="ghost">Detay</Button></Link>
                                            </div>
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        </section>
                    ))}
                </div>
            )}
            {isAdmin && <AddAccountModal open={open} onClose={() => setOpen(false)} platforms={props.platforms} overview={props.overview} projects={props.projects} youtubeApiKeySet={props.youtubeApiKeySet} />}
        </AppLayout>
    );
}
