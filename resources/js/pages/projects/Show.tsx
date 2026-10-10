import { Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Eye, FileText, Heart, Pencil, Percent, PenSquare, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { CompareBars } from '@/components/charts/CompareBars';
import { TrendChart } from '@/components/charts/TrendChart';
import { RangeSelector } from '@/components/shared/Filters';
import { KpiCard } from '@/components/shared/KpiCard';
import { MetricValue } from '@/components/shared/NullValue';
import { AccountAvatar, PlatformBadge } from '@/components/shared/Platform';
import { ProjectFormModal } from '@/components/shared/ProjectFormModal';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import AppLayout from '@/layouts/AppLayout';
import { formatDateTime, formatPercent, percentChange } from '@/lib/utils';
import type { Account, ComparisonRow, ExternalPostSummary, Kpis, SeriesData, SharedProps, TargetSummary } from '@/types';

interface Props {
    project: { id: number; name: string; type: string; type_label: string; category: string | null; description: string | null; color: string; logo_url: string | null; is_demo: boolean };
    range: number;
    accounts: Account[];
    allAccounts: Account[];
    types: Record<string, string>;
    kpis: Kpis;
    followersSeries: SeriesData;
    viewsSeries: SeriesData;
    platforms: ComparisonRow[];
    topPosts: ExternalPostSummary[];
    publishedCount: number;
    targets: TargetSummary[];
}

export default function ProjectShow(props: Props) {
    const { project, kpis, range } = props;
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [editing, setEditing] = useState(false);
    const [deleting, setDeleting] = useState(false);

    return (
        <AppLayout
            title={project.name}
            badge={<>{project.is_demo && <DemoBadge />}<Badge tone="brand">{project.type_label}</Badge>{project.category && <Badge>{project.category}</Badge>}</>}
            back={{ href: '/projects', label: 'Projelerim' }}
            description={project.description ?? undefined}
            actions={
                <>
                    <RangeSelector value={range} url={`/projects/${project.id}`} />
                    {isAdmin && (
                        <>
                            <Link href={`/posts/create?project=${project.id}`}><Button><PenSquare className="size-4" /> İçerik Oluştur</Button></Link>
                            <Button variant="secondary" onClick={() => setEditing(true)} aria-label="Düzenle"><Pencil className="size-4" /></Button>
                            <Button variant="secondary" onClick={() => setDeleting(true)} aria-label="Sil"><Trash2 className="size-4" /></Button>
                        </>
                    )}
                </>
            }
        >
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <KpiCard icon={Users} label="Takipçi + abone" value={kpis.followers} change={kpis.followers_change_range} changeLabel={`son ${range} gün`} demo={project.is_demo} />
                <KpiCard icon={Eye} label="Görüntülenme" value={kpis.views} change={percentChange(kpis.views, kpis.views_previous)} changeIsPercent changeLabel="önceki döneme göre" />
                <KpiCard icon={Heart} label="Beğeni" value={kpis.likes} change={percentChange(kpis.likes, kpis.likes_previous)} changeIsPercent />
                <KpiCard icon={Percent} label="Etkileşim oranı" value={kpis.engagement_rate} display={kpis.engagement_rate === null ? undefined : formatPercent(kpis.engagement_rate)} />
                <KpiCard icon={FileText} label="Yayınlanan içerik" value={props.publishedCount} hint={`son ${range} gün`} />
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader title="Takipçi değişimi" description="Hesap bazlı" />
                    <div className="p-5"><TrendChart data={props.followersSeries} /></div>
                </Card>
                <Card>
                    <CardHeader title="Bağlı hesaplar" description={`${props.accounts.length} hesap`} />
                    {props.accounts.length === 0 ? (
                        <EmptyState icon={Users} title="Bağlı hesap yok" action={isAdmin && <Button size="sm" onClick={() => setEditing(true)}>Hesap bağla</Button>} />
                    ) : (
                        <ul className="divide-y divide-surface-800">
                            {props.accounts.map((a) => (
                                <li key={a.id}>
                                    <Link href={`/accounts/${a.id}`} className="flex items-center gap-3 px-5 py-3 hover:bg-surface-850/60">
                                        <AccountAvatar platform={a.platform} name={a.display_name} url={a.avatar_url} />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm text-white">@{a.handle}</p>
                                            <p className="text-xs text-slate-500">{a.platform_label}</p>
                                        </div>
                                        <div className="text-right text-xs">
                                            <p className="font-medium text-white"><MetricValue value={a.followers_count} compact /></p>
                                            <p className="text-slate-500">{a.follower_label.toLowerCase()}</p>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-3">
                <Card className="xl:col-span-2">
                    <CardHeader title="Görüntülenme" description="Platform bazlı, günlük" />
                    <div className="p-5"><TrendChart data={props.viewsSeries} area /></div>
                </Card>
                <Card>
                    <CardHeader title="Platform karşılaştırması" description="Görüntülenme" />
                    <div className="p-5"><CompareBars items={props.platforms.map((p) => ({ key: p.key, label: p.label, color: p.color, value: p.views }))} /></div>
                </Card>
            </div>

            <div className="mt-4 grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader title="İçerik takvimi" description="Planlı, taslak ve dikkat gerektiren" action={<Link href={`/calendar?project=${project.id}`} className="text-xs text-brand-300 hover:underline">Takvimde aç</Link>} />
                    {props.targets.length === 0 ? <EmptyState icon={CalendarDays} title="Planlı içerik yok" /> : (
                        <ul className="divide-y divide-surface-800">
                            {props.targets.map((t) => (
                                <li key={t.id}>
                                    <Link href={`/posts/${t.post_id}`} className="flex items-center gap-3 px-5 py-3 hover:bg-surface-850/60">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm text-white">{t.title ?? 'Başlıksız'}</p>
                                            <p className="mt-0.5 flex items-center gap-2 text-xs text-slate-500"><PlatformBadge platform={t.platform} label={t.account} /> {t.scheduled_at && <>· {formatDateTime(t.scheduled_at)}</>}</p>
                                        </div>
                                        <StatusBadge status={t.status} label={t.status_label} />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
                <Card>
                    <CardHeader title="En iyi içerikler" description={`Son ${range} gün`} />
                    {props.topPosts.length === 0 ? <EmptyState icon={Eye} title="Bu dönemde yayın yok" /> : (
                        <ul className="divide-y divide-surface-800">
                            {props.topPosts.map((p) => (
                                <li key={p.id} className="flex items-center gap-3 px-5 py-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm text-white">{p.title ?? 'Başlıksız'}</p>
                                        <p className="mt-0.5 flex items-center gap-2 text-xs text-slate-500">{p.account && <PlatformBadge platform={p.account.platform} />} {p.content_type_label}</p>
                                    </div>
                                    <div className="text-right text-xs">
                                        <p className="text-slate-200"><MetricValue value={p.views} compact /> görüntülenme</p>
                                        <p className="text-slate-500">{formatPercent(p.engagement_rate)}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <ProjectFormModal open={editing} onClose={() => setEditing(false)} accounts={props.allAccounts} types={props.types}
                project={{ ...project, account_ids: props.accounts.map((a) => a.id) }} />
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} title="Projeyi sil" confirmLabel="Sil"
                onConfirm={() => router.delete(`/projects/${project.id}`)}>
                <b className="text-white">{project.name}</b> silinecek. Bağlı sosyal medya hesapları, gönderiler ve medya dosyaları silinmez; yalnızca proje bağlantısı kaldırılır.
            </ConfirmDialog>
        </AppLayout>
    );
}
