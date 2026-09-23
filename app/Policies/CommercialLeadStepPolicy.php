<?php

namespace App\Policies;

use App\Models\CommercialAffiliate;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class CommercialLeadStepPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof CommercialAffiliate) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole(['super_admin', 'commercial_manager', 'commercial_agent']);
    }

    public function manage(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }
}
