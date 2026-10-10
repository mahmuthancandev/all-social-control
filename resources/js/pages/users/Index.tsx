import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, UserPlus, Users } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { Checkbox, FieldError, Input, Label, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { cn, formatRelative, initials } from '@/lib/utils';
import type { Paginated, SharedProps, UserRole } from '@/types';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    role_label: string;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string | null;
}

interface Props {
    users: Paginated<UserRow>;
    filters: { search: string; role: string | null };
    roles: Array<{ value: UserRole; label: string }>;
}

type FormData = {
    name: string;
    email: string;
    role: UserRole;
    password: string;
    password_confirmation: string;
    is_active: boolean;
};

const emptyForm: FormData = { name: '', email: '', role: 'viewer', password: '', password_confirmation: '', is_active: true };

function UserFormModal({ open, onClose, user, roles }: { open: boolean; onClose: () => void; user: UserRow | null; roles: Props['roles'] }) {
    const form = useForm<FormData>(emptyForm);

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData(user ? { name: user.name, email: user.email, role: user.role, password: '', password_confirmation: '', is_active: user.is_active } : emptyForm);
    }, [open, user]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };
        if (user) form.put(`/admin/users/${user.id}`, options);
        else form.post('/admin/users', options);
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={user ? 'Kullanıcıyı düzenle' : 'Yeni kullanıcı'}
            description={user ? user.email : 'Panele erişebilecek yeni bir ekip üyesi ekleyin.'}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Vazgeç</Button>
                    <Button type="submit" form="user-form" loading={form.processing}>{user ? 'Kaydet' : 'Oluştur'}</Button>
                </>
            }
        >
            <form id="user-form" onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="name">Ad soyad</Label>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} invalid={!!form.errors.name} />
                    <FieldError message={form.errors.name} />
                </div>
                <div>
                    <Label htmlFor="email">E-posta</Label>
                    <Input id="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value.toLowerCase())} invalid={!!form.errors.email} />
                    <FieldError message={form.errors.email} />
                </div>
                <div>
                    <Label htmlFor="role">Rol</Label>
                    <Select id="role" value={form.data.role} onChange={(e) => form.setData('role', e.target.value as UserRole)} invalid={!!form.errors.role}>
                        {roles.map((r) => (
                            <option key={r.value} value={r.value}>{r.label}</option>
                        ))}
                    </Select>
                    <p className="mt-1.5 text-xs text-slate-500">
                        {form.data.role === 'admin'
                            ? 'Yönetici: hesapları, paylaşımları, kullanıcıları ve ayarları yönetir.'
                            : 'İzleyici: dashboard, istatistik ve raporları yalnızca görüntüler.'}
                    </p>
                    <FieldError message={form.errors.role} />
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label htmlFor="password">{user ? 'Yeni şifre (isteğe bağlı)' : 'Şifre'}</Label>
                        <Input id="password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} invalid={!!form.errors.password} />
                        <FieldError message={form.errors.password} />
                    </div>
                    <div>
                        <Label htmlFor="password_confirmation">Şifre (tekrar)</Label>
                        <Input id="password_confirmation" type="password" autoComplete="new-password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} />
                    </div>
                </div>
                <Checkbox label="Hesap aktif (giriş yapabilir)" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} />
            </form>
        </Modal>
    );
}

