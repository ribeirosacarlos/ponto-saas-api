<?php

namespace App\Policies;

use App\Models\MonthlyClosure;
use App\Models\User;

class MonthlyClosurePolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager']);
    }

    public function view(User $user, MonthlyClosure $closure): bool
    {
        return $user->hasRole(['admin', 'manager', 'area_manager']);
    }
}
