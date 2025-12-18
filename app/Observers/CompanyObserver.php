<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\SubscriptionService;

class CompanyObserver
{
    public function created(Company $company): void
    {
        app(SubscriptionService::class)->ensureDefaultTrial($company);
    }
}
