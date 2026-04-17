<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\UserVisibilityService;

class TimeEntryPolicy
{
    public function view(User $user, TimeEntry $entry): bool
    {
        if ((string) $user->company_id !== (string) $entry->company_id) {
            return false;
        }

        if ($user->hasRole('employee')) {
            return (string) $entry->user_id === (string) $user->id;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $user->hasRole(['manager', 'area_manager'])) {
            return false;
        }

        return app(UserVisibilityService::class)->canManageUserId($user, $entry->user_id);
    }

    public function viewAnyAdjustments(User $user): bool
    {
        if ($user->hasRole('employee')) {
            return false;
        }

        return $user->hasRole(['manager', 'area_manager', 'admin']);
    }

    public function requestAdjustment(User $user, TimeEntry $entry): bool
    {
        if ((string) $user->company_id !== (string) $entry->company_id) {
            return false;
        }

        if ($user->hasRole('employee')) {
            return (string) $entry->user_id === (string) $user->id;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $user->hasRole(['manager', 'area_manager'])) {
            return false;
        }

        return app(UserVisibilityService::class)->canManageUserId($user, $entry->user_id);
    }

    public function approveAdjustment(User $user, TimeEntry $entry): bool
    {
        if ($user->company_id !== $entry->company_id) {
            return false;
        }

        if (! $user->hasRole(['manager', 'area_manager', 'admin'])) {
            return false;
        }

        if (! $user->hasRole('admin') && ! app(UserVisibilityService::class)->canManageUserId($user, $entry->user_id)) {
            return false;
        }

        return $entry->isAdjustmentPending();
    }

    public function rejectAdjustment(User $user, TimeEntry $entry): bool
    {
        return $this->approveAdjustment($user, $entry);
    }


    /**
     * Employees can create clock entries only for themselves.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['employee', 'manager', 'area_manager', 'admin']);
    }


    /**
     * Nobody edits or deletes time entries directly in MVP.
     */
    public function update(User $user, TimeEntry $entry): bool
    {
        return false;
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return false;
    }
}
