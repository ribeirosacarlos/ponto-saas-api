<?php

namespace App\Policies;

use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\User;

class CommercialEmailSequenceEnrollmentPolicy
{
    public function manage(User $user, CommercialEmailSequenceEnrollment $enrollment): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager'])
            || ($user->hasRole('commercial_agent') && (string) $enrollment->lead->assigned_to_user_id === (string) $user->id);
    }
}
