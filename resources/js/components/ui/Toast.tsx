import { CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ToastType = 'success' | 'error' | 'info';

interface ToastItem {
    id: number;
    type: ToastType;
    message: string;
}

interface ToastContextValue {
    notify: (type: ToastType, message: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const icons = { success: CheckCircle2, error: XCircle, info: Info };
const styles = {
    success: 'border-emerald-500/30 text-emerald-200',
    error: 'border-rose-500/30 text-rose-200',
    info: 'border-brand-500/30 text-brand-200',
};

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<ToastItem[]>([]);
    const nextId = useRef(1);

    const dismiss = useCallback((id: number) => setToasts((all) => all.filter((t) => t.id !== id)), []);

    const notify = useCallback(
        (type: ToastType, message: string) => {
            const id = nextId.current++;
            setToasts((all) => [...all.slice(-3), { id, type, message }]);
            window.setTimeout(() => dismiss(id), 4500);
        },
        [dismiss],
    );

    const value = useMemo(() => ({ notify }), [notify]);

    return (
        <ToastContext.Provider value={value}>
            {children}
            <div className="pointer-events-none fixed right-4 bottom-4 z-[60] flex w-full max-w-sm flex-col gap-2" aria-live="polite">
                {toasts.map((toast) => {
                    const Icon = icons[toast.type];
                    return (
                        <div
                            key={toast.id}
                            className={cn(
                                'pointer-events-auto flex animate-slide-up items-start gap-3 rounded-lg border bg-surface-850/95 px-4 py-3 text-sm shadow-xl shadow-black/40 backdrop-blur',
                                styles[toast.type],
                            )}
                        >
                            <Icon className="mt-0.5 size-4 shrink-0" />
                            <p className="flex-1 text-slate-100">{toast.message}</p>
                            <button onClick={() => dismiss(toast.id)} className="text-slate-400 hover:text-white" aria-label="Kapat">
                                <X className="size-4" />
                            </button>
                        </div>
                    );
                })}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastContextValue {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast, ToastProvider içinde kullanılmalı.');
    return ctx;
}
