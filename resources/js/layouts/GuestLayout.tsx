import { Head, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { useToast } from '@/components/ui/Toast';
import type { SharedProps } from '@/types';

export default function GuestLayout({ title, children }: { title: string; children: ReactNode }) {
    const { flash } = usePage<SharedProps>().props;
    const { notify } = useToast();

    useEffect(() => {
        if (flash?.success) notify('success', flash.success);
        if (flash?.error) notify('error', flash.error);
    }, [flash, notify]);

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-surface-950 px-4 py-12">
            <Head title={title} />
            <div className="pointer-events-none absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-brand-600/20 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-40 -left-20 size-[28rem] rounded-full bg-fuchsia-600/10 blur-3xl" />
            <div className="relative w-full max-w-md animate-slide-up">{children}</div>
        </div>
    );
}
