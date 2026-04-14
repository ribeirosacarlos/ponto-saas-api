<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Support\Facades\Log;

class CompanySubscriptionBillingService
{
    public function __construct(protected StripeBillingService $stripeBillingService)
    {
    }

    public function buildRecurringSummary(Company $company): array
    {
        $company->loadMissing(['subscription.plan', 'currentPlan']);

        $subscription = $company->subscription;
        $plan = $company->currentPlan ?? $subscription?->plan;
        $activeEmployees = $this->countBillableEmployees($company);
        $includedEmployees = max(0, (int) data_get($plan?->quotas ?? [], 'max_employees', 0));
        $extraEmployeePriceCents = max(0, (int) ($plan?->extra_employee_price_cents ?? 0));
        $extraEmployees = max(0, $activeEmployees - $includedEmployees);
        $extraTotalCents = $extraEmployees * $extraEmployeePriceCents;
        $basePriceCents = (int) ($plan?->price_cents ?? 0);

        return [
            'currency' => $plan?->currency,
            'base_price_cents' => $basePriceCents,
            'included_employees' => $includedEmployees,
            'active_employees' => $activeEmployees,
            'extra_employees' => $extraEmployees,
            'extra_employee_price_cents' => $extraEmployeePriceCents,
            'extra_total_cents' => $extraTotalCents,
            'total_price_cents' => $basePriceCents + $extraTotalCents,
            'stripe_price_id' => $plan?->stripe_price_id,
            'stripe_extra_employee_price_id' => $plan?->stripe_extra_employee_price_id,
        ];
    }

    public function syncRecurringUsage(Company $company): array
    {
        $summary = $this->buildRecurringSummary($company);
        $subscription = $company->subscription;
        $plan = $company->currentPlan ?? $subscription?->plan;

        if (! $subscription || ! $plan) {
            return $summary;
        }

        if (! $subscription->stripe_subscription_id) {
            Log::info('Recurring billing sync skipped: local subscription without Stripe subscription', [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
            ]);

            return $summary;
        }

        if (! $this->planSupportsExtraEmployees($plan)) {
            return $summary;
        }

        try {
            return $this->stripeBillingService->syncExtraEmployeeSubscriptionItem(
                $company,
                $subscription,
                $plan,
                $summary
            );
        } catch (\Throwable $e) {
            Log::error('Recurring billing sync failed', [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'extra_employees' => $summary['extra_employees'],
                'error' => $e->getMessage(),
            ]);

            return $summary;
        }
    }

    public function syncExtraEmployeesAfterAdminConfirmation(Company $company): array
    {
        $company->loadMissing(['subscription.plan', 'currentPlan']);

        $subscription = $company->subscription;
        $plan = $company->currentPlan ?? $subscription?->plan;

        if (! $subscription || ! $plan) {
            throw new \LogicException('Assinatura não encontrada.');
        }

        if (! $subscription->isActive()) {
            throw new \LogicException('A sincronização de colaboradores extras só pode ocorrer com assinatura ativa.');
        }

        if (! $this->planSupportsExtraEmployees($plan)) {
            throw new \LogicException('O plano atual não possui cobrança recorrente de colaboradores extras.');
        }

        return $this->syncRecurringUsage($company);
    }

    protected function countBillableEmployees(Company $company): int
    {
        return $company->employeeUsersCount();
    }

    protected function planSupportsExtraEmployees(Plan $plan): bool
    {
        return (int) ($plan->extra_employee_price_cents ?? 0) > 0
            && filled($plan->stripe_extra_employee_price_id);
    }
}
