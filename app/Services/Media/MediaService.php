<?php

namespace App\Services\Media;

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Medya dosyalarını yerel diskte (storage/app/media) saklar.
 * Dosya adı rastgele üretilir; kullanıcı dosya adı yalnızca görüntüleme için tutulur.
 * FFmpeg/FFprobe (ücretsiz) bulunursa video süresi/çözünürlüğü okunur ve kapak üretilir.
 */
class MediaService
{
    public const VIDEO_MIMES = ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v'];

    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public const VIDEO_EXTENSIONS = ['mp4', 'mov', 'webm', 'm4v'];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function store(UploadedFile $file, ?int $projectId, ?string $folder, ?User $user): MediaFile
    {
        $mime = (string) $file->getMimeType(); // içerikten (finfo) tespit edilir, istemcinin bildirdiği türe güvenilmez
        $type = in_array($mime, self::VIDEO_MIMES, true) ? 'video' : 'image';
        $extension = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'bin'));
        $checksum = hash_file('sha256', $file->getRealPath());

        // Aynı dosya daha önce yüklendiyse yeniden kullan (disk tasarrufu, tekrar tespiti).
        $existing = MediaFile::where('checksum', $checksum)->where('is_demo', false)->first();
        if ($existing && Storage::disk($existing->disk)->exists($existing->path)) {
            return $existing;
        }

        $directory = now()->format('Y/m');
        $name = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $name, 'media');

        $media = new MediaFile([
            'project_id' => $projectId,
            'uploaded_by' => $user?->id,
            'folder' => $folder ? Str::limit(trim($folder), 120, '') : null,
            'disk' => 'media',
            'path' => $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'mime_type' => $mime,
            'extension' => $extension,
            'type' => $type,
            'size_bytes' => $file->getSize(),
            'checksum' => $checksum,
        ]);

        $absolute = Storage::disk('media')->path($path);
        if ($type === 'image') {
            $info = @getimagesize($absolute);
            if ($info) {
                [$media->width, $media->height] = $info;
            }
        } else {
            $this->probeVideo($media, $absolute);
        }

        $media->save();

        return $media;
    }

    public function delete(MediaFile $media): void
    {
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->thumbnail_path]));
        $media->delete();
    }

    public static function ffmpegAvailable(): bool
    {
        return self::binary('ffmpeg') !== null && self::binary('ffprobe') !== null;
    }

    private function probeVideo(MediaFile $media, string $absolute): void
    {
        $ffprobe = self::binary('ffprobe');
        $ffmpeg = self::binary('ffmpeg');
        if (! $ffprobe) {
            return;
        }

        try {
            $process = new Process([$ffprobe, '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height:format=duration', '-of', 'json', $absolute]);
            $process->setTimeout(60)->run();
            if ($process->isSuccessful()) {
                $json = json_decode($process->getOutput(), true) ?? [];
                $media->width = data_get($json, 'streams.0.width');
                $media->height = data_get($json, 'streams.0.height');
                $duration = data_get($json, 'format.duration');
                $media->duration_seconds = $duration !== null ? round((float) $duration, 2) : null;
            }

            if ($ffmpeg) {
                $thumb = 'thumbnails/'.pathinfo($media->path, PATHINFO_FILENAME).'.jpg';
                Storage::disk('media')->makeDirectory('thumbnails');
                $at = $media->duration_seconds && $media->duration_seconds > 2 ? '1' : '0';
                $process = new Process([$ffmpeg, '-y', '-ss', $at, '-i', $absolute, '-frames:v', '1', '-vf', 'scale=480:-2', Storage::disk('media')->path($thumb)]);
                $process->setTimeout(120)->run();
                if ($process->isSuccessful()) {
                    $media->thumbnail_path = $thumb;
                }
            }
        } catch (Throwable $e) {
            Log::info('FFmpeg ile video bilgisi okunamadı', ['error' => $e->getMessage()]);
        }
    }

    private static function binary(string $name): ?string
    {
        $configured = config('socialhub.'.$name.'_path');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder)->find($name);
    }
}
