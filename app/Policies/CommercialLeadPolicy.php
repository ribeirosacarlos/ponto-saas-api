<?php

namespace App\Policies;

use App\Models\CommercialLead;
use App\Models\User;

class CommercialLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager', 'commercial_agent', 'affiliate']);
    }

    public function view(User $user, CommercialLead $lead): bool
    {
        if ($user->hasRole(['super_admin', 'commercial_manager'])) {
            return true;
        }

        if ($user->hasRole('commercial_agent')
            && $lead->assigned_to_user_id !== null
            && (string) $lead->assigned_to_user_id === (string) $user->id) {
            return true;
        }

        if ($user->hasRole('affiliate')) {
            $affiliateId = $user->commercialAffiliate?->id;

            return $affiliateId !== null && (string) $lead->affiliate_id === (string) $affiliateId;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager', 'commercial_agent', 'affiliate']);
    }

    public function update(User $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function delete(User $user, CommercialLead $lead): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function assign(User $user, CommercialLead $lead): bool
    {
        return $user->hasRole(['super_admin', 'commercial_manager']);
    }

    public function moveStep(User $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function addNote(User $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function markWon(User $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function markLost(User $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }
}
