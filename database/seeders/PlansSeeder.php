<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $annualDiscount = 0.20; // 20% desconto anual

        // Planos base (mensais)
        $basePlans = [
            [
                'slug' => 'basic',
                'name' => 'Plano Basic',
                'description' => 'Ideal para começar: até 5 colaboradores com tudo que você precisa.',
                'price_cents' => 900, // €9
                'currency' => 'EUR',
                'billing_interval' => 'month',
                'trial_days' => 15, // ✅ somente Basic tem trial
                'is_active' => true,
                'sort_order' => 10,
                'features' => [
                    'reports' => true,
                    'exports' => true,
                    'geolocation' => true,
                ],
                'quotas' => [
                    'max_employees' => 5,
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Plano Pro',
                'description' => 'Para equipes em crescimento: recursos completos e mais capacidade.',
                'price_cents' => 1800, // €18
                'currency' => 'EUR',
                'billing_interval' => 'month',
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 20,
                'features' => [
                    'reports' => true,
                    'exports' => true,
                    'geolocation' => true,
                    'api' => true,
                ],
                'quotas' => [
                    'max_employees' => 20, // recomendado para “degrau” natural
                ],
            ],
            [
                'slug' => 'business',
                'name' => 'Plano Business',
                'description' => 'Para operações maiores: até 50 colaboradores com suporte e escalabilidade.',
                'price_cents' => 2900, // ✅ recomendado €29
                'currency' => 'EUR',
                'billing_interval' => 'month',
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 30,
                'features' => [
                    'reports' => true,
                    'exports' => true,
                    'geolocation' => true,
                    'api' => true,
                    // se quiser diferenciar: 'priority_support' => true
                ],
                'quotas' => [
                    'max_employees' => 50,
                ],
            ],
        ];

        foreach ($basePlans as $plan) {
            // Mensal
            Plan::updateOrCreate(
                [
                    'slug' => "{$plan['slug']}_monthly",
                    'billing_interval' => 'month',
                ],
                [
                    ...$plan,
                    'slug' => "{$plan['slug']}_monthly",
                    'billing_interval' => 'month',
                ]
            );

            // Anual (com desconto)
            $annualPrice = (int) round($plan['price_cents'] * 12 * (1 - $annualDiscount));

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
                    // trial no anual: normalmente manter igual ao mensal (15 dias no basic)
                    'trial_days' => $plan['trial_days'],
                    'sort_order' => $plan['sort_order'] + 1,
                ]
            );
        }
    }
}
