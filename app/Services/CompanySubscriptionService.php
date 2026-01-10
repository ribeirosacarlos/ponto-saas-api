<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use Carbon\Carbon;
use DateTimeInterface;

class CompanySubscriptionService
{
    /**
     * @var array<string>
     */
    protected array $plansWithTrial = [
        'basic',
    ];

    public function isTrialActive(Company $company): bool
    {
        if (! $this->planHasTrial($company)) {
            return false;
        }

        $trialEndsAt = $this->normalizeTrialEndsAt($company->trial_ends_at);

        if (! $trialEndsAt) {
            return false;
        }

        return Carbon::now()->lessThanOrEqualTo($trialEndsAt);
    }

    public function hasActiveSubscription(Company $company): bool
    {
        $status = $this->resolveSubscriptionStatus($company);

        return $status === SubscriptionStatus::ACTIVE->value;
    }

    public function canAccessSystem(Company $company): bool
    {
        if ($this->isTrialActive($company)) {
            return true;
        }

        return $this->hasActiveSubscription($company);
    }

    protected function planHasTrial(Company $company): bool
    {
        $slug = $this->resolvePlanSlug($company);

        return $slug !== null && in_array($slug, $this->plansWithTrial, true);
    }

    protected function resolvePlanSlug(Company $company): ?string
    {
        $rawSlug = $company->plan_slug ?? $company->plan ?? null;

        if (! $rawSlug || ! is_string($rawSlug)) {
            return null;
        }

        return strtolower(trim($rawSlug));
    }

    protected function resolveSubscriptionStatus(Company $company): ?string
    {
        // Local cache for the subscription status. Sync jobs may replace this with live data from Stripe.
        return $company->subscription_status;
    }

    protected function normalizeTrialEndsAt(mixed $trialEndsAt): ?Carbon
    {
        if ($trialEndsAt instanceof Carbon) {
            return $trialEndsAt;
        }

        if ($trialEndsAt instanceof DateTimeInterface) {
            return Carbon::instance($trialEndsAt);
        }

        if (is_string($trialEndsAt)) {
            return Carbon::parse($trialEndsAt);
        }

        return null;
    }
}
