<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanySubscriptionService;
use Illuminate\Console\Command;

class SyncExpiredCompanyAccess extends Command
{
    protected $signature = 'subscriptions:sync-expired-access {--company=}';
    protected $description = 'Bloqueia empresas cujo acesso pago expirou.';

    public function __construct(protected CompanySubscriptionService $companySubscriptionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Company::query()
            ->with('subscription')
            ->whereNotNull('access_expires_at')
            ->where('access_expires_at', '<=', now());

        if ($companyId = $this->option('company')) {
            $query->whereKey($companyId);
        }

        $query->chunkById(100, function ($companies) {
            foreach ($companies as $company) {
                $this->companySubscriptionService->expireAccess($company);
            }
        }, 'id');

        return self::SUCCESS;
    }
}
