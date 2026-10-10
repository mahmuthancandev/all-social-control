import { router, useForm, usePage } from '@inertiajs/react';
import { ExternalLink, Eye, FileUp, Heart, Link2, MessageCircle, Percent, Plus, RefreshCw, Trash2, Users } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { TrendChart } from '@/components/charts/TrendChart';
import { CapabilityList } from '@/components/shared/Capabilities';
import { RangeSelector } from '@/components/shared/Filters';
import { KpiCard } from '@/components/shared/KpiCard';
import { MetricValue } from '@/components/shared/NullValue';
import { AccountAvatar } from '@/components/shared/Platform';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { FieldError, Input, Label } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatDateTime, formatPercent, formatRelative, percentChange, toLocalInput } from '@/lib/utils';
import type { Account, CapabilityItem, ExternalPostSummary, Kpis, SeriesData, SharedProps } from '@/types';

interface Props {
    account: Account & { projects: Array<{ id: number; name: string; color: string }>; granted_scopes: string[]; token_expires_at: string | null };
    capabilities: CapabilityItem[];
    range: number;
    kpis: Kpis;
    followersSeries: SeriesData;
    viewsSeries: SeriesData;
    posts: ExternalPostSummary[];
    snapshots: Array<{ captured_on: string; source: string; followers: number | null; views: number | null; reach: number | null; impressions: number | null; likes: number | null; comments: number | null; shares: number | null }>;
    syncLogs: Array<{ id: number; type: string; status: string; items_count: number | null; message: string | null; finished_at: string | null; created_at: string }>;
    projects: Array<{ id: number; name: string }>;
}

const METRIC_FIELDS: Array<[string, string]> = [['followers', 'Takipçi/abone'], ['views', 'Görüntülenme'], ['reach', 'Erişim'], ['impressions', 'Gösterim'], ['likes', 'Beğeni'], ['comments', 'Yorum'], ['shares', 'Paylaşım']];

function MetricsModal({ open, onClose, accountId }: { open: boolean; onClose: () => void; accountId: number }) {
    const form = useForm<Record<string, string>>({ captured_on: new Date().toISOString().slice(0, 10), followers: '', views: '', reach: '', impressions: '', likes: '', comments: '', shares: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : k === 'captured_on' ? v : Number(v)])));
        form.post(`/accounts/${accountId}/metrics`, { preserveScroll: true, onSuccess: () => { onClose(); form.reset(); } });
    };
    return (
        <Modal open={open} onClose={onClose} title="Metrik gir" description="Boş bıraktığınız alanlar 'veri yok' olarak kalır (0 yazılmaz)."
            footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button type="submit" form="metrics-form" loading={form.processing}>Kaydet</Button></>}>
            <form id="metrics-form" onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <Label htmlFor="m-date">Tarih</Label>
                    <Input id="m-date" type="date" value={form.data.captured_on} onChange={(e) => form.setData('captured_on', e.target.value)} invalid={!!form.errors.captured_on} />
                    <FieldError message={form.errors.captured_on} />
                </div>
                {METRIC_FIELDS.map(([k, l]) => (
                    <div key={k}>
                        <Label htmlFor={`m-${k}`}>{l}</Label>
                        <Input id={`m-${k}`} type="number" min={0} value={form.data[k]} onChange={(e) => form.setData(k, e.target.value)} invalid={!!form.errors[k]} />
                    </div>
                ))}
            </form>
        </Modal>
    );
}

function ImportModal({ open, onClose, accountId }: { open: boolean; onClose: () => void; accountId: number }) {
    const form = useForm<{ file: File | null }>({ file: null });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/accounts/${accountId}/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => { onClose(); form.reset(); } });
    };
    const template = 'data:text/csv;charset=utf-8,' + encodeURIComponent('﻿tarih;takipçi;görüntüleme;beğeni;yorum;paylaşım;erişim;gösterim\n2026-10-01;12500;3400;210;18;7;;\n');
    return (
        <Modal open={open} onClose={onClose} title="CSV içe aktar" description="YouTube Studio, Instagram, TikTok veya X Analytics'ten dışa aktardığınız günlük verileri yükleyin."
            footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button type="submit" form="import-form" loading={form.processing} disabled={!form.data.file}>İçe aktar</Button></>}>
            <form id="import-form" onSubmit={submit} className="space-y-3 text-sm text-slate-300">
                <p>Tanınan sütun başlıkları (TR/EN): <code className="text-xs text-slate-400">date/tarih, followers/takipçi/subscribers/abone, views/görüntüleme, likes/beğeni, comments/yorum, shares/paylaşım, reach/erişim, impressions/gösterim</code>. Ayraç , ; veya sekme olabilir.</p>
                <a href={template} download="socialhub-sablon.csv" className="inline-block text-xs text-brand-300 underline">Örnek şablonu indir</a>
                <input type="file" accept=".csv,text/csv,text/plain" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                    className="block w-full text-xs text-slate-400 file:mr-3 file:rounded-md file:border-0 file:bg-surface-700 file:px-3 file:py-2 file:text-xs file:text-white" />
                <FieldError message={form.errors.file} />
            </form>
        </Modal>
    );
}

