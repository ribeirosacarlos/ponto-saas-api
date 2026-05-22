<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeTimesheet;
use App\Models\TimesheetSignature;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class TimesheetPdfService
{
    public function generateSignedPdf(EmployeeTimesheet $timesheet): string
    {
        $timesheet->loadMissing(['employee', 'monthlyClosure', 'activeSignatures.signer']);

        $employeeSignature = $timesheet->activeSignatures->firstWhere('role.value', 'employee');
        $managerSignature = $timesheet->activeSignatures->firstWhere('role.value', 'manager');

        $signatureImageBase64 = null;
        if ($employeeSignature?->signature_image_path) {
            $disk = config('filesystems.default', 'local');
            if (Storage::disk($disk)->exists($employeeSignature->signature_image_path)) {
                $imageContent = Storage::disk($disk)->get($employeeSignature->signature_image_path);
                $signatureImageBase64 = 'data:image/png;base64,' . base64_encode($imageContent);
            }
        }

        $pdf = Pdf::loadView('pdf.timesheet_signature', [
            'timesheet' => $timesheet,
            'employee' => $timesheet->employee,
            'closure' => $timesheet->monthlyClosure,
            'snapshot' => $timesheet->snapshot ?? [],
            'employeeSignature' => $employeeSignature,
            'managerSignature' => $managerSignature,
            'signatureImageBase64' => $signatureImageBase64,
            'declarationText' => 'Declaro que visualizei e confirmei eletronicamente esta folha de ponto, reconhecendo os registros apresentados para o período indicado.',
        ])->setPaper('a4', 'portrait');

        $path = sprintf(
            'timesheets/signed/%s/%s.pdf',
            $timesheet->company_id,
            $timesheet->id
        );

        $disk = config('filesystems.default', 'local');
        Storage::disk($disk)->put($path, $pdf->output());

        $timesheet->update([
            'pdf_path' => $path,
            'pdf_generated_at' => now(),
        ]);

        return $path;
    }

    public function getSignedPdfUrl(EmployeeTimesheet $timesheet): ?string
    {
        if (! $timesheet->pdf_path) {
            return null;
        }

        $disk = config('filesystems.default', 'local');

        if (config('filesystems.default') === 's3') {
            return Storage::disk('s3')->temporaryUrl($timesheet->pdf_path, now()->addMinutes(30));
        }

        return Storage::disk($disk)->url($timesheet->pdf_path);
    }
}
