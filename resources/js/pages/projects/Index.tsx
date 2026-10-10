import { Link, usePage } from '@inertiajs/react';
import { FolderKanban, Plus } from 'lucide-react';
import { useState } from 'react';
import { ProjectFormModal } from '@/components/shared/ProjectFormModal';
import { MetricValue } from '@/components/shared/NullValue';
import { AccountAvatar } from '@/components/shared/Platform';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import AppLayout from '@/layouts/AppLayout';
import { formatPercent, signed } from '@/lib/utils';
import type { Account, SharedProps } from '@/types';

interface ProjectCard {
    id: number;
    name: string;
    type: string;
    type_label: string;
    category: string | null;
    description: string | null;
    color: string;
    logo_url: string | null;
    is_demo: boolean;
    posts_count: number;
    accounts: Account[];
    followers: number | null;
    followers_change: number | null;
    views: number | null;
    engagement_rate: number | null;
}

export default function ProjectsIndex({ projects, accounts, types }: { projects: ProjectCard[]; accounts: Account[]; types: Record<string, string> }) {
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [open, setOpen] = useState(false);

    return (
        <AppLayout
            title="Projelerim"
            description="Oyun ve uygulamalarınızın tüm sosyal medya performansı tek yerde."
            actions={isAdmin && <Button onClick={() => setOpen(true)}><Plus className="size-4" /> Proje Ekle</Button>}
        >
            {projects.length === 0 ? (
                <Card>
                    <EmptyState icon={FolderKanban} title="Henüz proje yok" description="İlk oyununuzu veya uygulamanızı ekleyin, hesaplarını bağlayın." action={isAdmin && <Button onClick={() => setOpen(true)}><Plus className="size-4" /> Proje Ekle</Button>} />
                </Card>
            ) : (
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {projects.map((p) => (
                        <Link key={p.id} href={`/projects/${p.id}`} className="group">
                            <Card className="h-full p-5 transition-colors group-hover:border-surface-600">
                                <div className="flex items-start gap-4">
                                    {p.logo_url ? (
                                        <img src={p.logo_url} alt="" className="size-12 rounded-xl object-cover" />
                                    ) : (
                                        <span className="flex size-12 items-center justify-center rounded-xl text-lg font-bold text-white" style={{ backgroundColor: p.color }}>{p.name.slice(0, 1)}</span>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <h3 className="truncate font-semibold text-white">{p.name}</h3>
                                            {p.is_demo && <DemoBadge />}
                                        </div>
                                        <div className="mt-1 flex flex-wrap gap-1.5">
                                            <Badge tone="brand">{p.type_label}</Badge>
                                            {p.category && <Badge>{p.category}</Badge>}
                                        </div>
                                    </div>
                                </div>
                                {p.description && <p className="mt-3 line-clamp-2 text-sm text-slate-400">{p.description}</p>}
                                <div className="mt-4 grid grid-cols-3 gap-2 border-t border-surface-800 pt-4 text-center">
                                    <div>
                                        <p className="text-[11px] text-slate-500">Takipçi + abone</p>
                                        <p className="font-semibold text-white"><MetricValue value={p.followers} compact /></p>
                                        <p className="text-[11px] text-emerald-400">{p.followers_change !== null ? signed(p.followers_change, true) + ' (30g)' : ''}</p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] text-slate-500">Görüntülenme (30g)</p>
                                        <p className="font-semibold text-white"><MetricValue value={p.views} compact /></p>
                                    </div>
                                    <div>
                                        <p className="text-[11px] text-slate-500">Etkileşim</p>
                                        <p className="font-semibold text-white">{formatPercent(p.engagement_rate)}</p>
                                    </div>
                                </div>
                                <div className="mt-4 flex items-center justify-between">
                                    <div className="flex -space-x-1.5">
                                        {p.accounts.slice(0, 6).map((a) => <AccountAvatar key={a.id} platform={a.platform} name={a.display_name} url={a.avatar_url} size="sm" />)}
                                        {p.accounts.length === 0 && <span className="text-xs text-slate-500">Bağlı hesap yok</span>}
                                    </div>
                                    <span className="text-xs text-slate-500">{p.posts_count} gönderi</span>
                                </div>
                            </Card>
                        </Link>
                    ))}
                </div>
            )}
            <ProjectFormModal open={open} onClose={() => setOpen(false)} project={null} accounts={accounts} types={types} />
        </AppLayout>
    );
}
