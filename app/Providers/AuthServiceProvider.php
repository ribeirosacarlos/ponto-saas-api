<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\CommercialAffiliate;
use App\Models\CommercialCommission;
use App\Models\CommercialLead;
use App\Models\CommercialLeadStep;
use App\Models\Company;
use App\Models\Document;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use App\Policies\CommercialAffiliatePolicy;
use App\Policies\CommercialCommissionPolicy;
use App\Policies\CommercialLeadPolicy;
use App\Policies\CommercialLeadStepPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EmployeeTimesheetPolicy;
use App\Policies\MonthlyClosurePolicy;
use App\Policies\TimeEntryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Announcement::class => AnnouncementPolicy::class,
        Company::class => CompanyPolicy::class,
        CommercialAffiliate::class => CommercialAffiliatePolicy::class,
        CommercialCommission::class => CommercialCommissionPolicy::class,
        CommercialLead::class => CommercialLeadPolicy::class,
        CommercialLeadStep::class => CommercialLeadStepPolicy::class,
        Document::class => DocumentPolicy::class,
        EmployeeTimesheet::class => EmployeeTimesheetPolicy::class,
        MonthlyClosure::class => MonthlyClosurePolicy::class,
        TimeEntry::class => TimeEntryPolicy::class,
        User::class => EmployeePolicy::class, // employee policy
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
