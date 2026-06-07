<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalCertificateRequest;
use App\Http\Resources\MedicalCertificateResource;
use App\Models\Absence;
use App\Services\MedicalCertificateService;
use Illuminate\Http\Request;

class MedicalCertificateController extends Controller
{
    public function __construct(
        protected MedicalCertificateService $medicalCertificateService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Absence::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('type', Absence::TYPE_SICK_LEAVE)
            ->with('documents')
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->applyDateFilters($query, $request);

        return MedicalCertificateResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreMedicalCertificateRequest $request)
    {
        $absence = $this->medicalCertificateService->createFromEmployee(
            $request->user(),
            $request->validated(),
            $request->file('files', []),
        );

        return (new MedicalCertificateResource($absence))->response()->setStatusCode(201);
    }

    public function show(Request $request, Absence $absence)
    {
        $this->authorizeOwnMedicalCertificate($request, $absence);

        return new MedicalCertificateResource($absence->load('documents'));
    }

    public function destroy(Request $request, Absence $absence)
    {
        $this->authorizeOwnMedicalCertificate($request, $absence);

        $absence = $this->medicalCertificateService->cancel($absence, $request->user());

        return new MedicalCertificateResource($absence);
    }

    private function authorizeOwnMedicalCertificate(Request $request, Absence $absence): void
    {
        $user = $request->user();

        if (
            (string) $absence->company_id !== (string) $user->company_id
            || (string) $absence->user_id !== (string) $user->id
            || ! $absence->isMedicalCertificate()
        ) {
            abort(404);
        }
    }

    private function applyDateFilters($query, Request $request): void
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if (! $from && ! $to) {
            return;
        }

        $from = $from ?: $to;
        $to = $to ?: $from;

        $query->whereDate('start_date', '<=', $to)
            ->where(function ($query) use ($from) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $from);
            });
    }
}
