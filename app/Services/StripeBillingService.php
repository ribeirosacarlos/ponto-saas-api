<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
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

    public function createCheckoutSession(Company $company, Plan $plan, User $user): StripeCheckoutSession
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
                    'user_id' => $user->id,
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

    public function ensureCustomer(Company $company, User $user): string
    {
        if ($company->stripe_customer_id) {
            return $company->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'metadata' => [
                'company_id' => $company->id,
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
        if (! $session || empty($session->subscription)) {
            Log::warning('Stripe checkout.session.completed without subscription', [
                'session' => $session?->id,
            ]);

            return;
        }

        $metadata = $this->metadataToArray($session->metadata ?? []);
        $company = $this->resolveCompanyFromMetadata($metadata, $session->customer);

        if (! $company) {
            Log::warning('Stripe checkout session without company', [
                'session' => $session->id,
            ]);

            return;
        }

        $stripeSubscription = $this->stripe->subscriptions->retrieve($session->subscription);

        $this->syncStripeSubscription($company, $stripeSubscription);
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
        $item = $subscription->items?->data[0] ?? null;

        return $item?->price?->id ?? null;
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
}
