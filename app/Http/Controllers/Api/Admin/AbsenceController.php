<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\User;
use App\Services\AbsenceAllowanceService;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService,
        protected AbsenceAllowanceService $absenceAllowanceService
    ) {}

    public function index(Request $request)
    {
        $admin = $request->user();

        $query = Absence::query()
            ->orderByDesc('start_date');
        $this->userVisibilityService->applyToUserOwnedQuery($query, $admin);

        if ($request->filled('user_id')) {
            if ($admin->hasRole('admin') || $this->userVisibilityService->canManageUserId($admin, $request->user_id)) {
                $query->where('user_id', $request->user_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if ($from || $to) {
            $from = $from ?: $to;
            $to = $to ?: $from;

            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                    ->orWhereBetween('end_date', [$from, $to])
                    ->orWhere(function ($q) use ($from, $to) {
                        $q->where('start_date', '<=', $from)
                            ->where(function ($q) use ($to) {
                                $q->whereNull('end_date')->orWhere('end_date', '>=', $to);
                            });
                    });
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreAbsenceRequest $request)
    {
        $admin = $request->user();
        $targetUserQuery = $admin->hasRole('admin')
            ? User::query()->where('company_id', $admin->company_id)
            : $this->userVisibilityService->visibleUsersQuery($admin);
        $user = $targetUserQuery->whereKey($request->user_id)->firstOrFail();

        [$absence, $warnings] = $this->absenceAllowanceService->createFromAdmin($admin, $user, $request->validated());

        return response()->json(array_merge($absence->toArray(), ['warnings' => $warnings]), 201);
    }

    public function destroy(Request $request, Absence $absence)
    {
        $admin = $request->user();

        if ((string) $absence->company_id !== (string) $admin->company_id) {
            abort(404);
        }

        if (! $admin->hasRole('admin') && ! $this->userVisibilityService->canManageUserId($admin, $absence->user_id)) {
            abort(404);
        }

        $this->absenceAllowanceService->destroyFromAdmin($absence);

        return response()->noContent();
    }
}
