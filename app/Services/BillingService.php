<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Subscription;
use Carbon\Carbon;

class BillingService
{
    public function getCompanySubscription(Company $company): ?Subscription
    {
        if ($company->relationLoaded('subscription')) {
            return $company->subscription;
        }

        return $company->loadMissing('subscription.plan')->subscription;
    }

    public function markPastDue(Subscription $subscription, ?\DateTimeInterface $since = null): Subscription
    {
        $pastDueSince = $since ? Carbon::instance($since) : Carbon::now();
        $graceDays = $subscription->grace_period_days ?? config('billing.grace_period_days_default', 7);
        $graceEndsAt = $pastDueSince->copy()->addDays($graceDays);

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'past_due_since' => $pastDueSince,
            'grace_period_ends_at' => $graceEndsAt,
        ]);

        return $subscription->refresh();
    }

    public function activate(Subscription $subscription, \DateTimeInterface|string $periodEnd): Subscription
    {
        $end = $periodEnd instanceof \DateTimeInterface ? $periodEnd : Carbon::parse($periodEnd);

        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'current_period_end' => $end,
            'past_due_since' => null,
            'grace_period_ends_at' => null,
            'canceled_at' => null,
        ]);

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::CANCELED,
            'canceled_at' => Carbon::now(),
        ]);

        return $subscription->refresh();
    }

    public function isBlocked(Company $company): bool
    {
        if ($company->is_blocked) {
            return true;
        }

        return $this->getCompanySubscription($company)?->isBlocked() ?? false;
    }
}
