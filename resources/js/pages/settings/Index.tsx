import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Database, Info, KeyRound, Server, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { CopyButton } from '@/components/shared/CopyButton';
import { PlatformBadge } from '@/components/shared/Platform';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { Checkbox, FieldError, Input, Label, Select } from '@/components/ui/Form';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber, formatRelative } from '@/lib/utils';
import type { PlatformKey } from '@/types';

interface SettingItem { label: string; type: string; secret: boolean; is_set: boolean; value: string | number | boolean | null }
interface Props {
    settings: Record<string, SettingItem>;
    platforms: Array<{ platform: PlatformKey; label: string; configured: boolean; notes: string[]; scopes: string[]; redirect_uri: string }>;
    quota: Array<{ platform: string; label: string; limit: number; used: number }>;
    system: { demo_data: boolean; ffmpeg: boolean; queue_pending: number; queue_failed: number; scheduler_last_run: string | null; upload_max_filesize: string; post_max_size: string; app_url: string; timezone: string };
}

const GROUPS: Array<{ platform: PlatformKey; keys: string[] }> = [
    { platform: 'youtube', keys: ['youtube_client_id', 'youtube_client_secret', 'youtube_api_key', 'youtube_daily_quota', 'youtube_upload_cost'] },
    { platform: 'instagram', keys: ['instagram_app_id', 'instagram_app_secret', 'instagram_public_media_base_url'] },
    { platform: 'tiktok', keys: ['tiktok_client_key', 'tiktok_client_secret', 'tiktok_publish_mode'] },
    { platform: 'x', keys: ['x_api_enabled', 'x_client_id', 'x_client_secret'] },
];

