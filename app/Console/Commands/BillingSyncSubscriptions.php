<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class BillingSyncSubscriptions extends Command
{
    protected $signature = 'billing:sync-subscriptions {--company=}';
    protected $description = 'Sincroniza o status das assinaturas (trial x past_due).';

    public function __construct(protected SubscriptionService $subscriptionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($companyId = $this->option('company')) {
            $company = Company::findOrFail($companyId);
            $subscription = $company->subscription ?? $this->subscriptionService->ensureDefaultTrial($company);

            if ($subscription) {
                $this->subscriptionService->syncStatus($subscription);
            }

            return 0;
        }

        Subscription::chunk(100, function ($subscriptions) {
            foreach ($subscriptions as $subscription) {
                $this->subscriptionService->syncStatus($subscription);
            }
        });

        return 0;
    }
}
