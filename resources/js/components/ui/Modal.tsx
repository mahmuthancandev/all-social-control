import { X } from 'lucide-react';
import { useEffect, useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title: string;
    description?: string;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
}

const sizes = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' };

export function Modal({ open, onClose, title, description, children, footer, size = 'md' }: ModalProps) {
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        panelRef.current?.querySelector<HTMLElement>('input, select, textarea, button')?.focus();
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = previous;
        };
    }, [open, onClose]);

    if (!open) return null;

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-label={title}>
            <div className="absolute inset-0 animate-fade-in bg-black/60 backdrop-blur-sm" onClick={onClose} />
            <div
                ref={panelRef}
                className={cn(
                    'relative w-full animate-slide-up rounded-xl border border-surface-700 bg-surface-900 shadow-2xl shadow-black/50',
                    sizes[size],
                )}
            >
                <div className="flex items-start justify-between gap-4 border-b border-surface-700 px-5 py-4">
                    <div>
                        <h2 className="text-base font-semibold text-white">{title}</h2>
                        {description && <p className="mt-1 text-sm text-slate-400">{description}</p>}
                    </div>
                    <button onClick={onClose} className="rounded-md p-1 text-slate-400 hover:bg-surface-800 hover:text-white" aria-label="Kapat">
                        <X className="size-4" />
                    </button>
                </div>
                <div className="max-h-[70vh] overflow-y-auto px-5 py-4">{children}</div>
                {footer && <div className="flex justify-end gap-2 border-t border-surface-700 px-5 py-3">{footer}</div>}
            </div>
        </div>,
        document.body,
    );
}
