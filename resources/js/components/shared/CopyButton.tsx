import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';

export function CopyButton({ text, label = 'Kopyala', size = 'sm', variant = 'secondary' }: { text: string; label?: string; size?: 'sm' | 'md'; variant?: 'primary' | 'secondary' | 'ghost' }) {
    const [done, setDone] = useState(false);
    const { notify } = useToast();

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        setDone(true);
        notify('success', 'Panoya kopyalandı.');
        window.setTimeout(() => setDone(false), 1500);
    };

    return (
        <Button type="button" size={size} variant={variant} onClick={copy}>
            {done ? <Check className="size-3.5" /> : <Copy className="size-3.5" />} {label}
        </Button>
    );
}
