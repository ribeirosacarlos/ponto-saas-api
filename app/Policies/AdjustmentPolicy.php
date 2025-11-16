<?php

namespace App\Policies;

use App\Models\Adjustment;
use App\Models\User;

class AdjustmentPolicy
{
    /**
     * Employees request adjustments only for themselves.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['employee', 'area_manager', 'manager', 'admin']);
    }


    /**
     * Employee can only view his own adjustments.
     */
    public function view(User $user, Adjustment $adj): bool
    {
        if ($user->company_id !== $adj->company_id) {
            return false;
        }

        if ($user->hasRole('employee')) {
            return $adj->user_id === $user->id;
        }

        return true; // manager / area_manager / admin
    }


    /**
     * Only manager, area_manager or admin can approve/reject.
     */
    public function approve(User $user, Adjustment $adj): bool
    {
        if ($user->company_id !== $adj->company_id) {
            return false;
        }

        return $user->hasRole(['manager', 'area_manager', 'admin']);
    }


    public function reject(User $user, Adjustment $adj): bool
    {
        return $this->approve($user, $adj);
    }
}
