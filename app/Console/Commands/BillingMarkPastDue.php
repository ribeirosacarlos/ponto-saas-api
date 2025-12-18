<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\BillingService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class BillingMarkPastDue extends Command
{
    protected $signature = 'billing:mark-past-due {company}';
    protected $description = 'Marca assinatura de uma empresa como past due imediatamente.';

    public function __construct(
        protected BillingService $billingService,
        protected SubscriptionService $subscriptionService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $company = Company::findOrFail($this->argument('company'));
        $subscription = $company->subscription ?? $this->subscriptionService->ensureDefaultTrial($company);

        if (! $subscription) {
            $this->error('Assinatura não encontrada.');

            return 1;
        }

        $this->billingService->markPastDue($subscription);

        $this->info('Assinatura marcada como past due.');

        return 0;
    }
}
