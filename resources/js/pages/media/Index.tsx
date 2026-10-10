import { router, usePage } from '@inertiajs/react';
import { Download, Film, FolderOpen, HardDrive, Images, PenSquare, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FilterBar } from '@/components/shared/Filters';
import { MediaThumb } from '@/components/shared/MediaThumb';
import { Pagination } from '@/components/shared/Pagination';
import { UploadButton } from '@/components/shared/UploadButton';
import { Badge, DemoBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input, Label, Select } from '@/components/ui/Form';
import { Modal } from '@/components/ui/Modal';
import AppLayout from '@/layouts/AppLayout';
import { buildQuery, formatBytes, formatDateTime, formatDuration } from '@/lib/utils';
import type { MediaItem, Paginated, SharedProps } from '@/types';

type Row = MediaItem & { usage_count: number; is_unused: boolean; project_name: string | null };

interface Props {
    media: Paginated<Row>;
    filters: { search?: string; project?: string; type?: string; folder?: string; usage?: string };
    projects: Array<{ id: number; name: string }>;
    folders: string[];
    stats: { total: number; total_size: number; unused: number };
    limits: { video_mb: number; image_mb: number; php_upload_max: string; php_post_max: string };
    ffmpeg: boolean;
}

function PreviewModal({ item, onClose, projects, folders, isAdmin }: { item: Row | null; onClose: () => void; projects: Props['projects']; folders: string[]; isAdmin: boolean }) {
    const [project, setProject] = useState('');
    const [folder, setFolder] = useState('');
    const [confirm, setConfirm] = useState(false);
    useEffect(() => {
        setProject(item?.project_id ? String(item.project_id) : '');
        setFolder(item?.folder ?? '');
        setConfirm(false);
    }, [item]);
    if (!item) return null;

    const realFile = !(item.is_demo && item.type === 'video');

    return (
        <>
            <Modal open onClose={onClose} size="lg" title={item.original_name} description={`${formatBytes(item.size_bytes)} · ${item.mime_type}${item.width ? ` · ${item.width}×${item.height}` : ''}${item.duration_seconds ? ` · ${formatDuration(item.duration_seconds)}` : ''}`}>
                <div className="overflow-hidden rounded-lg bg-black">
                    {item.type === 'video' && realFile ? (
                        <video src={item.url} controls preload="metadata" poster={item.thumbnail_url ?? undefined} className="max-h-[50vh] w-full" />
                    ) : item.thumbnail_url ? (
                        <img src={item.type === 'image' ? item.url : item.thumbnail_url} alt={item.original_name} className="mx-auto max-h-[50vh]" />
                    ) : null}
                </div>
                {!realFile && <p className="mt-2 text-xs text-fuchsia-300">DEMO medya: video dosyası yok, yalnızca kapak gösteriliyor.</p>}
                <div className="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                    {item.usage_count > 0 ? <Badge tone="brand">{item.usage_count} gönderide kullanılıyor</Badge> : <Badge>Kullanılmıyor</Badge>}
                    <span>Yüklendi: {formatDateTime(item.created_at)}</span>
                </div>
                {isAdmin && (
                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label>Proje</Label>
                            <Select value={project} onChange={(e) => setProject(e.target.value)}>
                                <option value="">Proje yok</option>
                                {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </Select>
                        </div>
                        <div>
                            <Label>Klasör</Label>
                            <Input list="media-folders" value={folder} onChange={(e) => setFolder(e.target.value)} placeholder="ör. Fragmanlar" />
                            <datalist id="media-folders">{folders.map((f) => <option key={f} value={f} />)}</datalist>
                        </div>
                    </div>
                )}
                <div className="mt-5 flex flex-wrap justify-between gap-2">
                    <div className="flex gap-2">
                        {realFile && <a href={item.download_url}><Button variant="secondary"><Download className="size-4" /> İndir</Button></a>}
                        {isAdmin && <a href={`/posts/create?media=${item.id}`}><Button variant="secondary"><PenSquare className="size-4" /> Gönderide kullan</Button></a>}
                    </div>
                    {isAdmin && (
                        <div className="flex gap-2">
                            <Button variant="ghost" className="text-rose-300" onClick={() => setConfirm(true)}><Trash2 className="size-4" /> Sil</Button>
                            <Button onClick={() => router.put(`/media/${item.id}`, { project_id: project ? Number(project) : null, folder: folder || null }, { preserveScroll: true, onSuccess: onClose })}>Kaydet</Button>
                        </div>
                    )}
                </div>
            </Modal>
            <ConfirmDialog open={confirm} onClose={() => setConfirm(false)} title="Medyayı sil" confirmLabel={item.usage_count > 0 ? 'Zorla sil' : 'Sil'}
                onConfirm={() => router.delete(`/media/${item.id}${item.usage_count > 0 ? '?force=1' : ''}`, { preserveScroll: true, onSuccess: onClose })}>
                {item.usage_count > 0 ? <>Bu dosya <b>{item.usage_count} gönderide</b> kullanılıyor. Silerseniz o gönderilerden de kaldırılır.</> : 'Dosya diskten kalıcı olarak silinecek.'}
            </ConfirmDialog>
        </>
    );
}

export default function MediaIndex(props: Props) {
    const { media, filters, stats } = props;
    const isAdmin = usePage<SharedProps>().props.auth.user!.is_admin;
    const [search, setSearch] = useState(filters.search ?? '');
    const [preview, setPreview] = useState<Row | null>(null);
    const go = (patch: Record<string, string | undefined>) => router.get('/media', buildQuery({ ...filters, ...patch }), { preserveState: true, replace: true });

    useEffect(() => {
        if (search === (filters.search ?? '')) return;
        const t = window.setTimeout(() => go({ search }), 300);
        return () => window.clearTimeout(t);
    }, [search]);

    return (
        <AppLayout title="Medya Kütüphanesi" description="Videolar, görseller ve kapaklar bu bilgisayarın yerel diskinde (storage/app/media) saklanır."
            actions={isAdmin && <UploadButton variant="primary" projectId={filters.project ? Number(filters.project) : null} folder={filters.folder} />}>
            <div className="mb-5 grid grid-cols-3 gap-3">
                <Card className="flex items-center gap-3 p-4"><Images className="size-5 text-slate-400" /><div><p className="text-xs text-slate-500">Dosya</p><p className="font-semibold text-white">{stats.total}</p></div></Card>
                <Card className="flex items-center gap-3 p-4"><HardDrive className="size-5 text-slate-400" /><div><p className="text-xs text-slate-500">Toplam boyut</p><p className="font-semibold text-white">{formatBytes(stats.total_size)}</p></div></Card>
                <button onClick={() => go({ usage: filters.usage === 'unused' ? undefined : 'unused' })} className="text-left"><Card className="flex items-center gap-3 p-4 hover:border-surface-600"><FolderOpen className="size-5 text-slate-400" /><div><p className="text-xs text-slate-500">Kullanılmayan</p><p className="font-semibold text-white">{stats.unused}</p></div></Card></button>
            </div>

            <FilterBar>
                <div className="relative w-full sm:w-60">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-500" />
                    <Input className="h-9 pl-9" placeholder="Dosya adı…" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
                <Select className="h-9 w-auto" value={filters.type ?? ''} onChange={(e) => go({ type: e.target.value || undefined })}>
                    <option value="">Tüm türler</option><option value="video">Videolar</option><option value="image">Görseller</option>
                </Select>
                <Select className="h-9 w-auto" value={filters.project ?? ''} onChange={(e) => go({ project: e.target.value || undefined })}>
                    <option value="">Tüm projeler</option>
                    {props.projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.folder ?? ''} onChange={(e) => go({ folder: e.target.value || undefined })}>
                    <option value="">Tüm klasörler</option>
                    {props.folders.map((f) => <option key={f} value={f}>{f}</option>)}
                </Select>
                <Select className="h-9 w-auto" value={filters.usage ?? ''} onChange={(e) => go({ usage: e.target.value || undefined })}>
                    <option value="">Tüm kullanım</option><option value="unused">Kullanılmayan</option><option value="shared">Birden fazla gönderide</option>
                </Select>
            </FilterBar>

            <Card>
                {media.data.length === 0 ? (
                    <EmptyState icon={Film} title="Medya yok" description={`Video (en fazla ${props.limits.video_mb} MB) veya görsel (en fazla ${props.limits.image_mb} MB) yükleyin.`} action={isAdmin && <UploadButton variant="primary" />} />
                ) : (
                    <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                        {media.data.map((m) => (
                            <button key={m.id} onClick={() => setPreview(m)} className="group text-left">
                                <MediaThumb media={m} className="aspect-square ring-1 ring-surface-700 transition group-hover:ring-brand-500/60" />
                                <p className="mt-1.5 truncate text-xs text-slate-200">{m.original_name}</p>
                                <p className="flex items-center gap-1.5 text-[11px] text-slate-500">
                                    {formatBytes(m.size_bytes)}
                                    {m.is_unused ? <span className="text-amber-400/80">· kullanılmıyor</span> : <span>· {m.usage_count} gönderi</span>}
                                </p>
                                {m.project_name && <p className="truncate text-[11px] text-slate-500">{m.project_name}{m.folder && ` / ${m.folder}`}</p>}
                            </button>
                        ))}
                    </div>
                )}
                <Pagination data={media} noun="dosya" />
            </Card>

            <p className="mt-4 text-xs text-slate-500">
                İzin verilen: MP4, MOV, WEBM, M4V, JPG, PNG, WEBP, GIF · Dosya türü içerikten doğrulanır · Sunucu sınırı: {props.limits.php_upload_max} (php.ini) ·
                FFmpeg: {props.ffmpeg ? 'bulundu — video kapakları ve süreleri otomatik üretilir' : 'bulunamadı — video kapağı üretilmez (isteğe bağlı, ücretsiz)'}
                {stats.total > 0 && ' · '}{media.data.some((m) => m.is_demo) && <DemoBadge />}
            </p>

            <PreviewModal item={preview} onClose={() => setPreview(null)} projects={props.projects} folders={props.folders} isAdmin={isAdmin} />
        </AppLayout>
    );
}
