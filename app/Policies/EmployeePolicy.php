<?php

namespace App\Policies;

use App\Models\User;

class EmployeePolicy
{
    /**
     * Admin can view all employees.
     * Manager and area_manager can view company employees.
     */
    public function view(User $user, User $employee): bool
    {
        return $user->company_id === $employee->company_id &&
               $user->hasRole(['manager', 'area_manager', 'admin']);
    }

    /**
     * Only admin can create employees in MVP.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin can update employees.
     */
    public function update(User $user, User $employee): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager']) &&
               $user->company_id === $employee->company_id;
    }

    /**
     * Only admin can delete.
     */
    public function delete(User $user, User $employee): bool
    {
        return $user->hasRole('admin') &&
               $user->company_id === $employee->company_id;
    }
}
