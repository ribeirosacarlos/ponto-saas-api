<?php

namespace App\Policies;

use App\Models\User;
use App\Services\UserVisibilityService;

class EmployeePolicy
{
    public function view(User $user, User $employee): bool
    {
        return app(UserVisibilityService::class)->canViewUser($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, User $employee): bool
    {
        return app(UserVisibilityService::class)->canManageUser($user, $employee);
    }

    public function delete(User $user, User $employee): bool
    {
        return $user->hasRole('admin') &&
               $user->company_id === $employee->company_id;
    }
}
