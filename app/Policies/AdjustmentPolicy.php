<?php

namespace App\Policies;

use App\Models\Adjustment;
use App\Models\User;

class AdjustmentPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(['employee', 'area_manager', 'manager', 'admin']);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['manager', 'area_manager', 'admin']);
    }

    /**
     * Visualizar ajuste individual
     */
    public function view(User $user, Adjustment $adj): bool
    {
        if ($user->company_id !== $adj->company_id) {
            return false;
        }

        if ($user->hasRole('employee')) {
            return $adj->user_id === $user->id;
        }

        return true;
    }

    /**
     * Aprovar ajuste
     */
    public function approve(User $user, Adjustment $adj): bool
    {
        if ($user->company_id !== $adj->company_id) {
            return false;
        }

        if (!$user->hasRole(['manager', 'area_manager', 'admin'])) {
            return false;
        }

        return $adj->status === 'pending';
    }

    public function reject(User $user, Adjustment $adj): bool
    {
        return $this->approve($user, $adj);
    }
}