function LinkModal({ open, onClose, accountId }: { open: boolean; onClose: () => void; accountId: number }) {
    const form = useForm({ url: '', published_at: toLocalInput(new Date().toISOString()), views: '', likes: '', comments: '', shares: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, views: d.views === '' ? null : Number(d.views), likes: d.likes === '' ? null : Number(d.likes), comments: d.comments === '' ? null : Number(d.comments), shares: d.shares === '' ? null : Number(d.shares) }));
        form.post(`/accounts/${accountId}/links`, { preserveScroll: true, onSuccess: () => { onClose(); form.reset(); } });
    };
    return (
        <Modal open={open} onClose={onClose} title="Gönderi linki ekle" description="Başlık ve kapak, platformun resmi oEmbed servisiyle alınır (Instagram hariç). Sayıları isterseniz elle girin."
            footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button type="submit" form="link-form" loading={form.processing}>Ekle</Button></>}>
            <form id="link-form" onSubmit={submit} className="grid gap-3 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <Label htmlFor="l-url">Gönderi linki</Label>
                    <Input id="l-url" placeholder="https://..." value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} invalid={!!form.errors.url} />
                    <FieldError message={form.errors.url} />
                </div>
                <div className="sm:col-span-2">
                    <Label htmlFor="l-at">Yayın zamanı</Label>
                    <Input id="l-at" type="datetime-local" value={form.data.published_at} onChange={(e) => form.setData('published_at', e.target.value)} />
                </div>
                {(['views', 'likes', 'comments', 'shares'] as const).map((k) => (
                    <div key={k}>
                        <Label htmlFor={`l-${k}`}>{{ views: 'Görüntülenme', likes: 'Beğeni', comments: 'Yorum', shares: 'Paylaşım' }[k]}</Label>
                        <Input id={`l-${k}`} type="number" min={0} value={form.data[k]} onChange={(e) => form.setData(k, e.target.value)} />
                    </div>
                ))}
            </form>
        </Modal>
    );
}

