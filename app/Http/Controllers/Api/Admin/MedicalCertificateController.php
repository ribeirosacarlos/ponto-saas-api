<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminStoreMedicalCertificateRequest;
use App\Http\Requests\RejectMedicalCertificateRequest;
use App\Http\Resources\MedicalCertificateResource;
use App\Models\Absence;
use App\Models\User;
use App\Services\MedicalCertificateService;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;

class MedicalCertificateController extends Controller
{
    public function __construct(
        protected MedicalCertificateService $medicalCertificateService,
        protected UserVisibilityService $userVisibilityService
    ) {}

    public function index(Request $request)
    {
        $actor = $request->user();

        $query = Absence::query()
            ->where('type', Absence::TYPE_SICK_LEAVE)
            ->with(['documents', 'user:id,name,email,area_id'])
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');

        $this->userVisibilityService->applyToUserOwnedQuery($query, $actor);

        if ($request->filled('user_id')) {
            if ($actor->hasRole('admin') || $this->userVisibilityService->canManageUserId($actor, $request->input('user_id'))) {
                $query->where('user_id', $request->input('user_id'));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->applyDateFilters($query, $request);

        return MedicalCertificateResource::collection($query->paginate($request->integer('per_page', 20)));
    }

    public function store(AdminStoreMedicalCertificateRequest $request)
    {
        $actor = $request->user();
        $employee = $this->resolveVisibleEmployee($actor, $request->input('user_id'));

        $absence = $this->medicalCertificateService->createFromAdmin(
            $actor,
            $employee,
            $request->validated(),
            $request->file('files', []),
        );

        return (new MedicalCertificateResource($absence))->response()->setStatusCode(201);
    }

    public function show(Request $request, Absence $absence)
    {
        $this->authorizeVisibleMedicalCertificate($request, $absence);

        return new MedicalCertificateResource($absence->load(['documents', 'user:id,name,email,area_id']));
    }

    public function approve(Request $request, Absence $absence)
    {
        $this->authorizeVisibleMedicalCertificate($request, $absence);

        $absence = $this->medicalCertificateService->approve($absence, $request->user());

        return new MedicalCertificateResource($absence);
    }

    public function reject(RejectMedicalCertificateRequest $request, Absence $absence)
    {
        $this->authorizeVisibleMedicalCertificate($request, $absence);

        $absence = $this->medicalCertificateService->reject(
            $absence,
            $request->user(),
            $request->string('rejection_reason')->toString()
        );

        return new MedicalCertificateResource($absence);
    }

    private function resolveVisibleEmployee(User $actor, string $employeeId): User
    {
        $query = $actor->hasRole('admin')
            ? User::query()->where('company_id', $actor->company_id)
            : $this->userVisibilityService->visibleUsersQuery($actor);

        return $query->whereKey($employeeId)->firstOrFail();
    }

    private function authorizeVisibleMedicalCertificate(Request $request, Absence $absence): void
    {
        $actor = $request->user();

        if (
            (string) $absence->company_id !== (string) $actor->company_id
            || ! $absence->isMedicalCertificate()
        ) {
            abort(404);
        }

        if ($actor->hasRole('admin')) {
            return;
        }

        if (! $this->userVisibilityService->canManageUserId($actor, $absence->user_id)) {
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
