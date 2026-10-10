import { usePage } from '@inertiajs/react';
import { Activity, CheckCircle2, Circle, Database, ShieldCheck, UserCheck, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card, CardHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber, formatRelative } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Props {
    stats: { users_total: number; admins_total: number; active_users: number };
    system: { php_version: string; laravel_version: string; database: string; queue: string; timezone: string };
    recentActivity: Array<{ id: number; action: string; user: string | null; created_at: string | null }>;
}

const actionLabels: Record<string, string> = {
    'auth.login': 'Giriş yaptı',
    'auth.logout': 'Çıkış yaptı',
    'auth.password_changed': 'Şifresini değiştirdi',
    'user.created': 'Kullanıcı oluşturdu',
    'user.updated': 'Kullanıcı güncelledi',
    'user.deleted': 'Kullanıcı sildi',
};

const roadmap: Array<{ stage: number; title: string; done: boolean }> = [
    { stage: 1, title: 'Laravel + React kurulumu, giriş sistemi, roller', done: true },
    { stage: 2, title: 'Modern dashboard ve metrik kartları', done: false },
    { stage: 3, title: 'Proje ve sosyal medya hesap yönetimi', done: false },
    { stage: 4, title: 'İçerik oluşturma, medya kütüphanesi, takvim', done: false },
    { stage: 5, title: 'YouTube / Instagram / TikTok / X entegrasyon altyapısı', done: false },
    { stage: 6, title: 'Resmi API izinleriyle yayınlama', done: false },
    { stage: 7, title: 'İstatistikler, karşılaştırmalar, senkronizasyon', done: false },
    { stage: 8, title: 'Yorumlar, bildirimler, otomasyonlar', done: false },
    { stage: 9, title: 'Testler, güvenlik, Windows kurulum scriptleri', done: false },
];

function StatCard({ icon: Icon, label, value, hint }: { icon: LucideIcon; label: string; value: number; hint: string }) {
    return (
        <Card className="p-5">
            <div className="flex items-center justify-between">
                <p className="text-sm text-slate-400">{label}</p>
                <span className="rounded-lg bg-brand-500/10 p-2 text-brand-300">
                    <Icon className="size-4" />
                </span>
            </div>
            <p className="mt-3 text-3xl font-semibold tracking-tight text-white">{formatNumber(value)}</p>
            <p className="mt-1 text-xs text-slate-500">{hint}</p>
        </Card>
    );
}

export default function Dashboard({ stats, system, recentActivity }: Props) {
    const user = usePage<SharedProps>().props.auth.user!;

    return (
        <AppLayout title="Dashboard" description={`Hoş geldin, ${user.name}. Sosyal medya metrikleri Aşama 2 ile burada görünecek.`}>
            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard icon={Users} label="Panel kullanıcıları" value={stats.users_total} hint="Toplam kayıtlı kullanıcı" />
                <StatCard icon={ShieldCheck} label="Yöneticiler" value={stats.admins_total} hint="ADMIN rolündeki kullanıcılar" />
                <StatCard icon={UserCheck} label="Aktif kullanıcılar" value={stats.active_users} hint="Giriş yapabilen hesaplar" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader title="Geliştirme yol haritası" description="Tamamlanmayan modüller menüde pasif görünür; çalışıyormuş gibi gösterilmez." />
                    <ul className="divide-y divide-surface-800">
                        {roadmap.map((item) => (
                            <li key={item.stage} className="flex items-center gap-3 px-5 py-3">
                                {item.done ? <CheckCircle2 className="size-4 text-emerald-400" /> : <Circle className="size-4 text-slate-600" />}
                                <span className="w-16 shrink-0 text-xs font-medium text-slate-500">Aşama {item.stage}</span>
                                <span className={item.done ? 'text-sm text-white' : 'text-sm text-slate-400'}>{item.title}</span>
                                {item.done && <Badge tone="success" className="ml-auto">Tamamlandı</Badge>}
                            </li>
                        ))}
                    </ul>
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Sistem durumu" />
                        <dl className="space-y-2.5 px-5 py-4 text-sm">
                            {[
                                ['PHP', system.php_version],
                                ['Laravel', system.laravel_version],
                                ['Veritabanı', system.database],
                                ['Kuyruk', system.queue],
                                ['Saat dilimi', system.timezone],
                            ].map(([k, v]) => (
                                <div key={k} className="flex justify-between gap-4">
                                    <dt className="text-slate-400">{k}</dt>
                                    <dd className="font-mono text-xs text-slate-200">{v}</dd>
                                </div>
                            ))}
                        </dl>
                        <div className="flex items-center gap-2 border-t border-surface-800 px-5 py-3 text-xs text-slate-500">
                            <Database className="size-3.5" /> Yerel SQLite — veriler yalnızca bu bilgisayarda.
                        </div>
                    </Card>

                    {user.is_admin && (
                        <Card>
                            <CardHeader title="Son etkinlikler" description="Denetim kaydı" />
                            {recentActivity.length === 0 ? (
                                <EmptyState icon={Activity} title="Henüz etkinlik yok" />
                            ) : (
                                <ul className="divide-y divide-surface-800">
                                    {recentActivity.map((log) => (
                                        <li key={log.id} className="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                            <span className="truncate text-slate-300">
                                                <span className="text-white">{log.user ?? 'Sistem'}</span> · {actionLabels[log.action] ?? log.action}
                                            </span>
                                            <span className="shrink-0 text-xs text-slate-500">{formatRelative(log.created_at)}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