export default function AccountShow(props: Props) {
    const { account, kpis, range } = props;
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [modal, setModal] = useState<'metrics' | 'import' | 'link' | 'delete' | null>(null);
    const [syncing, setSyncing] = useState(false);
    const [projectIds, setProjectIds] = useState(account.projects.map((p) => p.id));

    return (
        <AppLayout
            title={account.display_name}
            back={{ href: '/accounts', label: 'Hesaplar' }}
            badge={account.is_demo ? <DemoBadge /> : <StatusBadge status={account.status} label={account.status_label} />}
            description={<span className="inline-flex flex-wrap items-center gap-2"><a href={account.profile_url} target="_blank" rel="noreferrer noopener" className="inline-flex items-center gap-1 hover:text-white">{account.platform_label} · @{account.handle} <ExternalLink className="size-3" /></a><Badge>{account.connection_label}</Badge></span>}
            actions={
                <>
                    <RangeSelector value={range} url={`/accounts/${account.id}`} />
                    {isAdmin && account.connection_type !== 'manual' && (
                        <Button variant="secondary" loading={syncing} onClick={() => router.post(`/accounts/${account.id}/sync`, {}, { preserveScroll: true, onStart: () => setSyncing(true), onFinish: () => setSyncing(false) })}>
                            <RefreshCw className="size-4" /> Senkronize et
                        </Button>
                    )}
                    {isAdmin && account.connection_type === 'oauth' && !account.is_demo && (
                        <a href={`/oauth/${account.platform}/redirect?account=${account.id}`}><Button variant="secondary"><Link2 className="size-4" /> Bağlantıyı yenile</Button></a>
                    )}
                </>
            }
        >
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <KpiCard icon={Users} label={account.follower_label} value={kpis.followers} change={kpis.followers_change_range} changeLabel={`son ${range} gün`} demo={account.is_demo} />
                <KpiCard icon={Eye} label="Görüntülenme" value={kpis.views} change={percentChange(kpis.views, kpis.views_previous)} changeIsPercent changeLabel="önceki döneme göre" />
                <KpiCard icon={Heart} label="Beğeni" value={kpis.likes} />
                <KpiCard icon={MessageCircle} label="Yorum" value={kpis.comments} />
                <KpiCard icon={Percent} label="Etkileşim oranı" value={kpis.engagement_rate} display={kpis.engagement_rate === null ? undefined : formatPercent(kpis.engagement_rate)} />
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-3">
                <div className="space-y-4 xl:col-span-2">
                    <Card>
                        <CardHeader title={`${account.follower_label} gelişimi`} />
                        <div className="p-5"><TrendChart data={props.followersSeries} /></div>
                    </Card>
                    <Card>
                        <CardHeader title="Günlük görüntülenme" />
                        <div className="p-5"><TrendChart data={props.viewsSeries} area emptyText="Bu hesap için günlük görüntülenme verisi yok. OAuth analitik izni, CSV içe aktarma veya manuel giriş ile eklenebilir." /></div>
                    </Card>
                </div>
                <div className="space-y-4">
                    <Card>
                        <CardHeader title="Yetenekler" description="Bağlantı izinlerine göre" />
                        <div className="p-5"><CapabilityList items={props.capabilities} /></div>
                        {account.connection_type === 'oauth' && (
                            <div className="border-t border-surface-800 px-5 py-3 text-xs text-slate-500">
                                Token geçerliliği: {account.token_expires_at ? formatDateTime(account.token_expires_at) : 'süresiz / bilinmiyor'}
                                {account.granted_scopes.length > 0 && <p className="mt-1 break-all">İzinler: {account.granted_scopes.map((s) => s.replace('https://www.googleapis.com/auth/', '')).join(', ')}</p>}
                            </div>
                        )}
                    </Card>
                    {isAdmin && (
                        <Card>
                            <CardHeader title="Veri ekle" description="API olmadan da geçmiş ölçüm biriktirin" />
                            <div className="grid gap-2 p-5">
                                <Button variant="secondary" onClick={() => setModal('metrics')}><Plus className="size-4" /> Günlük metrik gir</Button>
                                <Button variant="secondary" onClick={() => setModal('import')}><FileUp className="size-4" /> CSV içe aktar</Button>
                                <Button variant="secondary" onClick={() => setModal('link')}><Link2 className="size-4" /> Gönderi linki ekle</Button>
                            </div>
                        </Card>
                    )}
                    {isAdmin && (
                        <Card>
                            <CardHeader title="Projeler" />
                            <div className="space-y-2 p-5">
                                {props.projects.map((p) => (
                                    <label key={p.id} className="flex items-center gap-2 text-sm text-slate-300">
                                        <input type="checkbox" className="accent-brand-500" checked={projectIds.includes(p.id)} onChange={() => setProjectIds((ids) => (ids.includes(p.id) ? ids.filter((x) => x !== p.id) : [...ids, p.id]))} />
                                        {p.name}
                                    </label>
                                ))}
                                <Button size="sm" className="mt-2" onClick={() => router.put(`/accounts/${account.id}`, { display_name: account.display_name, project_ids: projectIds }, { preserveScroll: true })}>Kaydet</Button>
                            </div>
                            <div className="border-t border-surface-800 p-5">
                                <Button variant="danger" size="sm" onClick={() => setModal('delete')}><Trash2 className="size-3.5" /> Hesabı kaldır</Button>
                            </div>
                        </Card>
                    )}
                </div>
            </div>

            <Card className="mt-4">
                <CardHeader title="Gönderiler" description="Platformdan senkronize edilen veya eklenen içerikler" />
                {props.posts.length === 0 ? <EmptyState icon={Eye} title="Gönderi yok" /> : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead><tr className="border-b border-surface-800 text-left text-xs text-slate-500"><th className="px-5 py-2 font-medium">İçerik</th><th className="px-3 py-2 font-medium">Yayın</th><th className="px-3 py-2 text-right font-medium">Görüntülenme</th><th className="px-3 py-2 text-right font-medium">Beğeni</th><th className="px-3 py-2 text-right font-medium">Yorum</th><th className="px-3 py-2 text-right font-medium">Paylaşım</th><th className="px-5 py-2 text-right font-medium">Etkileşim</th></tr></thead>
                            <tbody className="divide-y divide-surface-800">
                                {props.posts.map((p) => (
                                    <tr key={p.id} className="hover:bg-surface-850/50">
                                        <td className="max-w-xs px-5 py-2.5">
                                            <p className="truncate text-white">{p.url ? <a href={p.url} target="_blank" rel="noreferrer noopener" className="hover:underline">{p.title ?? 'Başlıksız'}</a> : p.title ?? 'Başlıksız'}</p>
                                            <p className="text-xs text-slate-500">{p.content_type_label}{p.is_demo && ' · DEMO'}</p>
                                        </td>
                                        <td className="px-3 py-2.5 text-xs whitespace-nowrap text-slate-400">{formatDateTime(p.published_at)}</td>
                                        <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.views} /></td>
                                        <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.likes} /></td>
                                        <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.comments} /></td>
                                        <td className="px-3 py-2.5 text-right tabular-nums"><MetricValue value={p.shares} /></td>
                                        <td className="px-5 py-2.5 text-right tabular-nums">{formatPercent(p.engagement_rate)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader title="Son ölçümler" description="Tarihsel kayıtlar (— = veri yok)" />
                    <div className="overflow-x-auto">
                        <table className="w-full text-xs">
                            <thead><tr className="border-b border-surface-800 text-left text-slate-500"><th className="px-5 py-2 font-medium">Tarih</th><th className="px-2 py-2 text-right font-medium">Takipçi</th><th className="px-2 py-2 text-right font-medium">Görüntülenme</th><th className="px-2 py-2 text-right font-medium">Beğeni</th><th className="px-5 py-2 font-medium">Kaynak</th></tr></thead>
                            <tbody className="divide-y divide-surface-800">
                                {props.snapshots.map((s) => (
                                    <tr key={s.captured_on}>
                                        <td className="px-5 py-2 text-slate-300">{formatDate(s.captured_on, { day: 'numeric', month: 'short', year: 'numeric' })}</td>
                                        <td className="px-2 py-2 text-right tabular-nums"><MetricValue value={s.followers} /></td>
                                        <td className="px-2 py-2 text-right tabular-nums"><MetricValue value={s.views} /></td>
                                        <td className="px-2 py-2 text-right tabular-nums"><MetricValue value={s.likes} /></td>
                                        <td className="px-5 py-2 text-slate-500">{s.source}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
                <Card>
                    <CardHeader title="Senkronizasyon geçmişi" />
                    {props.syncLogs.length === 0 ? <EmptyState icon={RefreshCw} title="Kayıt yok" /> : (
                        <ul className="divide-y divide-surface-800">
                            {props.syncLogs.map((l) => (
                                <li key={l.id} className="px-5 py-2.5 text-xs">
                                    <div className="flex items-center justify-between gap-2">
                                        <Badge tone={l.status === 'success' ? 'success' : l.status === 'failed' ? 'danger' : l.status === 'partial' ? 'warning' : 'neutral'}>{l.status}</Badge>
                                        <span className="text-slate-500">{formatRelative(l.finished_at ?? l.created_at)}</span>
                                    </div>
                                    {l.message && <p className="mt-1 text-slate-400">{l.message}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <MetricsModal open={modal === 'metrics'} onClose={() => setModal(null)} accountId={account.id} />
            <ImportModal open={modal === 'import'} onClose={() => setModal(null)} accountId={account.id} />
            <LinkModal open={modal === 'link'} onClose={() => setModal(null)} accountId={account.id} />
            <ConfirmDialog open={modal === 'delete'} onClose={() => setModal(null)} title="Hesabı kaldır" confirmLabel="Kaldır" onConfirm={() => router.delete(`/accounts/${account.id}`)}>
                @{account.handle} ve tüm kayıtlı ölçümleri, yorumları ve şifreli bağlantı bilgileri silinecek. Platformdaki içerikleriniz etkilenmez.
            </ConfirmDialog>
        </AppLayout>
    );
}
