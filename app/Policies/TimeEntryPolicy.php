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
        if ($user->company_id !== $entry->company_id) {
            return false;
        }

        // employee sees own entries only
        if ($user->hasRole('employee')) {
            return $entry->user_id === $user->id;
        }

        // manager / area_manager / admin can view all entries from the same company
        return $user->hasRole(['manager', 'area_manager', 'admin']);
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
