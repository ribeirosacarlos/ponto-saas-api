<?php

namespace App\Policies;

use App\Models\User;

class CommercialCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }
}
