<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    private array $adminRoles = ['admin', 'manager', 'area_manager'];
    private array $employeeRoles = ['employee', 'manager', 'area_manager', 'admin'];

    public function viewAnyAdmin(User $user): bool
    {
        return $user->hasRole($this->adminRoles);
    }

    public function viewAnyEmployee(User $user): bool
    {
        return $user->hasRole($this->employeeRoles);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->belongsToCompany($user, $announcement)
            && $user->hasRole($this->employeeRoles);
    }

    public function markSeen(User $user, Announcement $announcement): bool
    {
        return $this->view($user, $announcement);
    }

    public function create(User $user): bool
    {
        return $user->hasRole($this->adminRoles);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $this->belongsToCompany($user, $announcement)
            && $user->hasRole($this->adminRoles);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }

    protected function belongsToCompany(User $user, Announcement $announcement): bool
    {
        return $user->company_id === $announcement->company_id;
    }
}
