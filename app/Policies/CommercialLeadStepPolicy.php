<?php

namespace App\Policies;

use App\Models\User;

class CommercialLeadStepPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
