<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DocumentStoragePath
{
<<<<<<< HEAD
    public const ROOT_PREFIX = 'jornafy-documents';

=======
>>>>>>> 3d28fcd (feat: separate document uploads by company prefix)
    public static function newEmployeeDocumentPath(UploadedFile $file, string $companyId, string $employeeId): array
    {
        $extension = self::extensionFromUpload($file);

        return [
            sprintf(
<<<<<<< HEAD
                '%s/%s/documents/employees/%s/%s.%s',
                self::ROOT_PREFIX,
=======
                '%s/documents/employees/%s/%s.%s',
>>>>>>> 3d28fcd (feat: separate document uploads by company prefix)
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
