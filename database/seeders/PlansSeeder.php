<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $annualDiscount = 0.20; // 20% de desconto no anual

        $basePlans = [
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

        foreach ($basePlans as $plan) {

            Plan::updateOrCreate(
                [
                    'slug' => "{$plan['slug']}_monthly",
                    'billing_interval' => 'month',
                ],
                [
                    ...$plan,
                    'slug' => "{$plan['slug']}_monthly",
                ]
            );

            // 🔹 Plano anual (exceto free)
            if ($plan['price_cents'] > 0) {
                $annualPrice = (int) round(
                    $plan['price_cents'] * 12 * (1 - $annualDiscount)
                );

                Plan::updateOrCreate(
                    [
                        'slug' => "{$plan['slug']}_yearly",
                        'billing_interval' => 'year',
                    ],
                    [
                        ...$plan,
                        'slug' => "{$plan['slug']}_yearly",
                        'name' => "{$plan['name']} Anual",
                        'description' => "{$plan['description']} (Pagamento anual com desconto)",
                        'price_cents' => $annualPrice,
                        'billing_interval' => 'year',
                        'sort_order' => $plan['sort_order'] + 1,
                    ]
                );
            }
        }
    }
}
