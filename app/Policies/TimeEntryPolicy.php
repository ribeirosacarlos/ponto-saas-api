<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    /**
     * Employee only sees his own time entries.
     * Manager, area_manager and admin see all entries from SAME company.
     */
    public function view(User $user, TimeEntry $entry): bool
    {
        if ((string) $user->company_id !== (string) $entry->company_id) {
            return false;
        }

        // employee sees own entries only
        if ($user->hasRole('employee')) {
            return (string) $entry->user_id === (string) $user->id;
        }

        // manager / area_manager / admin can view all entries from the same company
        return $user->hasRole(['manager', 'area_manager', 'admin']);
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

        return $user->hasRole(['manager', 'area_manager', 'admin']);
    }

    public function approveAdjustment(User $user, TimeEntry $entry): bool
    {
        if ($user->company_id !== $entry->company_id) {
            return false;
        }

        if (! $user->hasRole(['manager', 'area_manager', 'admin'])) {
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
