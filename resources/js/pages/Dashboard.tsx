import { Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, CalendarClock, Eye, Heart, MessageCircle, Percent, RefreshCw, Share2, Users } from 'lucide-react';
import { CompareBars } from '@/components/charts/CompareBars';
import { TrendChart } from '@/components/charts/TrendChart';
import { FilterBar, RangeSelector } from '@/components/shared/Filters';
import { KpiCard } from '@/components/shared/KpiCard';
import { MetricValue } from '@/components/shared/NullValue';
import { AccountAvatar, PlatformBadge } from '@/components/shared/Platform';
import { StatusBadge } from '@/components/shared/StatusBadge';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Card, CardHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';
import { buildQuery, compactNumber, formatDateTime, formatPercent, formatRelative, percentChange } from '@/lib/utils';
import type { Account, ComparisonRow, ExternalPostSummary, Kpis, ProjectRef, SeriesData, SharedProps, TargetSummary } from '@/types';

interface Props {
    range: number;
    filters: { project: string | null; platform: string | null; demo: string };
    projects: ProjectRef[];
    hasDemo: boolean;
    hasReal: boolean;
    accounts: Account[];
    kpis: Kpis;
    followersSeries: SeriesData;
    viewsSeries: SeriesData;
    engagementSeries: SeriesData;
    platforms: ComparisonRow[];
    topVideos: ExternalPostSummary[];
    topPosts: ExternalPostSummary[];
    upcoming: TargetSummary[];
    attention: TargetSummary[];
    notifications: Array<{ id: number; level: string; title: string; body: string | null; read_at: string | null; created_at: string }>;
    lastSync: string | null;
    schedulerLastRun: string | null;
}

function PostRow({ post, rank }: { post: ExternalPostSummary; rank: number }) {
    return (
        <li className="flex items-center gap-3 px-5 py-3">
            <span className="w-4 text-xs font-semibold text-slate-500 tabular-nums">{rank}</span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm text-white">{post.url ? <a href={post.url} target="_blank" rel="noreferrer noopener" className="hover:underline">{post.title ?? 'Başlıksız'}</a> : post.title ?? 'Başlıksız'}</p>
                <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                    {post.account && <PlatformBadge platform={post.account.platform} label={'@' + post.account.handle} />}
                    {post.content_type_label && <span>· {post.content_type_label}</span>}
                    {post.is_demo && <DemoBadge />}
                </div>
            </div>
            <div className="text-right text-xs">
                <p className="font-medium text-slate-200 tabular-nums"><MetricValue value={post.views} compact /> <span className="text-slate-500">görüntülenme</span></p>
                <p className="text-slate-500 tabular-nums">etkileşim {formatPercent(post.engagement_rate)}</p>
            </div>
        </li>
    );
}

function TargetRow({ t, showError = false }: { t: TargetSummary; showError?: boolean }) {
    return (
        <li>
            <Link href={`/posts/${t.post_id}`} className="block px-5 py-3 hover:bg-surface-850/60">
                <div className="flex items-center justify-between gap-3">
                    <p className="truncate text-sm text-white">{t.title ?? 'Başlıksız'}</p>
                    <StatusBadge status={t.status} label={t.status_label} />
                </div>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <PlatformBadge platform={t.platform} label={t.account} />
                    {t.scheduled_at && <span>· {formatDateTime(t.scheduled_at)}</span>}
                    {t.publish_mode === 'manual' && t.status === 'scheduled' && <Badge tone="warning">manuel</Badge>}
                    {t.is_demo && <DemoBadge />}
                </div>
                {showError && t.error_message && <p className="mt-1 line-clamp-2 text-xs text-rose-300/80">{t.error_message}</p>}
            </Link>
        </li>
    );
}

