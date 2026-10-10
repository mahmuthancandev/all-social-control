import { forwardRef, type InputHTMLAttributes, type LabelHTMLAttributes, type SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const fieldBase =
    'w-full rounded-lg border bg-surface-900 px-3 text-sm text-slate-100 placeholder:text-slate-500 transition-colors focus:outline-none focus:ring-2 focus:ring-brand-500/40 disabled:opacity-60';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement> & { invalid?: boolean }>(
    function Input({ className, invalid, ...props }, ref) {
        return (
            <input
                ref={ref}
                className={cn(
                    fieldBase,
                    'h-10',
                    invalid ? 'border-rose-500/70' : 'border-surface-700 focus:border-brand-500',
                    className,
                )}
                aria-invalid={invalid || undefined}
                {...props}
            />
        );
    },
);

export function Select({ className, invalid, children, ...props }: SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
    return (
        <select
            className={cn(fieldBase, 'h-10', invalid ? 'border-rose-500/70' : 'border-surface-700 focus:border-brand-500', className)}
            aria-invalid={invalid || undefined}
            {...props}
        >
            {children}
        </select>
    );
}

export function Label({ className, ...props }: LabelHTMLAttributes<HTMLLabelElement>) {
    return <label className={cn('mb-1.5 block text-sm font-medium text-slate-300', className)} {...props} />;
}

export function FieldError({ message }: { message?: string }) {
    if (!message) return null;
    return <p className="mt-1.5 text-xs text-rose-400">{message}</p>;
}

export function Checkbox({ label, className, ...props }: InputHTMLAttributes<HTMLInputElement> & { label: string }) {
    return (
        <label className={cn('inline-flex cursor-pointer items-center gap-2 text-sm text-slate-300', className)}>
            <input
                type="checkbox"
                className="size-4 rounded border-surface-600 bg-surface-900 accent-brand-500"
                {...props}
            />
            {label}
        </label>
    );
}
