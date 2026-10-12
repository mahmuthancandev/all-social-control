<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Süreli, imzalı medya adresi (Instagram/Meta'nın videoyu/görseli indirmesi için).
 * Rota "signed" ara katmanıyla korunur: imza veya süre geçersizse 403 döner.
 */
class PublicMediaController extends Controller
{
    public function __invoke(MediaFile $media): BinaryFileResponse
    {
        abort_if($media->is_demo, 404);

        $disk = Storage::disk($media->disk);
        abort_unless($disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
