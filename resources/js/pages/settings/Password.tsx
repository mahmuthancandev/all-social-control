import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Card, CardHeader } from '@/components/ui/Card';
import { FieldError, Input, Label } from '@/components/ui/Form';
import AppLayout from '@/layouts/AppLayout';

export default function Password() {
    const form = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
            onError: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AppLayout title="Şifre Değiştir" description="Panel hesabınızın şifresini güncelleyin.">
            <Card className="max-w-xl">
                <CardHeader title="Şifre" description="En az 8 karakter; harf ve rakam içermelidir." />
                <form onSubmit={submit} className="space-y-4 p-5">
                    <div>
                        <Label htmlFor="current_password">Mevcut şifre</Label>
                        <Input id="current_password" type="password" autoComplete="current-password" value={form.data.current_password} onChange={(e) => form.setData('current_password', e.target.value)} invalid={!!form.errors.current_password} />
                        <FieldError message={form.errors.current_password} />
                    </div>
                    <div>
                        <Label htmlFor="password">Yeni şifre</Label>
                        <Input id="password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} invalid={!!form.errors.password} />
                        <FieldError message={form.errors.password} />
                    </div>
                    <div>
                        <Label htmlFor="password_confirmation">Yeni şifre (tekrar)</Label>
                        <Input id="password_confirmation" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" loading={form.processing}>Kaydet</Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