export default function Dashboard(props: Props) {
    const { range, filters, kpis } = props;
    const user = usePage<SharedProps>().props.auth.user!;
    const onlyDemo = props.hasDemo && !props.hasReal;
    const showDemoBadge = props.accounts.some((a) => a.is_demo);
    const params = { project: filters.project, platform: filters.platform, demo: filters.demo === '0' ? '0' : null };
    const go = (patch: Record<string, string | null>) => router.get('/dashboard', buildQuery({ ...params, range, ...patch }), { preserveState: true, preserveScroll: true, replace: true });

    return (
        <AppLayout
            title="Dashboard"
            description={`Hoş geldin, ${user.name}.`}
            actions={<RangeSelector value={range} url="/dashboard" params={params} />}
        >
            {onlyDemo && (
                <div className="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-fuchsia-500/30 bg-fuchsia-500/10 px-4 py-3 text-sm text-fuchsia-100">
                    <DemoBadge />
                    <span className="flex-1">Şu an yalnızca DEMO veriler görüntüleniyor. Gerçek hesaplarınızı bağladığınızda gerçek metrikler burada görünecek.</span>
                    {user.is_admin && <Link href="/accounts" className="font-medium underline underline-offset-2">Hesap bağla</Link>}
                </div>
            )}

            <FilterBar>
                <Select className="h-9 w-auto min-w-40" value={filters.project ?? ''} onChange={(e) => go({ project: e.target.value || null })} aria-label="Proje">
                    <option value="">Tüm projeler</option>
                    {props.projects.map((p) => <option key={p.id} value={p.id}>{p.name}{p.is_demo ? ' (demo)' : ''}</option>)}
                </Select>
                <Select className="h-9 w-auto min-w-36" value={filters.platform ?? ''} onChange={(e) => go({ platform: e.target.value || null })} aria-label="Platform">
                    <option value="">Tüm platformlar</option>
                    <option value="youtube">YouTube</option>
                    <option value="instagram">Instagram</option>
                    <option value="tiktok">TikTok</option>
                    <option value="x">X</option>
                </Select>
                {props.hasDemo && props.hasReal && (
                    <Select className="h-9 w-auto" value={filters.demo} onChange={(e) => go({ demo: e.target.value === '0' ? '0' : null })} aria-label="Demo veriler">
                        <option value="1">Demo dahil</option>
                        <option value="0">Yalnızca gerçek</option>
                    </Select>
                )}
                <span className="ml-auto inline-flex items-center gap-1.5 text-xs text-slate-500">
                    <RefreshCw className="size-3.5" /> Son senkronizasyon: {props.lastSync ? formatRelative(props.lastSync) : 'hiç'}
                </span>
            </FilterBar>

            {props.accounts.length === 0 ? (
                <Card>
                    <EmptyState icon={Share2} title="Henüz sosyal medya hesabı yok" description="Hesap ekleyin veya Ayarlar'dan demo verileri oluşturun." action={user.is_admin ? <Link href="/accounts" className="text-sm font-medium text-brand-300">Hesap ekle →</Link> : undefined} />
                </Card>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
                        <KpiCard icon={Users} label="Takipçi + abone" value={kpis.followers} change={kpis.followers_change_day} changeLabel="bugün" demo={showDemoBadge}
                            hint={kpis.followers_known_accounts < kpis.followers_total_accounts ? `${kpis.followers_total_accounts - kpis.followers_known_accounts} hesapta veri yok` : undefined} />
                        <KpiCard icon={Users} label="Haftalık değişim" value={kpis.followers_change_week} display={kpis.followers_change_week === null ? undefined : (kpis.followers_change_week > 0 ? '+' : '') + compactNumber(kpis.followers_change_week)} hint="son 7 gün" />
                        <KpiCard icon={Eye} label="Görüntülenme" value={kpis.views} change={percentChange(kpis.views, kpis.views_previous)} changeIsPercent changeLabel={`önceki ${range} güne göre`} />
                        <KpiCard icon={Heart} label="Beğeni" value={kpis.likes} change={percentChange(kpis.likes, kpis.likes_previous)} changeIsPercent changeLabel="önceki döneme göre" />
                        <KpiCard icon={MessageCircle} label="Yorum" value={kpis.comments} change={percentChange(kpis.comments, kpis.comments_previous)} changeIsPercent changeLabel="önceki döneme göre" />
                        <KpiCard icon={Percent} label="Etkileşim oranı" value={kpis.engagement_rate} display={kpis.engagement_rate === null ? undefined : formatPercent(kpis.engagement_rate)} hint="(beğeni+yorum+paylaşım) / görüntülenme" />
                    </div>

                    <div className="mt-4 grid gap-4 xl:grid-cols-3">
                        <Card className="xl:col-span-2">
                            <CardHeader title="Takipçi / abone gelişimi" description={`Son ${range} gün · platform bazlı (YouTube: abone, diğerleri: takipçi)`} />
                            <div className="p-5"><TrendChart data={props.followersSeries} /></div>
                        </Card>
                        <Card>
                            <CardHeader title="Platform bazlı takipçi" description="Güncel toplamlar" />
                            <div className="p-5">
                                <CompareBars items={kpis.followers_by_platform.map((p) => ({ key: p.platform, label: `${p.label} (${p.follower_label.toLowerCase()})`, color: props.platforms.find((x) => x.key === p.platform)?.color ?? '#3987e5', value: p.value }))} />
                                <div className="mt-4 grid grid-cols-2 gap-2 text-xs">
                                    <div className="rounded-lg bg-surface-850 p-3"><p className="text-slate-500">Erişim (Instagram)</p><p className="mt-1 text-base font-semibold text-white"><MetricValue value={kpis.reach} compact /></p></div>
                                    <div className="rounded-lg bg-surface-850 p-3"><p className="text-slate-500">Paylaşım</p><p className="mt-1 text-base font-semibold text-white"><MetricValue value={kpis.shares} compact /></p></div>
                                </div>
                            </div>
                        </Card>
                    </div>

                    <div className="mt-4 grid gap-4 xl:grid-cols-3">
                        <Card className="xl:col-span-2">
                            <CardHeader title="Görüntülenme" description="Günlük, platform bazlı. Boşluklar: o gün için veri yok." />
                            <div className="p-5"><TrendChart data={props.viewsSeries} area /></div>
                        </Card>
                        <Card>
                            <CardHeader title="Platform performansı" description={`Son ${range} gün`} />
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead><tr className="text-left text-xs text-slate-500"><th className="px-5 py-2 font-medium">Platform</th><th className="px-2 py-2 text-right font-medium">Görüntülenme</th><th className="px-5 py-2 text-right font-medium">Etkileşim</th></tr></thead>
                                    <tbody className="divide-y divide-surface-800">
                                        {props.platforms.map((p) => (
                                            <tr key={p.key}>
                                                <td className="px-5 py-2.5"><PlatformBadge platform={p.key as Account['platform']} /></td>
                                                <td className="px-2 py-2.5 text-right text-slate-200 tabular-nums"><MetricValue value={p.views} compact /></td>
                                                <td className="px-5 py-2.5 text-right text-slate-200 tabular-nums">{formatPercent(p.engagement_rate)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </div>

                    <div className="mt-4 grid gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader title="En başarılı videolar" description="Görüntülenmeye göre" />
                            {props.topVideos.length ? <ul className="divide-y divide-surface-800">{props.topVideos.map((p, i) => <PostRow key={p.id} post={p} rank={i + 1} />)}</ul> : <EmptyState icon={Eye} title="Bu dönemde video yok" />}
                        </Card>
                        <Card>
                            <CardHeader title="En başarılı gönderiler" description="Etkileşim oranına göre" />
                            {props.topPosts.length ? <ul className="divide-y divide-surface-800">{props.topPosts.map((p, i) => <PostRow key={p.id} post={p} rank={i + 1} />)}</ul> : <EmptyState icon={Heart} title="Bu dönemde gönderi yok" />}
                        </Card>
                    </div>
                </>
            )}

            <div className="mt-4 grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader title="Yaklaşan planlı paylaşımlar" action={<Link href="/calendar" className="text-xs text-brand-300 hover:underline">Takvim</Link>} />
                    {props.upcoming.length ? <ul className="divide-y divide-surface-800">{props.upcoming.map((t) => <TargetRow key={t.id} t={t} />)}</ul> : <EmptyState icon={CalendarClock} title="Planlı paylaşım yok" />}
                    <p className="border-t border-surface-800 px-5 py-2.5 text-[11px] text-slate-500">
                        Zamanlayıcı: {props.schedulerLastRun ? `son çalışma ${formatRelative(props.schedulerLastRun)}` : 'henüz çalışmadı (start.bat ile başlatın)'} · Bilgisayar kapalıyken planlı paylaşımlar çalışmaz.
                    </p>
                </Card>
                <Card>
                    <CardHeader title="Dikkat gerektirenler" description="Başarısız ve manuel bekleyen yayınlar" />
                    {props.attention.length ? <ul className="divide-y divide-surface-800">{props.attention.map((t) => <TargetRow key={t.id} t={t} showError />)}</ul> : <EmptyState icon={AlertTriangle} title="Her şey yolunda" />}
                </Card>
                <Card>
                    <CardHeader title="Bildirimler" action={<Link href="/notifications" className="text-xs text-brand-300 hover:underline">Tümü</Link>} />
                    {props.notifications.length ? (
                        <ul className="divide-y divide-surface-800">
                            {props.notifications.map((n) => (
                                <li key={n.id}>
                                    <a href={`/notifications/${n.id}/open`} className="flex gap-3 px-5 py-3 hover:bg-surface-850/60">
                                        <span className={`mt-1.5 size-2 shrink-0 rounded-full ${n.read_at ? 'bg-transparent' : 'bg-brand-400'}`} aria-label={n.read_at ? 'okundu' : 'okunmadı'} />
                                        <div className="min-w-0">
                                            <p className="truncate text-sm text-white">{n.title}</p>
                                            {n.body && <p className="truncate text-xs text-slate-400">{n.body}</p>}
                                            <p className="mt-0.5 text-[11px] text-slate-500">{formatRelative(n.created_at)}</p>
                                        </div>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    ) : <EmptyState icon={Bell} title="Bildirim yok" />}
                </Card>
            </div>

            {props.accounts.length > 0 && (
                <Card className="mt-4">
                    <CardHeader title="Bağlı hesaplar" description={`${props.accounts.length} hesap`} action={<Link href="/accounts" className="text-xs text-brand-300 hover:underline">Yönet</Link>} />
                    <div className="grid gap-px bg-surface-800 sm:grid-cols-2 xl:grid-cols-5">
                        {props.accounts.map((a) => (
                            <Link key={a.id} href={`/accounts/${a.id}`} className="flex items-center gap-3 bg-surface-900 px-4 py-3 hover:bg-surface-850">
                                <AccountAvatar platform={a.platform} name={a.display_name} url={a.avatar_url} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm text-white">@{a.handle}</p>
                                    <p className="text-xs text-slate-500"><MetricValue value={a.followers_count} compact /> {a.follower_label.toLowerCase()}</p>
                                </div>
                                {a.is_demo ? <DemoBadge /> : <StatusBadge status={a.status} label={a.status_label} />}
                            </Link>
                        ))}
                    </div>
                </Card>
            )}
        </AppLayout>
    );
}
