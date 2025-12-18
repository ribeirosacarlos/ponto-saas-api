<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\SubscriptionService;
use Illuminate\Database\Seeder;

class SubscriptionsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(SubscriptionService::class);

        Company::doesntHave('subscription')->chunk(100, function ($companies) use ($service) {
            foreach ($companies as $company) {
                $service->ensureDefaultTrial($company);
            }
        });
    }
}
