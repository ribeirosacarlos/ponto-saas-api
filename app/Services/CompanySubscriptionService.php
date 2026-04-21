<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Subscription;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;

class CompanySubscriptionService
{
    public const BLOCK_REASON_SUBSCRIPTION_EXPIRED = 'subscription_expired';
    public const BLOCK_REASON_PAYMENT_PAST_DUE = 'payment_past_due';
    public const BLOCK_REASON_TRIAL_EXPIRED = 'trial_expired';

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
        if ($company->is_blocked) {
            return false;
        }

        if ($this->isTrialActive($company)) {
            return true;
        }

        if ($this->hasActiveSubscription($company)) {
            return true;
        }

        return $this->hasAccessUntil($company);
    }

    public function hasAccessUntil(Company $company, ?CarbonInterface $reference = null): bool
    {
        $accessExpiresAt = $this->normalizeTrialEndsAt($company->access_expires_at);

        if (! $accessExpiresAt) {
            return false;
        }

        $now = $reference ? Carbon::instance($reference) : Carbon::now();

        return $now->lessThan($accessExpiresAt);
    }

    public function buildSubscriptionPayload(
        Company $company,
        ?Subscription $subscription = null,
        ?string $subscriptionStatus = null
    ): array {
        $subscription ??= $company->subscription;
        $subscriptionStatus ??= $subscription?->status?->value ?? $company->subscription_status;

        $trialEndsAt = $this->normalizeTrialEndsAt($subscription?->trial_ends_at ?? $company->trial_ends_at ?? null);
        $currentPeriodEnd = $this->normalizeTrialEndsAt($subscription?->current_period_end);
        $accessExpiresAt = $this->normalizeTrialEndsAt($company->access_expires_at);
        $blockedAt = $this->normalizeTrialEndsAt($company->blocked_at);

        return [
            'status' => $subscriptionStatus,
            'status_label' => $this->statusLabel($subscriptionStatus),
            'next_action' => $this->nextAction($subscriptionStatus),
            'stripe_customer_id' => $company->stripe_customer_id,
            'stripe_subscription_id' => $subscription?->stripe_subscription_id,
            'subscription_status' => $subscriptionStatus,
            'trial_ends_at' => $trialEndsAt?->toIso8601String(),
            'trial_days_remaining' => $this->calculateDaysRemaining($trialEndsAt),
            'current_period_end' => $currentPeriodEnd?->toIso8601String(),
            'billing_days_remaining' => $this->calculateDaysRemaining($currentPeriodEnd),
            'subscription_ends_at' => $accessExpiresAt?->toIso8601String(),
            'cancel_at_period_end' => $subscription?->cancel_at_period_end,
            'canceled_at' => $subscription?->canceled_at?->toIso8601String(),
            'access_expires_at' => $accessExpiresAt?->toIso8601String(),
            'blocked_at' => $blockedAt?->toIso8601String(),
            'blocked_reason' => $company->blocked_reason,
            'is_blocked' => (bool) $company->is_blocked,
            'is_plan_active' => $this->canAccessSystem($company),
            'can_cancel' => (bool) ($subscription?->stripe_subscription_id && ! $subscription?->cancel_at_period_end),
        ];
    }

    public function expireAccess(Company $company, ?CarbonInterface $blockedAt = null): Company
    {
        $timestamp = $blockedAt ? Carbon::instance($blockedAt) : Carbon::now();

        $company->forceFill([
            'subscription_status' => SubscriptionStatus::CANCELED->value,
            'is_blocked' => true,
            'blocked_at' => $company->blocked_at ?? $timestamp,
            'blocked_reason' => self::BLOCK_REASON_SUBSCRIPTION_EXPIRED,
        ])->save();

        $subscription = $company->subscription;

        if ($subscription && $subscription->status !== SubscriptionStatus::CANCELED) {
            $subscription->forceFill([
                'status' => SubscriptionStatus::CANCELED,
                'canceled_at' => $subscription->canceled_at ?? $timestamp,
                'cancel_at_period_end' => false,
            ])->save();
        }

        return $company->refresh();
    }

    public function blockPastDue(Company $company, ?CarbonInterface $blockedAt = null): Company
    {
        $timestamp = $blockedAt ? Carbon::instance($blockedAt) : Carbon::now();

        $company->forceFill([
            'is_blocked' => true,
            'blocked_at' => $company->blocked_at ?? $timestamp,
            'blocked_reason' => self::BLOCK_REASON_PAYMENT_PAST_DUE,
        ])->save();

        return $company->refresh();
    }

    public function blockExpiredTrial(Company $company, ?CarbonInterface $blockedAt = null): Company
    {
        $timestamp = $blockedAt ? Carbon::instance($blockedAt) : Carbon::now();

        $company->forceFill([
            'is_blocked' => true,
            'blocked_at' => $company->blocked_at ?? $timestamp,
            'blocked_reason' => self::BLOCK_REASON_TRIAL_EXPIRED,
        ])->save();

        return $company->refresh();
    }

    public function restoreAccess(Company $company): Company
    {
        if (! $company->is_blocked || ! $this->isSubscriptionManagedBlock($company)) {
            return $company;
        }

        $company->forceFill([
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ])->save();

        return $company->refresh();
    }

    public function isAccessExpired(Company $company, ?CarbonInterface $reference = null): bool
    {
        $accessExpiresAt = $this->normalizeTrialEndsAt($company->access_expires_at);

        if (! $accessExpiresAt) {
            return false;
        }

        $now = $reference ? Carbon::instance($reference) : Carbon::now();

        return $now->greaterThanOrEqualTo($accessExpiresAt);
    }

    public function isSubscriptionManagedBlock(Company $company): bool
    {
        return in_array($company->blocked_reason, [
            self::BLOCK_REASON_SUBSCRIPTION_EXPIRED,
            self::BLOCK_REASON_PAYMENT_PAST_DUE,
            self::BLOCK_REASON_TRIAL_EXPIRED,
        ], true);
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

    protected function calculateDaysRemaining(?Carbon $date): ?int
    {
        if (! $date) {
            return null;
        }

        return max(0, Carbon::now()->diffInDays($date, false));
    }

    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            SubscriptionStatus::TRIALING->value => 'Trial',
            SubscriptionStatus::ACTIVE->value => 'Active',
            SubscriptionStatus::PAST_DUE->value => 'Payment required',
            SubscriptionStatus::CANCELED->value => 'Canceled',
            default => 'Unknown',
        };
    }

    protected function nextAction(?string $status): string
    {
        return match ($status) {
            SubscriptionStatus::PAST_DUE->value => 'UPDATE_PAYMENT_METHOD',
            SubscriptionStatus::CANCELED->value => 'RESUBSCRIBE',
            default => 'NONE',
        };
    }
}
