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

        if (config('filesystems.default') === 's3') {
            $url = Storage::disk('s3')->temporaryUrl($timesheet->pdf_path, now()->addMinutes(30));

            return response()->json(['url' => $url]);
        }

        return Storage::disk($disk)->download($timesheet->pdf_path, 'folha-de-ponto.pdf');
    }
}
