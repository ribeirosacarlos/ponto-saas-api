<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LeavePolicy;
use Illuminate\Database\Seeder;

class LeavePolicySeeder extends Seeder
{
    public function run(): void
    {
        Company::all()->each(function (Company $company) {
            LeavePolicy::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => 'Política Padrão Espanha',
                ],
                [
                    'days_per_year' => 30.00,
                    'accrual_rate_per_month' => 2.500,
                    'annual_entitlement_days' => 30.00,
                    'accrual_basis' => 'calendar_days',
                    'day_work_threshold_minutes' => 1,
                    'counting_method' => 'calendar_days',
                    'allow_carry_over' => false,
                ]
            );
        });
    }
}
