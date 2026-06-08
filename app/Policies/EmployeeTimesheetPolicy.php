<?php

namespace App\Policies;

use App\Models\EmployeeTimesheet;
use App\Models\User;
use App\Services\UserVisibilityService;

class EmployeeTimesheetPolicy
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, EmployeeTimesheet $timesheet): bool
    {
        if ((string) $user->company_id !== (string) $timesheet->company_id) {
            return false;
        }

        if ((string) $user->id === (string) $timesheet->employee_id) {
            return true;
        }

        return $user->hasRole(['admin', 'manager', 'area_manager'])
            && $this->userVisibilityService->canManageUser($user, $timesheet->employee);
    }

    public function signAsEmployee(User $user, EmployeeTimesheet $timesheet): bool
    {
        return (string) $user->company_id === (string) $timesheet->company_id
            && (string) $user->id === (string) $timesheet->employee_id;
    }

    public function signAsManager(User $user, EmployeeTimesheet $timesheet): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager'])
            && $this->userVisibilityService->canManageUser($user, $timesheet->employee);
    }

    public function dispute(User $user, EmployeeTimesheet $timesheet): bool
    {
        return (string) $user->company_id === (string) $timesheet->company_id
            && (string) $user->id === (string) $timesheet->employee_id;
    }

    public function resolveDispute(User $user, EmployeeTimesheet $timesheet): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager'])
            && $this->userVisibilityService->canManageUser($user, $timesheet->employee);
    }
}
