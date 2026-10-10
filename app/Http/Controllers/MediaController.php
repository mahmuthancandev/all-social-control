<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Models\PostPlatformTarget;
use App\Models\Project;
use App\Services\Media\MediaService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'project', 'type', 'folder', 'usage']);

        $media = MediaFile::query()
            ->with('project:id,name')
            ->withCount('posts')
            ->addSelect(['target_count' => PostPlatformTarget::selectRaw('count(*)')
                ->where(fn ($q) => $q->whereColumn('media_file_id', 'media_files.id')->orWhereColumn('thumbnail_media_id', 'media_files.id'))])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('original_name', 'like', "%{$s}%"))
            ->when($filters['project'] ?? null, fn ($q, $p) => $q->where('project_id', $p))
            ->when(in_array($filters['type'] ?? null, ['video', 'image'], true), fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['folder'] ?? null, fn ($q, $f) => $q->where('folder', $f))
            ->when(($filters['usage'] ?? null) === 'unused', fn ($q) => $q->doesntHave('posts')
                ->whereNotExists(fn ($s) => $s->from('post_platform_targets')->where(fn ($w) => $w->whereColumn('media_file_id', 'media_files.id')->orWhereColumn('thumbnail_media_id', 'media_files.id'))))
            ->when(($filters['usage'] ?? null) === 'shared', fn ($q) => $q->has('posts', '>', 1))
            ->latest()
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MediaFile $m) => $m->toSummary() + [
                'usage_count' => $m->posts_count,
                'is_unused' => $m->posts_count === 0 && (int) $m->target_count === 0,
                'project_name' => $m->project?->name,
            ]);

        return Inertia::render('media/Index', [
            'media' => $media,
            'filters' => $filters,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'folders' => MediaFile::whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder'),
            'stats' => [
                'total' => MediaFile::count(),
                'total_size' => (int) MediaFile::sum('size_bytes'),
                'unused' => MediaFile::doesntHave('posts')->whereNotExists(fn ($s) => $s->from('post_platform_targets')->where(fn ($w) => $w->whereColumn('media_file_id', 'media_files.id')->orWhereColumn('thumbnail_media_id', 'media_files.id')))->count(),
            ],
            'limits' => [
                'video_mb' => (int) config('socialhub.media.max_video_mb'),
                'image_mb' => (int) config('socialhub.media.max_image_mb'),
                'php_upload_max' => ini_get('upload_max_filesize'),
                'php_post_max' => ini_get('post_max_size'),
            ],
            'ffmpeg' => MediaService::ffmpegAvailable(),
        ]);
    }

    public function store(Request $request, MediaService $service): RedirectResponse
    {
        $videoKb = (int) config('socialhub.media.max_video_mb') * 1024;
        $imageKb = (int) config('socialhub.media.max_image_mb') * 1024;

        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => [
                'required', 'file',
                'mimetypes:'.implode(',', [...MediaService::VIDEO_MIMES, ...MediaService::IMAGE_MIMES]),
                'extensions:'.implode(',', [...MediaService::VIDEO_EXTENSIONS, ...MediaService::IMAGE_EXTENSIONS]),
                'max:'.$videoKb,
                function ($attribute, $file, $fail) use ($imageKb) {
                    if (str_starts_with((string) $file->getMimeType(), 'image/') && $file->getSize() > $imageKb * 1024) {
                        $fail('Görseller en fazla '.($imageKb / 1024).' MB olabilir.');
                    }
                },
            ],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'folder' => ['nullable', 'string', 'max:120'],
        ], [
            'files.*.mimetypes' => 'Desteklenmeyen dosya türü. İzin verilenler: MP4, MOV, WEBM, M4V, JPG, PNG, WEBP, GIF.',
            'files.*.extensions' => 'Desteklenmeyen dosya uzantısı.',
            'files.*.max' => 'Dosya boyutu sınırı aşıldı.',
        ], ['files' => 'dosyalar']);

        $created = [];
        foreach ($request->file('files') as $file) {
            $media = $service->store($file, $request->integer('project_id') ?: null, $request->input('folder'), $request->user());
            $created[] = $media->id;
        }

        Audit::log('media.uploaded', null, ['count' => count($created)]);

        return back()->with('success', count($created).' dosya yüklendi.')->with('uploaded', $created);
    }

    public function update(Request $request, MediaFile $media): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'folder' => ['nullable', 'string', 'max:120'],
        ]);

        $media->update($data);

        return back()->with('success', 'Medya güncellendi.');
    }

    public function destroy(Request $request, MediaFile $media, MediaService $service): RedirectResponse
    {
        $inUse = $media->posts()->exists()
            || PostPlatformTarget::where('media_file_id', $media->id)->orWhere('thumbnail_media_id', $media->id)->exists();

        if ($inUse && ! $request->boolean('force')) {
            return back()->with('error', 'Bu medya gönderilerde kullanılıyor. Yine de silmek için "Zorla sil" seçeneğini kullanın.');
        }

        Audit::log('media.deleted', $media, ['name' => $media->original_name]);
        $service->delete($media);

        return back()->with('success', 'Medya silindi.');
    }

    public function file(MediaFile $media): StreamedResponse
    {
        return $this->serve($media, $media->path, false);
    }

    public function thumbnail(MediaFile $media): StreamedResponse
    {
        abort_unless($media->thumbnail_path, 404);

        return $this->serve($media, $media->thumbnail_path, false);
    }

    public function download(MediaFile $media): StreamedResponse
    {
        return $this->serve($media, $media->path, true);
    }

    private function serve(MediaFile $media, string $path, bool $download): StreamedResponse
    {
        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($path), 404, $media->is_demo ? 'DEMO medya: gerçek dosya yok.' : 'Dosya bulunamadı.');

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            // SVG vb. içinde betik çalışmasını engelle.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; media-src 'self'; sandbox",
            'Cache-Control' => 'private, max-age=3600',
        ];

        $name = $path === $media->path ? $media->original_name : basename($path);

        return $download
            ? $disk->download($path, $name, $headers)
            : $disk->response($path, $name, $headers);
    }
}
