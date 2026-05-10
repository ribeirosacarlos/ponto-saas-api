<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class DocumentFileValidator
{
    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];

    public static function validate(UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'As extensões permitidas são: pdf, jpg, jpeg, png, doc, docx, xls e xlsx.';
        }

        $realPath = $file->getRealPath();

        if ($realPath === false || $realPath === '') {
            return 'Não foi possível validar o conteúdo real do arquivo enviado.';
        }

        $handle = @fopen($realPath, 'rb');

        if (! is_resource($handle)) {
            return 'Não foi possível validar o conteúdo real do arquivo enviado.';
        }

        $signature = fread($handle, 8);
        fclose($handle);

        return match ($extension) {
            'pdf' => self::validatePdf($signature),
            'jpg', 'jpeg' => self::validateJpeg($signature),
            'png' => self::validatePng($signature),
            'doc' => self::validateLegacyOffice($realPath, ['application/msword', 'application/x-ole-storage']),
            'xls' => self::validateLegacyOffice($realPath, ['application/vnd.ms-excel', 'application/x-ole-storage']),
            'docx' => self::validateOpenXml($realPath, 'word/document.xml'),
            'xlsx' => self::validateOpenXml($realPath, 'xl/workbook.xml'),
            default => 'Tipo de arquivo não suportado.',
        };
    }

    private static function validatePdf(string $signature): ?string
    {
        return str_starts_with($signature, '%PDF-')
            ? null
            : 'O arquivo enviado não corresponde a um PDF válido.';
    }

    private static function validateJpeg(string $signature): ?string
    {
        return str_starts_with($signature, "\xFF\xD8\xFF")
            ? null
            : 'O arquivo enviado não corresponde a uma imagem JPEG válida.';
    }

    private static function validatePng(string $signature): ?string
    {
        return $signature === "\x89PNG\x0D\x0A\x1A\x0A"
            ? null
            : 'O arquivo enviado não corresponde a uma imagem PNG válida.';
    }

    private static function validateLegacyOffice(string $realPath, array $allowedMimeTypes): ?string
    {
        $signature = file_get_contents($realPath, false, null, 0, 8);

        if ($signature !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return 'O arquivo enviado não corresponde a um documento Office válido.';
        }

        $mimeType = mime_content_type($realPath) ?: '';

        return in_array($mimeType, $allowedMimeTypes, true)
            ? null
            : 'O arquivo enviado não corresponde ao tipo Office esperado.';
    }

    private static function validateOpenXml(string $realPath, string $requiredEntry): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return 'Não foi possível validar o conteúdo real do arquivo enviado.';
        }

        $zip = new ZipArchive();
        $opened = $zip->open($realPath);

        if ($opened !== true) {
            return 'O arquivo enviado não corresponde a um documento Office válido.';
        }

        $hasEntry = $zip->locateName($requiredEntry, ZipArchive::FL_NOCASE) !== false;
        $zip->close();

        return $hasEntry
            ? null
            : 'O arquivo enviado não corresponde ao tipo Office esperado.';
    }
}