export default function SettingsIndex({ settings, platforms, quota, system }: Props) {
    const initial = Object.fromEntries(Object.entries(settings).map(([k, s]) => [k, s.secret ? '' : (s.value ?? '')])) as Record<string, string | number | boolean>;
    const form = useForm<Record<string, string | number | boolean>>(initial);
    const [purge, setPurge] = useState(false);
    const [xWarn, setXWarn] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.put('/settings', { preserveScroll: true, onSuccess: () => GROUPS.flatMap((g) => g.keys).filter((k) => settings[k].secret).forEach((k) => form.setData(k, '')) });
    };

    const field = (key: string) => {
        const s = settings[key];
        if (key === 'x_api_enabled') {
            return (
                <div key={key} className="sm:col-span-2">
                    <Checkbox label="X API'yi kullan (ÜCRETLİ — kullandıkça öde)" checked={Boolean(form.data[key])}
                        onChange={(e) => (e.target.checked ? setXWarn(true) : form.setData(key, false))} />
                </div>
            );
        }
        if (key === 'tiktok_publish_mode') {
            return (
                <div key={key}>
                    <Label>{s.label}</Label>
                    <Select value={String(form.data[key] ?? 'inbox')} onChange={(e) => form.setData(key, e.target.value)}>
                        <option value="inbox">Gelen kutusuna taslak (video.upload) — önerilen</option>
                        <option value="direct">Doğrudan yayın (video.publish, onay gerekir)</option>
                    </Select>
                </div>
            );
        }
        return (
            <div key={key}>
                <Label htmlFor={key}>{s.label} {s.secret && (s.is_set ? <Badge tone="success">kayıtlı</Badge> : <Badge>boş</Badge>)}</Label>
                <Input id={key} type={s.secret ? 'password' : s.type === 'int' ? 'number' : 'text'} autoComplete="off"
                    placeholder={s.secret && s.is_set ? '•••••••• (değiştirmek için yazın)' : ''}
                    value={String(form.data[key] ?? '')} onChange={(e) => form.setData(key, e.target.value)} invalid={!!form.errors[key]} />
                <FieldError message={form.errors[key]} />
                {s.secret && s.is_set && (
                    <button type="button" className="mt-1 text-[11px] text-slate-500 hover:text-rose-300" onClick={() => router.put('/settings', { clear: [key] }, { preserveScroll: true })}>Kaldır</button>
                )}
            </div>
        );
    };

    return (
        <AppLayout title="Ayarlar" description="API bağlantıları, kota, demo veriler ve sistem durumu. Gizli değerler şifreli saklanır ve ekranda gösterilmez.">
            <div className="grid gap-4 xl:grid-cols-[1fr_340px]">
                <form onSubmit={submit} className="space-y-4">
                    {GROUPS.map((g) => {
                        const p = platforms.find((x) => x.platform === g.platform)!;
                        return (
                            <Card key={g.platform}>
                                <CardHeader title={<PlatformBadge platform={g.platform} label={p.label} className="text-sm" />}
                                    action={p.configured ? <Badge tone="success"><CheckCircle2 className="size-3" /> Yapılandırıldı</Badge> : <Badge>Yapılandırılmadı</Badge>} />
                                <div className="space-y-4 p-5">
                                    <ul className="space-y-1">{p.notes.map((n, i) => <li key={i} className="flex gap-2 text-xs text-slate-400"><Info className="mt-0.5 size-3 shrink-0" />{n}</li>)}</ul>
                                    <div className="grid gap-4 sm:grid-cols-2">{g.keys.map(field)}</div>
                                    {g.platform !== 'x' || form.data.x_api_enabled ? (
                                        <div className="rounded-lg bg-surface-850 p-3 text-xs">
                                            <p className="mb-1 text-slate-400">Yönlendirme (Redirect) adresi — platformun geliştirici panelinde birebir girin:</p>
                                            <div className="flex items-center gap-2"><code className="min-w-0 flex-1 truncate text-slate-200">{p.redirect_uri}</code><CopyButton text={p.redirect_uri} label="" variant="ghost" /></div>
                                            {p.scopes.length > 0 && <p className="mt-2 break-all text-slate-500">İstenen izinler: {p.scopes.map((s) => s.replace('https://www.googleapis.com/auth/', '')).join(', ')}</p>}
                                        </div>
                                    ) : null}
                                </div>
                            </Card>
                        );
                    })}
                    <Card>
                        <CardHeader title="Gelişmiş" />
                        <div className="grid gap-4 p-5 sm:grid-cols-2">
                            {field('oauth_redirect_base_url')}
                            <p className="text-xs text-slate-500 sm:pt-7">Instagram ve TikTok yalnızca https yönlendirme kabul eder. Projedeki <code>oauth-bridge</code> klasörünü ücretsiz GitHub Pages'e yükleyip adresini (ör. https://kullanici.github.io/socialhub-oauth) buraya girin. YouTube ve X her zaman {system.app_url} adresine döner. Paneli her zaman http://127.0.0.1:8000 ile açın.</p>
                        </div>
                    </Card>
                    <div className="sticky bottom-4 flex justify-end">
                        <Button type="submit" loading={form.processing} className="shadow-xl"><KeyRound className="size-4" /> Ayarları kaydet</Button>
                    </div>
                </form>

                <div className="space-y-4">
                    <Card>
                        <CardHeader title="API kotası (bugün)" />
                        <div className="space-y-3 p-5">
                            {quota.map((q) => (
                                <div key={q.platform}>
                                    <div className="mb-1 flex justify-between text-xs"><span className="text-slate-300">{q.label}</span><span className="text-slate-400 tabular-nums">{formatNumber(q.used)} / {formatNumber(q.limit)}</span></div>
                                    <div className="h-2 overflow-hidden rounded-full bg-surface-800" role="progressbar" aria-valuenow={q.used} aria-valuemax={q.limit}>
                                        <div className={`h-full rounded-full ${q.used / q.limit > 0.9 ? 'bg-rose-500' : q.used / q.limit > 0.7 ? 'bg-amber-500' : 'bg-brand-500'}`} style={{ width: `${Math.min(100, (q.used / q.limit) * 100)}%` }} />
                                    </div>
                                </div>
                            ))}
                            <p className="text-[11px] text-slate-500">Kota yerel olarak sayılır ve dolmadan çağrılar durdurulur. Gerçek kota Google Cloud Console'da görünür.</p>
                        </div>
                    </Card>
                    <Card>
                        <CardHeader title="Sistem durumu" />
                        <dl className="space-y-2 p-5 text-xs">
                            {[
                                ['Zamanlayıcı', system.scheduler_last_run ? `son çalışma ${formatRelative(system.scheduler_last_run)}` : 'çalışmıyor (start.bat)', !!system.scheduler_last_run],
                                ['Kuyruk (bekleyen)', String(system.queue_pending), true],
                                ['Kuyruk (başarısız)', String(system.queue_failed), system.queue_failed === 0],
                                ['FFmpeg', system.ffmpeg ? 'bulundu' : 'yok (isteğe bağlı)', system.ffmpeg],
                                ['Yükleme sınırı (php.ini)', `${system.upload_max_filesize} / post ${system.post_max_size}`, true],
                                ['Saat dilimi', system.timezone, true],
                            ].map(([k, v, ok]) => (
                                <div key={String(k)} className="flex items-center justify-between gap-3">
                                    <dt className="text-slate-400">{k}</dt>
                                    <dd className={`flex items-center gap-1 ${ok ? 'text-slate-200' : 'text-amber-300'}`}>{!ok && <AlertTriangle className="size-3" />}{v}</dd>
                                </div>
                            ))}
                        </dl>
                        <p className="flex gap-1.5 border-t border-surface-800 px-5 py-3 text-[11px] text-slate-500"><Server className="mt-0.5 size-3 shrink-0" />Bilgisayar kapalıyken zamanlanmış paylaşımlar ve senkronizasyon çalışmaz.</p>
                    </Card>
                    <Card>
                        <CardHeader title="Demo veriler" description="Gerçek verilerden tamamen ayrı (is_demo)" />
                        <div className="space-y-3 p-5 text-sm">
                            <p className="text-slate-400">{system.demo_data ? 'Demo veriler yüklü. Tüm demo kayıtlar DEMO etiketiyle gösterilir.' : 'Demo veri yok.'}</p>
                            {system.demo_data ? (
                                <Button variant="danger" onClick={() => setPurge(true)}><Trash2 className="size-4" /> Demo verileri sil</Button>
                            ) : (
                                <Button variant="secondary" onClick={() => router.post('/settings/demo', {}, { preserveScroll: true })}><Database className="size-4" /> Demo verileri oluştur</Button>
                            )}
                        </div>
                    </Card>
                </div>
            </div>
            <ConfirmDialog open={purge} onClose={() => setPurge(false)} title="Demo verileri sil" confirmLabel="Sil" onConfirm={() => { setPurge(false); router.delete('/settings/demo', { preserveScroll: true }); }}>
                Yalnızca DEMO işaretli projeler, hesaplar, gönderiler, medya ve ölçümler silinir. Gerçek verileriniz etkilenmez.
            </ConfirmDialog>
            <ConfirmDialog open={xWarn} onClose={() => setXWarn(false)} title="X API ücretlidir" confirmLabel="Anladım, aç" danger={false} onConfirm={() => { form.setData('x_api_enabled', true); setXWarn(false); }}>
                <p>X, Şubat 2026'dan beri yeni geliştiricilere ücretsiz API katmanı sunmuyor; her istek önceden satın alınan krediden düşer.</p>
                <p className="mt-2">SocialHub'ın sıfır maliyet hedefi için önerilen yol, X'i <b>Paylaşım Asistanı</b> ve manuel/CSV metriklerle kullanmaktır. Yalnızca ücretlendirmeyi kabul ediyorsanız açın.</p>
            </ConfirmDialog>
        </AppLayout>
    );
}
