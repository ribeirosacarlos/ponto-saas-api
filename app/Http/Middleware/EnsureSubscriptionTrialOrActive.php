<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Services\BillingService;
use App\Services\CompanySubscriptionService;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;

class EnsureSubscriptionTrialOrActive
{
    public function __construct(
        protected BillingService $billingService,
        protected SubscriptionService $subscriptionService,
        protected CompanySubscriptionService $companySubscriptionService
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

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
                    $this->companySubscriptionService->blockExpiredTrial($company);
                }

                return response()->json(['message' => 'Trial expirado.'], 402);
            }

            if ($subscription->status === SubscriptionStatus::CANCELED) {
                return response()->json(['message' => 'Assinatura cancelada.'], 402);
            }

            if ($subscription->status === SubscriptionStatus::PAST_DUE && $this->billingService->isBlocked($company)) {
                $this->companySubscriptionService->blockPastDue($company);

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
}
