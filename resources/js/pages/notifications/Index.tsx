import { router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Bell, CheckCheck, CheckCircle2, Info, Plus, Trash2, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { FilterBar, SegmentedControl } from '@/components/shared/Filters';
import { Pagination } from '@/components/shared/Pagination';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { FieldError, Input, Label, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { cn, formatNumber, formatRelative } from '@/lib/utils';
import type { Account, Paginated, SharedProps } from '@/types';

interface Notif { id: number; type: string; level: string; title: string; body: string | null; url: string | null; read_at: string | null; created_at: string }
interface Rule { id: number; type: string; type_label: string; threshold: number | null; minutes_before: number | null; is_active: boolean; account: Account | null; last_triggered_at: string | null }
interface Props { notifications: Paginated<Notif>; filter: string | null; rules: Rule[]; ruleTypes: Record<string, string>; accounts: Account[] }

const ICON = { success: CheckCircle2, error: XCircle, warning: AlertTriangle, info: Info } as const;
const COLOR = { success: 'text-emerald-400', error: 'text-rose-400', warning: 'text-amber-400', info: 'text-brand-300' } as const;

function RuleModal({ open, onClose, ruleTypes, accounts }: { open: boolean; onClose: () => void; ruleTypes: Record<string, string>; accounts: Account[] }) {
    const form = useForm({ type: 'views_threshold', social_account_id: '', threshold: '', minutes_before: '30' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            type: d.type,
            social_account_id: d.social_account_id ? Number(d.social_account_id) : null,
            threshold: d.type === 'schedule_reminder' ? null : Number(d.threshold) || null,
            minutes_before: d.type === 'schedule_reminder' ? Number(d.minutes_before) : null,
        }));
        form.post('/notification-rules', { preserveScroll: true, onSuccess: () => { onClose(); form.reset(); } });
    };
    return (
        <Modal open={open} onClose={onClose} title="Bildirim kuralı ekle" footer={<><Button variant="secondary" onClick={onClose}>Vazgeç</Button><Button type="submit" form="rule-form" loading={form.processing}>Ekle</Button></>}>
            <form id="rule-form" onSubmit={submit} className="space-y-3">
                <div>
                    <Label>Kural</Label>
                    <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>{Object.entries(ruleTypes).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</Select>
                </div>
                {form.data.type !== 'schedule_reminder' && (
                    <>
                        <div>
                            <Label>Hesap {form.data.type === 'views_threshold' && '(boş = tüm hesaplar)'}</Label>
                            <Select value={form.data.social_account_id} onChange={(e) => form.setData('social_account_id', e.target.value)} invalid={!!form.errors.social_account_id}>
                                <option value="">{form.data.type === 'views_threshold' ? 'Tüm hesaplar' : 'Seçin'}</option>
                                {accounts.map((a) => <option key={a.id} value={a.id}>{a.platform_label} · @{a.handle}</option>)}
                            </Select>
                            <FieldError message={form.errors.social_account_id} />
                        </div>
                        <div>
                            <Label>{form.data.type === 'views_threshold' ? 'Görüntülenme eşiği' : 'Takipçi/abone hedefi'}</Label>
                            <Input type="number" min={1} value={form.data.threshold} onChange={(e) => form.setData('threshold', e.target.value)} invalid={!!form.errors.threshold} />
                            <FieldError message={form.errors.threshold} />
                        </div>
                    </>
                )}
                {form.data.type === 'schedule_reminder' && (
                    <div>
                        <Label>Kaç dakika önce</Label>
                        <Input type="number" min={5} max={1440} value={form.data.minutes_before} onChange={(e) => form.setData('minutes_before', e.target.value)} invalid={!!form.errors.minutes_before} />
                        <FieldError message={form.errors.minutes_before} />
                    </div>
                )}
            </form>
        </Modal>
    );
}

export default function NotificationsIndex({ notifications, filter, rules, ruleTypes, accounts }: Props) {
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [ruleOpen, setRuleOpen] = useState(false);

    return (
        <AppLayout title="Bildirimler" description="Panel içi bildirimler; okundu durumu her kullanıcıya özeldir."
            actions={
                <>
                    <Button variant="secondary" onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}><CheckCheck className="size-4" /> Tümünü okundu yap</Button>
                    <Button variant="ghost" onClick={() => router.delete('/notifications/read', { preserveScroll: true })}><Trash2 className="size-4" /> Okunanları sil</Button>
                </>
            }>
            <div className="grid gap-4 xl:grid-cols-[1fr_360px]">
                <div>
                    <FilterBar>
                        <SegmentedControl value={filter ?? 'all'} onChange={(v) => router.get('/notifications', v === 'all' ? {} : { filter: v }, { preserveState: true, replace: true })}
                            options={[{ value: 'all', label: 'Tümü' }, { value: 'unread', label: 'Okunmamış' }, { value: 'error', label: 'Hatalar' }, { value: 'warning', label: 'Uyarılar' }, { value: 'success', label: 'Başarılar' }]} />
                    </FilterBar>
                    <Card>
                        {notifications.data.length === 0 ? <EmptyState icon={Bell} title="Bildirim yok" /> : (
                            <ul className="divide-y divide-surface-800">
                                {notifications.data.map((n) => {
                                    const Icon = ICON[n.level as keyof typeof ICON] ?? Info;
                                    return (
                                        <li key={n.id} className={cn('flex gap-3 px-5 py-3.5', !n.read_at && 'bg-brand-500/[0.04]')}>
                                            <Icon className={cn('mt-0.5 size-4 shrink-0', COLOR[n.level as keyof typeof COLOR] ?? 'text-slate-400')} aria-label={n.level} />
                                            <a href={`/notifications/${n.id}/open`} className="min-w-0 flex-1">
                                                <p className={cn('text-sm', n.read_at ? 'text-slate-300' : 'font-medium text-white')}>{n.title}</p>
                                                {n.body && <p className="mt-0.5 text-xs text-slate-400">{n.body}</p>}
                                                <p className="mt-1 text-[11px] text-slate-500">{formatRelative(n.created_at)}</p>
                                            </a>
                                            <button onClick={() => router.patch(`/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true })} className="self-start text-[11px] text-slate-500 hover:text-white">
                                                {n.read_at ? 'Okunmadı' : 'Okundu'}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        <Pagination data={notifications} noun="bildirim" />
                    </Card>
                </div>
                <Card className="self-start">
                    <CardHeader title="Bildirim kuralları" description="Hedef ve hatırlatma bildirimleri" action={isAdmin && <Button size="sm" onClick={() => setRuleOpen(true)}><Plus className="size-3.5" /> Ekle</Button>} />
                    {rules.length === 0 ? <p className="px-5 py-6 text-center text-xs text-slate-500">Kural yok</p> : (
                        <ul className="divide-y divide-surface-800">
                            {rules.map((r) => (
                                <li key={r.id} className="px-5 py-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-sm text-white">{r.type_label}</p>
                                            <p className="text-xs text-slate-500">
                                                {r.minutes_before !== null && `${r.minutes_before} dk önce`}
                                                {r.threshold !== null && `Eşik: ${formatNumber(r.threshold)}`}
                                                {r.account ? ` · @${r.account.handle}` : r.type === 'views_threshold' ? ' · tüm hesaplar' : ''}
                                            </p>
                                            {r.last_triggered_at && <p className="text-[11px] text-slate-500">Son tetiklenme: {formatRelative(r.last_triggered_at)}</p>}
                                        </div>
                                        <Badge tone={r.is_active ? 'success' : 'neutral'}>{r.is_active ? 'Aktif' : 'Kapalı'}</Badge>
                                    </div>
                                    {isAdmin && (
                                        <div className="mt-2 flex gap-1">
                                            <Button size="sm" variant="ghost" onClick={() => router.patch(`/notification-rules/${r.id}`, {}, { preserveScroll: true })}>{r.is_active ? 'Kapat' : 'Aç'}</Button>
                                            <Button size="sm" variant="ghost" onClick={() => router.delete(`/notification-rules/${r.id}`, { preserveScroll: true })}>Sil</Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="border-t border-surface-800 px-5 py-3 text-[11px] text-slate-500">
                        Otomatik bildirimler: yayın başarılı/başarısız, manuel paylaşım gerekli, API bağlantısı koptu, hesap yeniden bağlanmalı, API kotası doldu, belirgin takipçi kaybı.
                    </div>
                </Card>
            </div>
            {isAdmin && <RuleModal open={ruleOpen} onClose={() => setRuleOpen(false)} ruleTypes={ruleTypes} accounts={accounts} />}
        </AppLayout>
    );
}
