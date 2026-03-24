<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CompanySubscriptionBillingService;

class UserObserver
{
    public function created(User $user): void
    {
        $this->syncCompany($user->company_id);
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged('company_id')) {
            return;
        }

        $companyIds = array_filter([
            $user->getOriginal('company_id'),
            $user->company_id,
        ]);

        foreach (array_unique($companyIds) as $companyId) {
            $this->syncCompany($companyId);
        }
    }

    public function deleted(User $user): void
    {
        $this->syncCompany($user->company_id);
    }

    protected function syncCompany(?string $companyId): void
    {
        if (! $companyId) {
            return;
        }

        app(CompanySubscriptionBillingService::class)->syncRecurringUsageForCompanyId($companyId);
    }
}
