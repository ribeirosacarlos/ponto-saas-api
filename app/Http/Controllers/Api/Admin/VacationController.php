<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveVacationRequestRequest;
use App\Http\Requests\RejectVacationRequestRequest;
use App\Http\Requests\StoreVacationRequestAdminRequest;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\VacationBalanceService;
use App\Services\VacationRequestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacationController extends Controller
{
    public function __construct(
        protected VacationBalanceService $balanceService,
        protected VacationRequestService $vacationRequestService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = VacationRequest::with(['user'])
            ->where('company_id', $user->company_id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('start')) {
            $query->whereDate('start_date', '>=', $request->start);
        }

        if ($request->filled('end')) {
            $query->whereDate('end_date', '<=', $request->end);
        }

        return response()->json($query->paginate(20));
    }

    public function store(StoreVacationRequestAdminRequest $request)
    {
        $admin = $request->user();
        $user = User::where('company_id', $admin->company_id)->findOrFail($request->user_id);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->startOfDay();

        $this->vacationRequestService->ensureNoConflicts($user, $start, $end);

        $calculation = $this->vacationRequestService->calculateRequestedDays(
            $user,
            $start,
            $end,
            'calendar_days'
        );

        $requestedDays = $calculation['count'];

        if ($requestedDays <= 0) {
            throw ValidationException::withMessages([
                'requested_days' => 'Período inválido para contagem de férias.',
            ]);
        }

        $status = $request->status ?? 'pending';

        $vacationRequest = DB::transaction(function () use (
            $request,
            $user,
            $admin,
            $start,
            $end,
            $requestedDays,
            $calculation,
            $status
        ) {
            $vacationRequest = VacationRequest::create([
                'company_id'               => $user->company_id,
                'user_id'                  => $user->id,
                'start_date'               => $start->toDateString(),
                'end_date'                 => $end->toDateString(),
                'requested_days'           => $requestedDays,
                'counting_method_snapshot' => 'calendar_days',
                'status'                   => $status,
                'requested_by'             => $admin->id,
                'notes'                    => $request->notes,
            ]);

            if ($status === 'approved') {
                $vacationRequest->update([
                    'approved_by' => $admin->id,
                    'approved_at' => now(),
                ]);

                $this->vacationRequestService->createVacationDays($vacationRequest, $calculation['days']);
            }

            return $vacationRequest;
        });

        return response()->json($vacationRequest, 201);
    }

    public function approve(ApproveVacationRequestRequest $request, VacationRequest $vacation)
    {
        $admin = $request->user();
        $this->authorizeRequest($admin->company_id, $vacation);

        if ($vacation->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Somente pedidos pendentes podem ser aprovados.',
            ]);
        }

        $user = $vacation->user;
        $start = Carbon::parse($vacation->start_date);
        $end = Carbon::parse($vacation->end_date);

        $this->vacationRequestService->ensureNoConflicts($user, $start, $end, $vacation->id);

        $calculation = $this->vacationRequestService->calculateRequestedDays(
            $user,
            $start,
            $end,
            $vacation->counting_method_snapshot ?: 'calendar_days'
        );

        DB::transaction(function () use ($vacation, $admin, $calculation, $request) {
            $vacation->update([
                'status'       => 'approved',
                'approved_by'  => $admin->id,
                'approved_at'  => now(),
                'rejection_reason' => null,
                'notes'        => $request->notes ?? $vacation->notes,
            ]);

            $this->vacationRequestService->createVacationDays($vacation, $calculation['days']);
        });

        return response()->json($vacation->refresh());
    }

    public function reject(RejectVacationRequestRequest $request, VacationRequest $vacation)
    {
        $admin = $request->user();
        $this->authorizeRequest($admin->company_id, $vacation);

        if ($vacation->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Somente pedidos pendentes podem ser rejeitados.',
            ]);
        }

        $vacation->update([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json($vacation);
    }

    public function destroy(Request $request, VacationRequest $vacation)
    {
        $admin = $request->user();
        $this->authorizeRequest($admin->company_id, $vacation);

        if (! in_array($vacation->status, ['pending', 'approved'])) {
            $vacation->update(['status' => 'cancelled']);
            return response()->json(['message' => 'Solicitação cancelada.']);
        }

        DB::transaction(function () use ($vacation) {
            if ($vacation->status === 'approved') {
                $this->vacationRequestService->deleteVacationDays($vacation);
            }

            $vacation->update([
                'status' => 'cancelled',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        });

        return response()->json(['message' => 'Solicitação cancelada.']);
    }

    public function balance(Request $request, User $employee)
    {
        $admin = $request->user();

        if ($employee->company_id !== $admin->company_id) {
            abort(403, 'Funcionário inválido.');
        }

        $balance = $this->balanceService->calculateBalance($employee);

        return response()->json([
            'period_start' => $balance['period_start'],
            'period_end' => $balance['period_end'],
            'annual_entitlement_days' => $balance['annual_entitlement_days'],
            'accrual_basis' => $balance['accrual_basis'],
            'computable_days' => $balance['computable_days'],
            'non_computable_days' => $balance['non_computable_days'],
            'accrual_rate' => $balance['accrual_rate'],
            'accrued_days' => $balance['accrued_days'],
            'used_days' => $balance['used_days'],
            'available_days' => $balance['available_days'],
            'breakdown' => $balance['breakdown'],
        ]);
    }

    protected function authorizeRequest(string $companyId, VacationRequest $vacation): void
    {
        if ($vacation->company_id !== $companyId) {
            abort(403, 'Solicitação não pertence à empresa atual.');
        }
    }
}
