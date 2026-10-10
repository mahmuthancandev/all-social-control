import { useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { Button } from '@/components/ui/Button';
import { Checkbox, FieldError, Input, Label, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import { AccountAvatar } from '@/components/shared/Platform';
import type { Account } from '@/types';

export interface ProjectFormValue {
    id?: number;
    name: string;
    type: string;
    category: string | null;
    description: string | null;
    color: string;
    logo_url?: string | null;
    account_ids: number[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    project: ProjectFormValue | null;
    accounts: Account[];
    types: Record<string, string>;
}

export function ProjectFormModal({ open, onClose, project, accounts, types }: Props) {
    const form = useForm<{ name: string; type: string; category: string; description: string; color: string; logo: File | null; remove_logo: boolean; account_ids: number[] }>({
        name: '', type: 'game', category: '', description: '', color: '#6366f1', logo: null, remove_logo: false, account_ids: [],
    });

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData({
            name: project?.name ?? '',
            type: project?.type ?? 'game',
            category: project?.category ?? '',
            description: project?.description ?? '',
            color: project?.color ?? '#6366f1',
            logo: null,
            remove_logo: false,
            account_ids: project?.account_ids ?? [],
        });
    }, [open, project]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, forceFormData: true, onSuccess: () => onClose() };
        if (project?.id) form.post(`/projects/${project.id}`, opts);
        else form.post('/projects', opts);
    };

    const toggle = (id: number) =>
        form.setData('account_ids', form.data.account_ids.includes(id) ? form.data.account_ids.filter((x) => x !== id) : [...form.data.account_ids, id]);

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={project?.id ? 'Projeyi düzenle' : 'Yeni proje'}
            description="Oyun veya uygulamanızı oluşturup sosyal medya hesaplarını bağlayın."
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Vazgeç</Button>
                    <Button type="submit" form="project-form" loading={form.processing}>{project?.id ? 'Kaydet' : 'Oluştur'}</Button>
                </>
            }
        >
            <form id="project-form" onSubmit={submit} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="p-name">Proje adı</Label>
                        <Input id="p-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} invalid={!!form.errors.name} />
                        <FieldError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-[1fr_auto] gap-3">
                        <div>
                            <Label htmlFor="p-type">Tür</Label>
                            <Select id="p-type" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                {Object.entries(types).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="p-color">Renk</Label>
                            <input id="p-color" type="color" value={form.data.color} onChange={(e) => form.setData('color', e.target.value)} className="h-10 w-14 cursor-pointer rounded-lg border border-surface-700 bg-surface-900 p-1" />
                        </div>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="p-cat">Kategori</Label>
                        <Input id="p-cat" placeholder="ör. Yarış, RPG, Fitness" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="p-logo">Logo (PNG/JPG/WEBP, en fazla 2 MB)</Label>
                        <input id="p-logo" type="file" accept="image/png,image/jpeg,image/webp" onChange={(e) => form.setData('logo', e.target.files?.[0] ?? null)}
                            className="block w-full text-xs text-slate-400 file:mr-3 file:rounded-md file:border-0 file:bg-surface-700 file:px-3 file:py-2 file:text-xs file:text-white" />
                        <FieldError message={form.errors.logo} />
                        {project?.logo_url && <Checkbox className="mt-2" label="Mevcut logoyu kaldır" checked={form.data.remove_logo} onChange={(e) => form.setData('remove_logo', e.target.checked)} />}
                    </div>
                </div>
                <div>
                    <Label htmlFor="p-desc">Açıklama</Label>
                    <textarea id="p-desc" rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)}
                        className="w-full rounded-lg border border-surface-700 bg-surface-900 px-3 py-2 text-sm text-slate-100 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/40 focus:outline-none" />
                </div>
                <div>
                    <Label>Bağlı sosyal medya hesapları</Label>
                    {accounts.length === 0 ? (
                        <p className="text-xs text-slate-500">Henüz hesap yok. Önce Sosyal Medya Hesapları sayfasından hesap ekleyin.</p>
                    ) : (
                        <div className="grid max-h-56 gap-2 overflow-y-auto sm:grid-cols-2">
                            {accounts.map((a) => (
                                <label key={a.id} className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2 ${form.data.account_ids.includes(a.id) ? 'border-brand-500/60 bg-brand-500/10' : 'border-surface-700 bg-surface-850'}`}>
                                    <input type="checkbox" className="size-4 accent-brand-500" checked={form.data.account_ids.includes(a.id)} onChange={() => toggle(a.id)} />
                                    <AccountAvatar platform={a.platform} name={a.display_name} url={a.avatar_url} size="sm" />
                                    <span className="min-w-0 truncate text-sm text-slate-200">@{a.handle}{a.is_demo && <span className="ml-1 text-[10px] text-fuchsia-300">DEMO</span>}</span>
                                </label>
                            ))}
                        </div>
                    )}
                </div>
            </form>
        </Modal>
    );
}
