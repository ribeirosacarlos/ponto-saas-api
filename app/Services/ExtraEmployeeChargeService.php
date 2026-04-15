<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ExtraEmployeeCharge;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Stripe\Checkout\Session as StripeCheckoutSession;

class ExtraEmployeeChargeService
{
    private const MAX_UNPAID_EXTRA_EMPLOYEES = 3;
    private const PAYMENT_WINDOW_DAYS = 7;

    public function __construct(protected StripeBillingService $stripeBillingService)
    {
    }

    public function registerPendingExtraEmployees(Company $company, int $incrementBy = 1): ?ExtraEmployeeCharge
    {
        if ($incrementBy <= 0) {
            return null;
        }

        $plan = $this->resolvePlan($company);
        $includedEmployees = $this->includedEmployees($plan);

        if ($includedEmployees === null) {
            return null;
        }

        $employeeCount = $company->employeeUsersCount();
        $paidAllowance = max(0, (int) ($company->paid_extra_employee_allowance ?? 0));
        $prospectivePendingQuantity = max(0, ($employeeCount + $incrementBy) - ($includedEmployees + $paidAllowance));

        if ($prospectivePendingQuantity === 0) {
            return null;
        }

        $pendingCharge = $this->getPendingCharge($company);

        if ($pendingCharge?->isExpired()) {
            throw ValidationException::withMessages([
                'role' => 'Existe uma cobrança vencida de colaboradores extras. Regularize o pagamento antes de adicionar novos colaboradores.',
            ]);
        }

        $newQuantity = max($prospectivePendingQuantity, (int) ($pendingCharge?->quantity ?? 0));

        if ($newQuantity > self::MAX_UNPAID_EXTRA_EMPLOYEES) {
            throw ValidationException::withMessages([
                'role' => 'O limite de colaboradores extras pendentes de pagamento é 3.',
            ]);
        }

        if (! $plan || ! filled($plan->stripe_extra_employee_price_id)) {
            throw ValidationException::withMessages([
                'role' => 'O plano atual não possui cobrança configurada para colaboradores extras.',
            ]);
        }

        if ($pendingCharge) {
            if ($newQuantity > (int) $pendingCharge->quantity) {
                $pendingCharge->update([
                    'quantity' => $newQuantity,
                    'plan_id' => $plan->id,
                ]);
            }

            return $pendingCharge->refresh();
        }

        return ExtraEmployeeCharge::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'quantity' => $newQuantity,
            'status' => 'pending',
            'due_at' => now()->addDays(self::PAYMENT_WINDOW_DAYS),
            'metadata' => [
                'included_employees' => $includedEmployees,
                'paid_extra_employee_allowance' => $paidAllowance,
            ],
        ]);
    }

    public function createCheckoutSessionForPendingCharge(Company $company, ?User $user = null): StripeCheckoutSession
    {
        $pendingCharge = $this->getPendingCharge($company);

        if (! $pendingCharge) {
            throw ValidationException::withMessages([
                'extra_employees' => 'Não existe cobrança pendente de colaboradores extras.',
            ]);
        }

        $plan = $this->resolvePlan($company);

        if (! $plan || ! filled($plan->stripe_extra_employee_price_id)) {
            throw ValidationException::withMessages([
                'extra_employees' => 'O plano atual não possui Stripe price configurado para colaboradores extras.',
            ]);
        }

        $session = $this->stripeBillingService->createExtraEmployeeCheckoutSession(
            $company,
            $plan,
            $pendingCharge,
            $user
        );

        $pendingCharge->update([
            'stripe_checkout_session_id' => $session->id,
        ]);

        return $session;
    }

    public function getPendingCharge(Company $company): ?ExtraEmployeeCharge
    {
        return $company->extraEmployeeCharges()
            ->where('status', 'pending')
            ->latest('created_at')
            ->first();
    }

    public function buildOverviewPayload(Company $company): array
    {
        $pendingCharge = $this->getPendingCharge($company);

        return [
            'paid_allowance' => max(0, (int) ($company->paid_extra_employee_allowance ?? 0)),
            'has_pending_payment' => (bool) $pendingCharge,
            'pending_quantity' => (int) ($pendingCharge?->quantity ?? 0),
            'payment_due_at' => $pendingCharge?->due_at?->toIso8601String(),
            'payment_overdue' => $pendingCharge?->isExpired() ?? false,
            'max_unpaid_extra_employees' => self::MAX_UNPAID_EXTRA_EMPLOYEES,
        ];
    }

    protected function resolvePlan(Company $company): ?Plan
    {
        $company->loadMissing(['subscription.plan', 'currentPlan']);

        return $company->currentPlan ?? $company->subscription?->plan;
    }

    protected function includedEmployees(?Plan $plan): ?int
    {
        $maxEmployees = data_get($plan?->quotas ?? [], 'max_employees');

        if ($maxEmployees === null || $maxEmployees === '') {
            return null;
        }

        return max(0, (int) $maxEmployees);
    }
}
