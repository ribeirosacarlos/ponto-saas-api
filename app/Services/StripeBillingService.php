<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\ExtraEmployeeCharge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\BillingPortal\Session as StripeBillingPortalSession;
use Stripe\Event;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

class StripeBillingService
{
    public function __construct(
        protected BillingService $billingService,
        protected StripeClient $stripe
    ) {
    }

    public function createCheckoutSession(Company $company, Plan $plan, ?User $user = null): StripeCheckoutSession
    {
        $customerId = $this->ensureCustomer($company, $user);

        return $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'client_reference_id' => $company->id,
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $this->buildFrontendUrl('/billing/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => $this->buildFrontendUrl('/billing/canceled'),
            'allow_promotion_codes' => true,
            'subscription_data' => [
                'metadata' => [
                    'company_id' => $company->id,
                    'user_id' => $user?->id,
                    'plan_id' => $plan->id,
                ],
            ],
            'payment_method_types' => ['card'],
        ]);
    }

    public function createBillingPortalSession(Company $company): StripeBillingPortalSession
    {
        if (! $company->stripe_customer_id) {
            throw new \LogicException('Stripe customer not registered.');
        }

        return $this->stripe->billingPortal->sessions->create([
            'customer' => $company->stripe_customer_id,
            'return_url' => $this->buildFrontendUrl('/billing/portal'),
        ]);
    }

    public function createExtraEmployeeCheckoutSession(
        Company $company,
        Plan $plan,
        ExtraEmployeeCharge $charge,
        ?User $user = null
    ): StripeCheckoutSession {
        $customerId = $this->ensureCustomer($company, $user);

        return $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'client_reference_id' => $company->id,
            'mode' => 'payment',
            'line_items' => [
                [
                    'price' => $plan->stripe_extra_employee_price_id,
                    'quantity' => $charge->quantity,
                ],
            ],
            'success_url' => $this->buildFrontendUrl('/billing/success?session_id={CHECKOUT_SESSION_ID}&context=extra-employees'),
            'cancel_url' => $this->buildFrontendUrl('/billing/canceled?context=extra-employees'),
            'payment_method_types' => ['card'],
            'metadata' => [
                'billing_component' => 'extra_employee_pending',
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'plan_id' => $plan->id,
                'extra_employee_charge_id' => $charge->id,
                'quantity' => (string) $charge->quantity,
            ],
        ]);
    }

    public function ensureCustomer(Company $company, ?User $user = null): string
    {
        if ($company->stripe_customer_id) {
            return $company->stripe_customer_id;
        }

        $customerEmail = $user?->email
            ?? $company->email
            ?? config('mail.from.address')
            ?? 'no-reply@example.com';

        $customer = $this->stripe->customers->create([
            'email' => $customerEmail,
            'metadata' => [
                'company_id' => $company->id,
                'user_id' => $user?->id,
            ],
        ]);

        $company->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    public function processEvent(Event $event): void
    {
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSession($event->data->object ?? null);
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                $this->handleStripeSubscription($event->data->object ?? null);
                break;
            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object ?? null);
                break;
            default:
                Log::info('Stripe webhook ignored', [
                    'event_id' => $event->id,
                    'type' => $event->type,
                ]);
        }
    }

    protected function handleCheckoutSession(?StripeCheckoutSession $session): void
    {
        if (! $session) {
            return;
        }

        $metadata = $this->metadataToArray($session->metadata ?? []);

        if (($metadata['billing_component'] ?? null) === 'extra_employee_pending') {
            $this->handleExtraEmployeeCheckoutSession($session, $metadata);
            return;
        }

        if (empty($session->subscription)) {
            Log::warning('Stripe checkout.session.completed without subscription', [
                'session' => $session->id,
            ]);

            return;
        }

        $company = $this->resolveCompanyFromMetadata($metadata, $session->customer);

        if (! $company) {
            Log::warning('Stripe checkout session without company', [
                'session' => $session->id,
            ]);

            return;
        }

        $stripeSubscription = $this->buildTestSubscriptionFromMetadata($metadata)
            ?? $this->stripe->subscriptions->retrieve($session->subscription);

        $this->syncStripeSubscription($company, $stripeSubscription);
    }

    protected function handleExtraEmployeeCheckoutSession(StripeCheckoutSession $session, array $metadata): void
    {
        $chargeId = $metadata['extra_employee_charge_id'] ?? null;

        if (! $chargeId) {
            Log::warning('Stripe extra employee checkout without charge id', [
                'session' => $session->id,
            ]);

            return;
        }

        $charge = ExtraEmployeeCharge::with('company')->find($chargeId);

        if (! $charge) {
            Log::warning('Stripe extra employee checkout charge not found', [
                'session' => $session->id,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        if ($charge->isPaid()) {
            return;
        }

        $company = $charge->company;

        if (! $company) {
            Log::warning('Stripe extra employee checkout without company', [
                'session' => $session->id,
                'charge_id' => $chargeId,
            ]);

            return;
        }

        $company->update([
            'paid_extra_employee_allowance' => max(0, (int) $company->paid_extra_employee_allowance) + (int) $charge->quantity,
        ]);

        $charge->update([
            'status' => 'paid',
            'paid_at' => now(),
            'stripe_checkout_session_id' => $session->id,
        ]);
    }

    protected function handleStripeSubscription(?StripeSubscription $stripeSubscription): void
    {
        if (! $stripeSubscription) {
            return;
        }

        $metadata = $this->metadataToArray($stripeSubscription->metadata ?? []);
        $company = $this->resolveCompanyFromMetadata($metadata, $stripeSubscription->customer);

        if (! $company) {
            Log::warning('Customer subscription webhook without company', [
                'stripe_subscription_id' => $stripeSubscription->id,
            ]);

            return;
        }

        $this->syncStripeSubscription($company, $stripeSubscription);
    }

    protected function handleInvoicePaymentFailed(?\Stripe\Invoice $invoice): void
    {
        if (! $invoice || empty($invoice->subscription)) {
            return;
        }

        $subscription = Subscription::firstWhere('stripe_subscription_id', $invoice->subscription);

        if (! $subscription) {
            Log::warning('Invoice payment_failed could not find subscription', [
                'invoice_id' => $invoice->id,
            ]);

            return;
        }

        $subscription = $this->billingService->markPastDue($subscription, now());

        $subscription->company()?->update([
            'subscription_status' => SubscriptionStatus::PAST_DUE->value,
        ]);
    }

    protected function syncStripeSubscription(Company $company, StripeSubscription $stripeSubscription): Subscription
    {
        $plan = $this->resolvePlan($stripeSubscription);
        $status = $this->mapStripeStatus($stripeSubscription->status ?? '');
        $subscription = Subscription::firstOrNew(['company_id' => $company->id]);

        $subscription->fill([
            'plan_id' => $plan?->id,
            'status' => $status,
            'trial_ends_at' => $this->toCarbon($stripeSubscription->trial_end ?? null),
            'current_period_start' => $this->toCarbon($stripeSubscription->current_period_start ?? null),
            'current_period_end' => $this->toCarbon($stripeSubscription->current_period_end ?? null),
            'canceled_at' => $this->toCarbon($stripeSubscription->canceled_at ?? null),
            'cancel_at_period_end' => (bool) $stripeSubscription->cancel_at_period_end,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeSubscription->customer,
            'stripe_price_id' => $this->resolvePriceId($stripeSubscription),
            'stripe_subscription_item_id' => $this->resolveBaseSubscriptionItemId($stripeSubscription, $plan),
            'stripe_extra_subscription_item_id' => $this->resolveExtraSubscriptionItemId($stripeSubscription, $plan),
            'metadata' => $this->metadataToArray($stripeSubscription->metadata ?? []),
        ]);

        $subscription->save();

        $company->update([
            'stripe_customer_id' => $stripeSubscription->customer,
            'subscription_status' => $status->value,
            'current_plan_id' => $plan?->id ?? $company->current_plan_id,
        ]);

        return $subscription->refresh();
    }

    protected function resolvePlan(StripeSubscription $subscription): ?Plan
    {
        $metadata = $this->metadataToArray($subscription->metadata ?? []);

        if (! empty($metadata['plan_id']) && ($plan = Plan::find($metadata['plan_id']))) {
            return $plan;
        }

        $priceId = $this->resolvePriceId($subscription);

        if ($priceId) {
            return Plan::firstWhere('stripe_price_id', $priceId);
        }

        return null;
    }

    protected function resolvePriceId(StripeSubscription $subscription): ?string
    {
        $item = $this->resolveBaseSubscriptionItem($subscription) ?? ($subscription->items?->data[0] ?? null);

        return $item?->price?->id ?? null;
    }

    public function syncExtraEmployeeSubscriptionItem(
        Company $company,
        Subscription $subscription,
        Plan $plan,
        array $summary
    ): array {
        $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->stripe_subscription_id);
        $extraItem = $this->resolveExtraSubscriptionItem($stripeSubscription, $plan);
        $currentExtraEmployees = (int) ($extraItem?->quantity ?? 0);
        $targetExtraEmployees = (int) ($summary['extra_employees'] ?? 0);

        Log::info('Recurring billing sync started', [
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'current_extra_employees' => $currentExtraEmployees,
            'target_extra_employees' => $targetExtraEmployees,
        ]);

        if ($currentExtraEmployees === $targetExtraEmployees) {
            Log::info('Recurring billing sync skipped: extras unchanged', [
                'company_id' => $company->id,
                'subscription_id' => $subscription->id,
                'extra_employees' => $targetExtraEmployees,
            ]);

            $this->syncStripeSubscription($company, $stripeSubscription);

            return $summary;
        }

        Log::info('Recurring billing extra employees changed', [
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'from' => $currentExtraEmployees,
            'to' => $targetExtraEmployees,
        ]);

        if ($targetExtraEmployees > 0 && ! $extraItem) {
            $this->stripe->subscriptionItems->create([
                'subscription' => $subscription->stripe_subscription_id,
                'price' => $plan->stripe_extra_employee_price_id,
                'quantity' => $targetExtraEmployees,
                'proration_behavior' => 'none',
            ]);
        } elseif ($targetExtraEmployees > 0 && $extraItem) {
            $this->stripe->subscriptionItems->update($extraItem->id, [
                'quantity' => $targetExtraEmployees,
                'proration_behavior' => 'none',
            ]);
        } elseif ($extraItem) {
            $this->stripe->subscriptionItems->delete($extraItem->id, [
                'proration_behavior' => 'none',
            ]);
        }

        $updatedStripeSubscription = $this->stripe->subscriptions->retrieve($subscription->stripe_subscription_id);
        $this->syncStripeSubscription($company, $updatedStripeSubscription);

        Log::info('Recurring billing sync succeeded', [
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'extra_employees' => $targetExtraEmployees,
        ]);

        return $summary;
    }

    protected function mapStripeStatus(string $status): SubscriptionStatus
    {
        return match ($status) {
            'trialing' => SubscriptionStatus::TRIALING,
            'active' => SubscriptionStatus::ACTIVE,
            'past_due', 'unpaid', 'incomplete' => SubscriptionStatus::PAST_DUE,
            'canceled', 'incomplete_expired' => SubscriptionStatus::CANCELED,
            default => SubscriptionStatus::PAST_DUE,
        };
    }

    protected function resolveCompanyFromMetadata(array $metadata, ?string $customerId): ?Company
    {
        if (! empty($metadata['company_id'])) {
            return Company::find($metadata['company_id']);
        }

        if ($customerId) {
            return Company::firstWhere('stripe_customer_id', $customerId);
        }

        return null;
    }

    protected function metadataToArray(mixed $value): array
    {
        if (! $value) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return [];
    }

    protected function resolveBaseSubscriptionItem(StripeSubscription $subscription): mixed
    {
        $items = $subscription->items?->data ?? [];

        foreach ($items as $item) {
            if (! $this->looksLikeExtraSubscriptionItem($item)) {
                return $item;
            }
        }

        return $items[0] ?? null;
    }

    protected function resolveBaseSubscriptionItemId(StripeSubscription $subscription, ?Plan $plan): ?string
    {
        $items = $subscription->items?->data ?? [];

        foreach ($items as $item) {
            if ($plan && $item?->price?->id === $plan->stripe_price_id) {
                return $item->id;
            }
        }

        return $this->resolveBaseSubscriptionItem($subscription)?->id;
    }

    protected function resolveExtraSubscriptionItem(StripeSubscription $subscription, ?Plan $plan): mixed
    {
        $items = $subscription->items?->data ?? [];

        foreach ($items as $item) {
            if ($plan && filled($plan->stripe_extra_employee_price_id) && $item?->price?->id === $plan->stripe_extra_employee_price_id) {
                return $item;
            }

            if ($this->looksLikeExtraSubscriptionItem($item)) {
                return $item;
            }
        }

        return null;
    }

    protected function resolveExtraSubscriptionItemId(StripeSubscription $subscription, ?Plan $plan): ?string
    {
        return $this->resolveExtraSubscriptionItem($subscription, $plan)?->id;
    }

    protected function looksLikeExtraSubscriptionItem(mixed $item): bool
    {
        $nickname = strtolower((string) data_get($item, 'price.nickname', ''));
        $lookupKey = strtolower((string) data_get($item, 'price.lookup_key', ''));
        $metadata = array_change_key_case($this->metadataToArray(data_get($item, 'price.metadata', [])), CASE_LOWER);

        if (($metadata['billing_component'] ?? null) === 'extra_employee') {
            return true;
        }

        return str_contains($nickname, 'extra employee')
            || str_contains($nickname, 'extra collaborator')
            || str_contains($lookupKey, 'extra_employee')
            || str_contains($lookupKey, 'extra-collaborator');
    }

    protected function toCarbon(?int $timestamp): ?Carbon
    {
        if (! $timestamp) {
            return null;
        }

        return Carbon::createFromTimestamp($timestamp);
    }

    protected function buildFrontendUrl(string $path = ''): string
    {
        $base = rtrim(config('app.frontend_url') ?? config('app.url'), '/');
        $relative = ltrim($path, '/');

        if ($relative === '') {
            return $base;
        }

        return $base . '/' . $relative;
    }

    /**
     * Build a fake Stripe subscription when the metadata carries one, which is
     * useful for manual/local webhook testing without calling Stripe.
     */
    protected function buildTestSubscriptionFromMetadata(array $metadata): ?StripeSubscription
    {
        if (empty($metadata['mock_subscription'])) {
            return null;
        }

        $payload = json_decode($metadata['mock_subscription'], true);

        if (! is_array($payload)) {
            return null;
        }

        return StripeSubscription::constructFrom($payload);
    }
}
