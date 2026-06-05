<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentStoragePath
{
    public static function newEmployeeDocumentPath(UploadedFile $file, string $companyId, string $employeeId): array
    {
        $extension = self::extensionFromUpload($file);

        return [
            sprintf(
                '%s/documents/employees/%s/%s.%s',
                $companyId,
                $employeeId,
                (string) Str::ulid(),
                $extension,
            ),
            $extension,
        ];
    }

    private static function extensionFromUpload(UploadedFile $file): string
    {
        $rawExtension = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));

        return preg_replace('/[^a-z0-9]+/', '', $rawExtension) ?: 'bin';
    }
}
