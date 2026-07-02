<?php

namespace App\Policies;

use App\Models\User;

class CommercialAffiliatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function view(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function update(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function delete(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }
}
