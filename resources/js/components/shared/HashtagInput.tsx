import { X } from 'lucide-react';
import { useState, type KeyboardEvent } from 'react';

export function HashtagInput({ value, onChange, placeholder = '#etiket yazıp Enter', id }: { value: string[]; onChange: (v: string[]) => void; placeholder?: string; id?: string }) {
    const [draft, setDraft] = useState('');

    const add = (raw: string) => {
        const tags = raw.split(/[\s,]+/).map((t) => t.replace(/^#+/, '').trim()).filter((t) => /^[\p{L}\p{N}_]+$/u.test(t));
        if (tags.length) onChange(Array.from(new Set([...value, ...tags])));
        setDraft('');
    };

    const onKey = (e: KeyboardEvent<HTMLInputElement>) => {
        if (['Enter', ',', ' '].includes(e.key)) {
            e.preventDefault();
            add(draft);
        } else if (e.key === 'Backspace' && draft === '' && value.length) {
            onChange(value.slice(0, -1));
        }
    };

    return (
        <div className="flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-surface-700 bg-surface-900 px-2 py-1.5 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/40">
            {value.map((t) => (
                <span key={t} className="inline-flex items-center gap-1 rounded-md bg-brand-500/15 px-2 py-0.5 text-xs text-brand-200">
                    #{t}
                    <button type="button" onClick={() => onChange(value.filter((x) => x !== t))} aria-label={`${t} etiketini kaldır`}><X className="size-3" /></button>
                </span>
            ))}
            <input id={id} value={draft} onChange={(e) => setDraft(e.target.value)} onKeyDown={onKey} onBlur={() => draft && add(draft)}
                placeholder={value.length ? '' : placeholder} className="min-w-28 flex-1 bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none" />
        </div>
    );
}
