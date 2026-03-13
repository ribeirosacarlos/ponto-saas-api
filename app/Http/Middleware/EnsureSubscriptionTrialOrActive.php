<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Services\BillingService;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;

class EnsureSubscriptionTrialOrActive
{
    public function __construct(
        protected BillingService $billingService,
        protected SubscriptionService $subscriptionService
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->hasRole('super_admin')) {
            return $next($request);
        }

        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 403);
        }

        try {
            $subscription = $this->billingService->getCompanySubscription($company);

            if (! $subscription) {
                return response()->json(['message' => 'Assinatura necessária.'], 403);
            }

            $subscription = $this->subscriptionService->syncStatus($subscription);

            if ($subscription->status === SubscriptionStatus::TRIALING
                && $subscription->trial_ends_at
                && now()->greaterThan($subscription->trial_ends_at)) {
                $subscription = $this->billingService->markPastDue($subscription, now());

                if ($this->billingService->isBlocked($company)) {
                    $this->blockCompany($company, 'Trial expirado.');
                }

                return response()->json(['message' => 'Trial expirado.'], 402);
            }

            if ($subscription->status === SubscriptionStatus::CANCELED) {
                return response()->json(['message' => 'Assinatura cancelada.'], 402);
            }

            if ($subscription->status === SubscriptionStatus::PAST_DUE && $this->billingService->isBlocked($company)) {
                $this->blockCompany($company, 'Pagamento em atraso.');

                return response()->json(['message' => 'Assinatura em atraso.'], 402);
            }

            return $next($request);
        } catch (\Throwable $e) {
            \Log::error('subscription.active exception', [
                'company_id' => $company->id ?? null,
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        
            return response()->json(['message' => 'Erro ao validar assinatura.'], 500);
        }
    }

    protected function blockCompany(Company $company, string $reason): void
    {
        if ($company->is_blocked) {
            return;
        }

        $company->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => $reason,
        ]);
    }
}
