<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function updateTimezone(User $user, Company $company): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->hasRole('admin')) {
            return false;
        }

        return $user->company_id === $company->id;
    }

    public function updateGeolocation(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }

    public function viewLocationSettings(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }

    public function updateLocationSettings(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }

    public function updateDeviceSettings(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }

    public function updateSignatureSettings(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }

    public function updateInfo(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }

    public function updateLocale(User $user, Company $company): bool
    {
        return $this->updateTimezone($user, $company);
    }
}
