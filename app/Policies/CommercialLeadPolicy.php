<?php

namespace App\Policies;

use App\Models\CommercialAffiliate;
use App\Models\CommercialLead;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class CommercialLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function view(Authenticatable $user, CommercialLead $lead): bool
    {
        if ($user instanceof CommercialAffiliate) {
            return (string) $lead->affiliate_id === (string) $user->id;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function create(Authenticatable $user): bool
    {
        if ($user instanceof CommercialAffiliate) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function update(Authenticatable $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function delete(User $user, CommercialLead $lead): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function assign(User $user, CommercialLead $lead): bool
    {
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function moveStep(Authenticatable $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function addNote(Authenticatable $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function markWon(Authenticatable $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function markLost(Authenticatable $user, CommercialLead $lead): bool
    {
        return $this->view($user, $lead);
    }
}