export default function UsersIndex({ users, filters, roles }: Props) {
    const currentUser = usePage<SharedProps>().props.auth.user!;
    const [modalOpen, setModalOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [deleting, setDeleting] = useState<UserRow | null>(null);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const [search, setSearch] = useState(filters.search);

    useEffect(() => {
        if (search === filters.search) return;
        const t = window.setTimeout(() => {
            router.get('/admin/users', { search: search || undefined, role: filters.role || undefined }, { preserveState: true, replace: true });
        }, 300);
        return () => window.clearTimeout(t);
    }, [search, filters.search, filters.role]);

    const openCreate = () => {
        setEditing(null);
        setModalOpen(true);
    };

    const openEdit = (user: UserRow) => {
        setEditing(user);
        setModalOpen(true);
    };

    const confirmDelete = () => {
        if (!deleting) return;
        router.delete(`/admin/users/${deleting.id}`, {
            preserveScroll: true,
            onStart: () => setDeleteProcessing(true),
            onFinish: () => {
                setDeleteProcessing(false);
                setDeleting(null);
            },
        });
    };

    return (
        <AppLayout
            title="Kullanıcı Yönetimi"
            description="Panel kullanıcılarını ve rollerini yönetin."
            actions={
                <Button onClick={openCreate}>
                    <Plus className="size-4" /> Kullanıcı Ekle
                </Button>
            }
        >
            <Card>
                <div className="flex flex-col gap-3 border-b border-surface-800 p-4 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                        <Input className="pl-9" placeholder="Ad veya e-posta ara…" value={search} onChange={(e) => setSearch(e.target.value)} />
                    </div>
                    <Select
                        className="sm:w-48"
                        value={filters.role ?? ''}
                        onChange={(e) => router.get('/admin/users', { search: search || undefined, role: e.target.value || undefined }, { preserveState: true, replace: true })}
                    >
                        <option value="">Tüm roller</option>
                        {roles.map((r) => (
                            <option key={r.value} value={r.value}>{r.label}</option>
                        ))}
                    </Select>
                </div>

                {users.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Kullanıcı bulunamadı"
                        description={filters.search || filters.role ? 'Filtrelerinizi değiştirmeyi deneyin.' : 'İlk ekip üyenizi ekleyin.'}
                        action={<Button onClick={openCreate}><UserPlus className="size-4" /> Kullanıcı Ekle</Button>}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-surface-800 text-left text-xs tracking-wide text-slate-500 uppercase">
                                    <th className="px-4 py-3 font-medium">Kullanıcı</th>
                                    <th className="px-4 py-3 font-medium">Rol</th>
                                    <th className="px-4 py-3 font-medium">Durum</th>
                                    <th className="hidden px-4 py-3 font-medium md:table-cell">Son giriş</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-surface-800">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="transition-colors hover:bg-surface-850/60">
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface-700 text-xs font-semibold text-white">{initials(user.name)}</span>
                                                <div className="min-w-0">
                                                    <p className="truncate font-medium text-white">
                                                        {user.name} {user.id === currentUser.id && <span className="text-xs text-slate-500">(siz)</span>}
                                                    </p>
                                                    <p className="truncate text-xs text-slate-400">{user.email}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={user.role === 'admin' ? 'brand' : 'neutral'}>{user.role_label}</Badge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge tone={user.is_active ? 'success' : 'danger'}>{user.is_active ? 'Aktif' : 'Pasif'}</Badge>
                                        </td>
                                        <td className="hidden px-4 py-3 text-slate-400 md:table-cell">{user.last_login_at ? formatRelative(user.last_login_at) : 'Hiç giriş yapmadı'}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(user)} aria-label="Düzenle">
                                                    <Pencil className="size-3.5" />
                                                </Button>
                                                <Button variant="ghost" size="sm" onClick={() => setDeleting(user)} disabled={user.id === currentUser.id} aria-label="Sil" className="hover:text-rose-300">
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {users.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-surface-800 px-4 py-3 text-xs text-slate-400">
                        <span>
                            {users.total} kullanıcıdan {users.from}–{users.to} arası
                        </span>
                        <div className="flex gap-1">
                            {users.links.map((link, i) =>
                                link.url ? (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveState
                                        className={cn('rounded-md px-2.5 py-1', link.active ? 'bg-brand-500 text-white' : 'hover:bg-surface-800')}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <span key={i} className="px-2.5 py-1 text-slate-600" dangerouslySetInnerHTML={{ __html: link.label }} />
                                ),
                            )}
                        </div>
                    </div>
                )}
            </Card>

            <UserFormModal open={modalOpen} onClose={() => setModalOpen(false)} user={editing} roles={roles} />

            <Modal
                open={deleting !== null}
                onClose={() => setDeleting(null)}
                title="Kullanıcıyı sil"
                size="sm"
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setDeleting(null)}>Vazgeç</Button>
                        <Button variant="danger" onClick={confirmDelete} loading={deleteProcessing}>Sil</Button>
                    </>
                }
            >
                <p className="text-sm text-slate-300">
                    <span className="font-semibold text-white">{deleting?.name}</span> kalıcı olarak silinecek. Bu işlem geri alınamaz.
                </p>
            </Modal>
        </AppLayout>
    );
}
