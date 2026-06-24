<?php

namespace App\Policies;

use App\Models\User;

class CommercialLeadStepPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager', 'commercial_agent']);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }
}
