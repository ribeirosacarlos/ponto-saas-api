<?php

namespace App\Policies;

use App\Models\MonthlyClosure;
use App\Models\User;
use App\Services\UserVisibilityService;

class MonthlyClosurePolicy
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {}

    public function create(User $user): bool
    {
        return $user->hasRole('admin') && ! empty($user->company_id);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager']) && ! empty($user->company_id);
    }

    public function view(User $user, MonthlyClosure $closure): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager'])
            && (string) $user->company_id === (string) $closure->company_id;
    }

    public function delete(User $user, MonthlyClosure $closure): bool
    {
        if (empty($user->company_id) || ! $this->view($user, $closure)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        $employees = $closure->timesheets()->withoutGlobalScope('company')->with('employee')->get()
            ->map(fn ($timesheet) => $timesheet->employee);

        if ($closure->employee_id !== null) {
            $employees->push($closure->employee);
        }

        return $employees->isNotEmpty() && $employees->every(
            fn ($employee) => $employee !== null
                && $this->userVisibilityService->canManageUser($user, $employee)
        );
    }
}
