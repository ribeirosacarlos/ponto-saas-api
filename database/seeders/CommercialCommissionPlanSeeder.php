<?php

namespace Database\Seeders;

use App\Models\CommercialCommissionPlan;
use Illuminate\Database\Seeder;

class CommercialCommissionPlanSeeder extends Seeder
{
    public function run(): void
    {
        CommercialCommissionPlan::updateOrCreate(
            ['name' => 'Plano padrão afiliados'],
            [
                'commission_type' => 'recurring_percentage',
                'commission_percentage' => 20,
                'recurrence_months' => 6,
                'bonus_enabled' => true,
                'bonus_every_clients' => 5,
                'bonus_amount' => 50,
                'active' => true,
            ],
        );
    }
}
