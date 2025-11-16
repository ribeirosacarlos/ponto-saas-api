<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Models\TimeEntry;
use App\Models\Adjustment;
use App\Models\User;

use App\Policies\TimeEntryPolicy;
use App\Policies\AdjustmentPolicy;
use App\Policies\EmployeePolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        TimeEntry::class   => TimeEntryPolicy::class,
        Adjustment::class  => AdjustmentPolicy::class,
        User::class        => EmployeePolicy::class, // employee policy
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
