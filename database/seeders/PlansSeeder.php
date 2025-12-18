<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Plano Free',
                'description' => 'Plano gratuito com recursos básicos para testar a plataforma.',
                'price_cents' => 0,
                'currency' => 'EUR',
                'billing_interval' => 'month',
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 0,
                'features' => [
                    'reports' => false,
                    'exports' => false,
                    'geolocation' => true,
                ],
                'quotas' => [
                    'max_employees' => 5,
                ],
            ],
            [
                'slug' => 'basic',
                'name' => 'Plano Basic',
                'description' => 'Plano mensal para pequenas equipes.',
                'price_cents' => 2900,
                'currency' => 'EUR',
                'billing_interval' => 'month',
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 10,
                'features' => [
                    'reports' => true,
                    'exports' => true,
                    'geolocation' => true,
                ],
                'quotas' => [
                    'max_employees' => 20,
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Plano Pro',
                'description' => 'Plano avançado com recursos completos e API.',
                'price_cents' => 5900,
                'currency' => 'EUR',
                'billing_interval' => 'month',
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 20,
                'features' => [
                    'reports' => true,
                    'exports' => true,
                    'geolocation' => true,
                    'api' => true,
                ],
                'quotas' => [
                    'max_employees' => 999999,
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
