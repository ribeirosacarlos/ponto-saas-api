<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BlogUploadController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'filename'  => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'in:image/jpeg,image/png,image/webp'],
            'folder'    => ['required', 'in:covers,heroes,og'],
        ]);

        $extension = match ($request->mime_type) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        };

        $key = sprintf(
            'blog/%s/%s.%s',
            $request->folder,
            str(str()->ulid())->lower(),
            $extension
        );

        $disk = Storage::disk('s3');

        $presignedUrl = $disk->temporaryUploadUrl($key, now()->addMinutes(5), [
            'ContentType'  => $request->mime_type,
            'CacheControl' => 'public, max-age=31536000',
        ]);

        $publicUrl = $disk->url($key);

        return response()->json([
            'upload_url' => $presignedUrl,
            'public_url' => $publicUrl,
        ]);
    }
}
