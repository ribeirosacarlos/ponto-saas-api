<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $client = new S3Client([
            'region'      => config('filesystems.disks.s3.region'),
            'version'     => 'latest',
            'credentials' => [
                'key'    => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $command = $client->getCommand('PutObject', [
            'Bucket'       => config('filesystems.disks.s3.bucket'),
            'Key'          => $key,
            'ContentType'  => $request->mime_type,
            'CacheControl' => 'public, max-age=31536000',
        ]);

        $presignedUrl = (string) $client->createPresignedRequest($command, '+5 minutes')->getUri();
        $publicUrl    = config('filesystems.disks.s3.url').'/'.$key;

        return response()->json([
            'upload_url' => $presignedUrl,
            'public_url' => $publicUrl,
        ]);
    }
}
