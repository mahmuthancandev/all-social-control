import { router } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { useToast } from '@/components/ui/Toast';

const ACCEPT = 'video/mp4,video/quicktime,video/webm,video/x-m4v,image/jpeg,image/png,image/webp,image/gif';

export function UploadButton({ projectId, folder, onDone, label = 'Dosya yükle', variant = 'secondary' }: { projectId?: number | null; folder?: string | null; onDone?: () => void; label?: string; variant?: 'primary' | 'secondary' }) {
    const ref = useRef<HTMLInputElement>(null);
    const [progress, setProgress] = useState<number | null>(null);
    const { notify } = useToast();

    const upload = (files: FileList | null) => {
        if (!files?.length) return;
        const data = new FormData();
        Array.from(files).forEach((f) => data.append('files[]', f));
        if (projectId) data.append('project_id', String(projectId));
        if (folder) data.append('folder', folder);
        router.post('/media', data, {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onProgress: (e) => setProgress(e?.percentage ?? null),
            onError: (errors) => notify('error', Object.values(errors)[0] ?? 'Yükleme başarısız.'),
            onFinish: () => {
                setProgress(null);
                if (ref.current) ref.current.value = '';
            },
            onSuccess: () => onDone?.(),
        });
    };

    return (
        <>
            <input ref={ref} type="file" multiple accept={ACCEPT} className="hidden" onChange={(e) => upload(e.target.files)} />
            <Button type="button" variant={variant} onClick={() => ref.current?.click()} loading={progress !== null}>
                <Upload className="size-4" /> {progress !== null ? `%${Math.round(progress)}` : label}
            </Button>
        </>
    );
}
