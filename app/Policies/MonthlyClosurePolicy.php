<?php

namespace App\Policies;

use App\Models\MonthlyClosure;
use App\Models\User;

class MonthlyClosurePolicy
{
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
}
