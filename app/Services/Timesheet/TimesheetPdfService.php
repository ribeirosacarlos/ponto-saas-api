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

        $snapshot = $timesheet->snapshot ?? [];
        $previousBalanceHhmm = $snapshot['previous_balance_hhmm'] ?? $this->computePreviousBalanceHhmm($timesheet);
        $currentBalanceMinutes = (int) ($snapshot['totals']['balance_minutes'] ?? 0);
        $previousBalanceMinutes = $this->hhhmmToMinutes($previousBalanceHhmm);
        $accumulatedBalanceHhmm = $snapshot['accumulated_balance_hhmm'] ?? $this->minutesToSignedHhmm($previousBalanceMinutes + $currentBalanceMinutes);

        $pdf = Pdf::loadView('pdf.timesheet_signature', [
            'timesheet' => $timesheet,
            'employee' => $timesheet->employee,
            'closure' => $timesheet->monthlyClosure,
            'snapshot' => $snapshot,
            'employeeSignature' => $employeeSignature,
            'managerSignature' => $managerSignature,
            'signatureImageBase64' => $signatureImageBase64,
            'previousBalanceHhmm' => $previousBalanceHhmm,
            'accumulatedBalanceHhmm' => $accumulatedBalanceHhmm,
            'declarationText' => 'Declaro que visualizei e confirmei eletronicamente esta folha de ponto, reconhecendo os registros apresentados para o período indicado.',
        ])->setPaper('a4', 'portrait');

        $closure = $timesheet->monthlyClosure;
        $path = sprintf(
            'companies/%s/%d/%02d/pdfs/%s.pdf',
            $timesheet->company_id,
            $closure->reference_year,
            $closure->reference_month,
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

    private function computePreviousBalanceHhmm(EmployeeTimesheet $timesheet): string
    {
        $closure = $timesheet->monthlyClosure;

        $minutes = EmployeeTimesheet::query()
            ->where('employee_id', $timesheet->employee_id)
            ->where('company_id', $timesheet->company_id)
            ->where('id', '!=', $timesheet->id)
            ->whereHas('monthlyClosure', function ($q) use ($closure) {
                $q->where('reference_year', '<', $closure->reference_year)
                    ->orWhere(function ($q2) use ($closure) {
                        $q2->where('reference_year', $closure->reference_year)
                            ->where('reference_month', '<', $closure->reference_month);
                    });
            })
            ->get(['snapshot'])
            ->sum(fn (EmployeeTimesheet $ts) => (int) ($ts->snapshot['totals']['balance_minutes'] ?? 0));

        return $this->minutesToSignedHhmm((int) $minutes);
    }

    private function minutesToSignedHhmm(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }
        $sign = $minutes > 0 ? '+' : '-';
        $abs = abs($minutes);

        return sprintf('%s%02d:%02d', $sign, (int) floor($abs / 60), $abs % 60);
    }

    private function hhhmmToMinutes(string $hhmm): int
    {
        if ($hhmm === '00:00') {
            return 0;
        }
        $sign = str_starts_with($hhmm, '-') ? -1 : 1;
        [$h, $m] = explode(':', ltrim($hhmm, '+-'));

        return $sign * ((int) $h * 60 + (int) $m);
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
