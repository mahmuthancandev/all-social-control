import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, ChevronDown, KeyRound, LogOut, Menu, X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { navigation } from '@/lib/navigation';
import { cn, initials } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface AppLayoutProps {
    title: string;
    description?: ReactNode;
    badge?: ReactNode;
    back?: { href: string; label: string };
    actions?: ReactNode;
    children: ReactNode;
}

function useFlashToasts() {
    const { flash } = usePage<SharedProps>().props;
    const { notify } = useToast();

    useEffect(() => {
        if (flash?.success) notify('success', flash.success);
        if (flash?.error) notify('error', flash.error);
    }, [flash, notify]);
}

function Logo() {
    return (
        <Link href="/dashboard" className="flex items-center gap-2.5 px-2">
            <img src="/favicon.svg" alt="" className="size-8" />
            <span className="text-base font-semibold tracking-tight text-white">SocialHub</span>
        </Link>
    );
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const { url, props } = usePage<SharedProps>();
    const isAdmin = props.auth.user?.is_admin ?? false;
    const items = navigation.filter((item) => !item.adminOnly || isAdmin);
    const path = url.split('?')[0];

    const isActive = (href: string) => {
        if (href === '/posts') return path === '/posts' || (/^\/posts\/\d+/.test(path));
        return path === href || path.startsWith(`${href}/`);
    };

    return (
        <nav className="flex h-full flex-col gap-0.5 overflow-y-auto px-3 pb-4">
            {items.map((item) => {
                const Icon = item.icon;
                const active = isActive(item.href);
                const count = item.count ? props.counts?.[item.count] ?? 0 : 0;

                return (
                    <Link
                        key={item.label}
                        href={item.href}
                        onClick={onNavigate}
                        className={cn(
                            'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                            active ? 'bg-brand-500/15 text-white ring-1 ring-brand-500/25 ring-inset' : 'text-slate-300 hover:bg-surface-800 hover:text-white',
                        )}
                    >
                        <Icon className={cn('size-4 shrink-0', active ? 'text-brand-300' : 'text-slate-400 group-hover:text-slate-200')} />
                        <span className="flex-1 truncate">{item.label}</span>
                        {count > 0 && <span className="rounded-full bg-brand-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-brand-200 tabular-nums">{count > 99 ? '99+' : count}</span>}
                    </Link>
                );
            })}
        </nav>
    );
}

function UserMenu() {
    const user = usePage<SharedProps>().props.auth.user!;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onClick = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        return () => document.removeEventListener('mousedown', onClick);
    }, []);

    return (
        <div className="relative" ref={ref}>
            <button
                onClick={() => setOpen((v) => !v)}
                className="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-surface-800"
                aria-expanded={open}
            >
                <span className="flex size-8 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-fuchsia-500 text-xs font-bold text-white">
                    {initials(user.name)}
                </span>
                <span className="hidden sm:block">
                    <span className="block text-sm leading-tight font-medium text-white">{user.name}</span>
                    <span className="block text-xs leading-tight text-slate-400">{user.role_label}</span>
                </span>
                <ChevronDown className="size-4 text-slate-400" />
            </button>
            {open && (
                <div className="absolute right-0 z-40 mt-2 w-56 animate-slide-up rounded-xl border border-surface-700 bg-surface-900 p-1.5 shadow-2xl shadow-black/50">
                    <div className="border-b border-surface-700 px-3 py-2">
                        <p className="truncate text-sm text-white">{user.email}</p>
                    </div>
                    <Link href="/settings/password" className="mt-1 flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:bg-surface-800 hover:text-white">
                        <KeyRound className="size-4" /> Şifre Değiştir
                    </Link>
                    <button
                        onClick={() => router.post('/logout')}
                        className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-rose-300 hover:bg-rose-500/10"
                    >
                        <LogOut className="size-4" /> Çıkış Yap
                    </button>
                </div>
            )}
        </div>
    );
}

export default function AppLayout({ title, description, badge, back, actions, children }: AppLayoutProps) {
    const { app, counts } = usePage<SharedProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    useFlashToasts();

    return (
        <div className="min-h-screen bg-surface-950">
            <Head title={title} />

            {/* Masaüstü sidebar */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-surface-800 bg-surface-900/60 backdrop-blur lg:flex">
                <div className="flex h-16 items-center px-4">
                    <Logo />
                </div>
                <Sidebar />
            </aside>

            {/* Mobil sidebar */}
            {mobileOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 animate-fade-in bg-black/60" onClick={() => setMobileOpen(false)} />
                    <aside className="relative flex h-full w-72 animate-slide-up flex-col border-r border-surface-800 bg-surface-900">
                        <div className="flex h-16 items-center justify-between px-4">
                            <Logo />
                            <button onClick={() => setMobileOpen(false)} className="rounded-md p-1.5 text-slate-400 hover:bg-surface-800" aria-label="Menüyü kapat">
                                <X className="size-5" />
                            </button>
                        </div>
                        <Sidebar onNavigate={() => setMobileOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-surface-800 bg-surface-950/80 px-4 backdrop-blur sm:px-6">
                    <button onClick={() => setMobileOpen(true)} className="rounded-md p-1.5 text-slate-300 hover:bg-surface-800 lg:hidden" aria-label="Menüyü aç">
                        <Menu className="size-5" />
                    </button>
                    <div className="flex flex-1 items-center gap-2">
                        {app.demo_mode && <DemoBadge />}
                        {app.demo_mode && <span className="hidden text-xs text-slate-500 md:inline">DEMO etiketli veriler simülasyondur</span>}
                    </div>
                    <Badge tone="neutral" className="hidden sm:inline-flex">{app.timezone}</Badge>
                    <Link href="/notifications" className="relative rounded-lg p-2 text-slate-300 hover:bg-surface-800 hover:text-white" aria-label="Bildirimler">
                        <Bell className="size-5" />
                        {(counts?.notifications ?? 0) > 0 && (
                            <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">
                                {counts!.notifications > 9 ? '9+' : counts!.notifications}
                            </span>
                        )}
                    </Link>
                    <UserMenu />
                </header>

                <main className="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 lg:py-8">
                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div className="min-w-0">
                            {back && (
                                <Link href={back.href} className="mb-2 inline-flex items-center gap-1 text-xs text-slate-400 hover:text-white">
                                    <ArrowLeft className="size-3.5" /> {back.label}
                                </Link>
                            )}
                            <h1 className="flex flex-wrap items-center gap-2 text-2xl font-semibold tracking-tight text-white">
                                <span className="truncate">{title}</span>
                                {badge}
                            </h1>
                            {description && <p className="mt-1 text-sm text-slate-400">{description}</p>}
                        </div>
                        {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
                    </div>
                    <div className="animate-fade-in">{children}</div>
                </main>
            </div>
        </div>
    );
}
