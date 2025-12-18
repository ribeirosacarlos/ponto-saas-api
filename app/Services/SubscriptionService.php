<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;

class SubscriptionService
{
    public function __construct(protected BillingService $billingService)
    {
    }

    public function ensureDefaultTrial(Company $company): ?Subscription
    {
        $existing = Subscription::firstWhere('company_id', $company->id);

        if ($existing) {
            return $existing->loadMissing('plan');
        }

        $planSlug = config('billing.default_plan_slug', 'free');
        $plan = Plan::where('slug', $planSlug)->first();

        if (! $plan) {
            return null;
        }

        $trialDays = (int) ($plan->trial_days ?? config('billing.trial_days_default', 14));

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::TRIALING,
            'trial_ends_at' => now()->addDays($trialDays),
            'current_period_start' => now(),
            'grace_period_days' => config('billing.grace_period_days_default', 7),
        ]);

        return $subscription->loadMissing('plan');
    }

    public function syncStatus(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::TRIALING
            && $subscription->trial_ends_at
            && now()->greaterThan($subscription->trial_ends_at)) {
            $subscription = $this->billingService->markPastDue($subscription, now());
        }

        return $subscription->refresh();
    }
}
