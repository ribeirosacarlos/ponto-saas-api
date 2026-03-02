<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVacationRequestEmployeeRequest;
use App\Models\VacationRequest;
use App\Services\VacationBalanceService;
use App\Services\VacationRequestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $requests = VacationRequest::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($requests);
    }

    public function store(StoreVacationRequestEmployeeRequest $request)
    {
        $user = $request->user();

        $policy = $this->balanceService->getActivePolicyForUser($user);

        if (! $policy) {
            throw ValidationException::withMessages([
                'policy' => 'Nenhuma política de férias configurada para sua empresa.',
            ]);
        }

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end = Carbon::parse($request->end_date)->startOfDay();

        $this->vacationRequestService->ensureNoConflicts($user, $start, $end);

        $calculation = $this->vacationRequestService->calculateRequestedDays(
            $user,
            $start,
            $end,
            $policy->counting_method ?? 'calendar_days'
        );

        $requestedDays = $calculation['count'];

        if ($requestedDays <= 0) {
            throw ValidationException::withMessages([
                'requested_days' => 'Período inválido para contagem de férias.',
            ]);
        }

        $this->vacationRequestService->ensureEnoughBalance($user, $requestedDays);

        $vacationRequest = VacationRequest::create([
            'company_id'               => $user->company_id,
            'user_id'                  => $user->id,
            'start_date'               => $start->toDateString(),
            'end_date'                 => $end->toDateString(),
            'requested_days'           => $requestedDays,
            'counting_method_snapshot' => $policy->counting_method ?? 'calendar_days',
            'status'                   => 'pending',
            'requested_by'             => $user->id,
            'notes'                    => $request->notes,
        ]);

        return response()->json($vacationRequest, 201);
    }

    public function balance(Request $request)
    {
        $user = $request->user();
        $balance = $this->balanceService->calculateBalance($user);

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

    public function destroy(Request $request, VacationRequest $vacation)
    {
        $user = $request->user();

        if ($vacation->company_id !== $user->company_id || $vacation->user_id !== $user->id) {
            abort(403, 'Pedido inválido.');
        }

        if ($vacation->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Somente pedidos pendentes podem ser cancelados.',
            ]);
        }

        $vacation->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Solicitação de férias cancelada.']);
    }
}
