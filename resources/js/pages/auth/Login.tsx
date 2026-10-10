import { useForm } from '@inertiajs/react';
import { Lock, Mail, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Checkbox, FieldError, Input, Label } from '@/components/ui/Form';
import GuestLayout from '@/layouts/GuestLayout';

interface DemoAccount {
    role: string;
    email: string;
    password: string;
}

export default function Login({ demoAccounts }: { demoAccounts: DemoAccount[] }) {
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <GuestLayout title="Giriş Yap">
            <div className="mb-8 flex flex-col items-center text-center">
                <img src="/favicon.svg" alt="" className="mb-4 size-12" />
                <h1 className="text-2xl font-semibold tracking-tight text-white">SocialHub'a giriş yap</h1>
                <p className="mt-1.5 text-sm text-slate-400">Oyun ve uygulamalarınızın sosyal medya yönetim paneli</p>
            </div>

            <Card className="p-6 sm:p-8">
                <form onSubmit={submit} className="space-y-5" noValidate>
                    <div>
                        <Label htmlFor="email">E-posta</Label>
                        <div className="relative">
                            <Mail className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                autoFocus
                                className="pl-9"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                invalid={!!form.errors.email}
                                placeholder="ornek@ekibiniz.com"
                            />
                        </div>
                        <FieldError message={form.errors.email} />
                    </div>

                    <div>
                        <Label htmlFor="password">Şifre</Label>
                        <div className="relative">
                            <Lock className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                className="pl-9"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                invalid={!!form.errors.password}
                            />
                        </div>
                        <FieldError message={form.errors.password} />
                    </div>

                    <Checkbox label="Beni hatırla" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />

                    <Button type="submit" className="w-full" loading={form.processing}>
                        Giriş Yap
                    </Button>
                </form>
            </Card>

            {demoAccounts.length > 0 && (
                <Card className="mt-4 p-4">
                    <div className="mb-3 flex items-center gap-2">
                        <Badge tone="demo">Demo</Badge>
                        <span className="text-xs text-slate-400">Yerel kurulum hesapları — ilk girişten sonra şifreleri değiştirin.</span>
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {demoAccounts.map((account) => (
                            <button
                                key={account.email}
                                type="button"
                                onClick={() => form.setData({ ...form.data, email: account.email, password: account.password })}
                                className="rounded-lg border border-surface-700 bg-surface-850 px-3 py-2 text-left transition-colors hover:border-brand-500/50"
                            >
                                <span className="block text-xs font-semibold text-white">{account.role}</span>
                                <span className="block truncate text-xs text-slate-400">{account.email}</span>
                            </button>
                        ))}
                    </div>
                </Card>
            )}

            <p className="mt-6 flex items-center justify-center gap-1.5 text-xs text-slate-500">
                <ShieldCheck className="size-3.5" /> Sosyal medya şifreleriniz bu panelde istenmez veya saklanmaz.
            </p>
        </GuestLayout>
    );
}
