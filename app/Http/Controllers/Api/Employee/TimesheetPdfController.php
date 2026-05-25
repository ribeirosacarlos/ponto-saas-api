<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\EmployeeTimesheet;
use App\Services\Timesheet\TimesheetPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TimesheetPdfController extends Controller
{
    public function __construct(protected TimesheetPdfService $pdfService) {}

    public function download(Request $request, EmployeeTimesheet $timesheet)
    {
        $this->authorize('view', $timesheet);

        if (! $timesheet->pdf_path) {
            return response()->json(['message' => 'PDF ainda não gerado para esta folha.'], 404);
        }

        $disk = config('filesystems.default', 'local');

        if (! Storage::disk($disk)->exists($timesheet->pdf_path)) {
            return response()->json(['message' => 'Arquivo PDF não encontrado.'], 404);
        }

        $filename = $this->buildFilename($timesheet);

        if (config('filesystems.default') === 's3') {
            $url = Storage::disk('s3')->temporaryUrl($timesheet->pdf_path, now()->addMinutes(30));

            return response()->json(['url' => $url]);
        }

        return Storage::disk($disk)->download($timesheet->pdf_path, $filename);
    }

    private function buildFilename(EmployeeTimesheet $timesheet): string
    {
        $timesheet->loadMissing(['employee', 'monthlyClosure']);

        $name = $timesheet->employee
            ? str($timesheet->employee->name)->slug('-')
            : 'colaborador';

        $month = $timesheet->monthlyClosure
            ? str_pad((string) $timesheet->monthlyClosure->reference_month, 2, '0', STR_PAD_LEFT)
            : '00';

        $year = $timesheet->monthlyClosure
            ? (string) $timesheet->monthlyClosure->reference_year
            : date('Y');

        return "folha-de-ponto-{$name}-{$month}-{$year}.pdf";
    }
}
