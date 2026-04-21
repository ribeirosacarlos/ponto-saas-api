<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ExtraEmployeeCharge;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Stripe\Event;
use Tests\TestCase;

class StripeSubscriptionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_customer_subscription_webhook_persists_base_and_extra_subscription_item_ids(): void
    {
        $company = Company::factory()->create([
            'stripe_customer_id' => 'cus_test_123',
        ]);

        $plan = Plan::create([
            'slug' => 'pro-br-' . Str::lower(Str::random(6)),
            'name' => 'Plano Pro BR',
            'description' => 'Plano para testes',
            'price_cents' => 12000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 50,
            'features' => [],
            'quotas' => ['max_employees' => 15],
            'extra_employee_price_cents' => 1500,
            'stripe_price_id' => 'price_base_br',
            'stripe_extra_employee_price_id' => 'price_extra_br',
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_test',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'object' => 'subscription',
                    'id' => 'sub_test_123',
                    'customer' => 'cus_test_123',
                    'status' => 'active',
                    'cancel_at_period_end' => false,
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => now()->addMonth()->timestamp,
                    'metadata' => [
                        'company_id' => $company->id,
                        'plan_id' => $plan->id,
                    ],
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_base_123',
                                'quantity' => 1,
                                'price' => [
                                    'id' => 'price_base_br',
                                ],
                            ],
                            [
                                'id' => 'si_extra_123',
                                'quantity' => 3,
                                'price' => [
                                    'id' => 'price_extra_br',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->app->make(StripeBillingService::class)->processEvent($event);

        $subscription = Subscription::firstWhere('company_id', $company->id);

        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, $subscription->plan_id);
        $this->assertSame('sub_test_123', $subscription->stripe_subscription_id);
        $this->assertSame('price_base_br', $subscription->stripe_price_id);
        $this->assertSame('si_base_123', $subscription->stripe_subscription_item_id);
        $this->assertSame('si_extra_123', $subscription->stripe_extra_subscription_item_id);
        $this->assertNull($company->fresh()->access_expires_at);
    }

    public function test_customer_subscription_updated_sets_access_expiration_when_cancel_is_scheduled(): void
    {
        $plan = Plan::create([
            'slug' => 'cancel-flow-' . Str::lower(Str::random(6)),
            'name' => 'Cancel flow',
            'description' => 'Plano para cancelamento agendado.',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => [],
            'stripe_price_id' => 'price_cancel_flow',
        ]);

        $company = Company::factory()->create([
            'stripe_customer_id' => 'cus_cancel_123',
            'current_plan_id' => $plan->id,
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_customer_id' => 'cus_cancel_123',
            'stripe_subscription_id' => 'sub_cancel_123',
            'stripe_price_id' => 'price_cancel_flow',
        ]);

        $periodEnd = now()->addDays(10);

        $event = Event::constructFrom([
            'id' => 'evt_cancel_scheduled',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'object' => 'subscription',
                    'id' => 'sub_cancel_123',
                    'customer' => 'cus_cancel_123',
                    'status' => 'active',
                    'cancel_at_period_end' => true,
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => $periodEnd->timestamp,
                    'metadata' => [
                        'company_id' => $company->id,
                        'plan_id' => $plan->id,
                    ],
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_cancel_flow',
                                'price' => [
                                    'id' => 'price_cancel_flow',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->app->make(StripeBillingService::class)->processEvent($event);

        $company->refresh();

        $subscription = $company->subscription->fresh();

        $this->assertTrue($subscription->cancel_at_period_end);
        $this->assertSame(
            $subscription->current_period_end?->toDateTimeString(),
            $company->access_expires_at?->toDateTimeString()
        );
        $this->assertSame('active', $company->subscription_status);
    }

    public function test_customer_subscription_updated_clears_scheduled_cancellation_when_reactivated(): void
    {
        $company = Company::factory()->create([
            'stripe_customer_id' => 'cus_reactivate_123',
            'access_expires_at' => now()->addDays(4),
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => \App\Services\CompanySubscriptionService::BLOCK_REASON_SUBSCRIPTION_EXPIRED,
            'subscription_status' => 'canceled',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => Plan::create([
                'slug' => 'reactive-' . Str::lower(Str::random(6)),
                'name' => 'Reactive',
                'description' => 'Plano reativado.',
                'price_cents' => 1000,
                'currency' => 'BRL',
                'billing_interval' => 'month',
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [],
                'quotas' => [],
                'stripe_price_id' => 'price_reactivate',
            ])->id,
            'status' => 'canceled',
            'stripe_customer_id' => 'cus_reactivate_123',
            'stripe_subscription_id' => 'sub_reactivate_123',
            'cancel_at_period_end' => true,
            'current_period_end' => now()->addDays(4),
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_reactivate',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'object' => 'subscription',
                    'id' => 'sub_reactivate_123',
                    'customer' => 'cus_reactivate_123',
                    'status' => 'active',
                    'cancel_at_period_end' => false,
                    'current_period_start' => now()->timestamp,
                    'current_period_end' => now()->addMonth()->timestamp,
                    'metadata' => [
                        'company_id' => $company->id,
                    ],
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_reactivate_123',
                                'price' => [
                                    'id' => 'price_reactivate',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->app->make(StripeBillingService::class)->processEvent($event);

        $company->refresh();

        $this->assertNull($company->access_expires_at);
        $this->assertFalse($company->is_blocked);
        $this->assertNull($company->blocked_reason);
        $this->assertSame('active', $company->subscription_status);
    }

    public function test_checkout_session_completed_marks_extra_employee_charge_as_paid(): void
    {
        $company = Company::factory()->create([
            'stripe_customer_id' => 'cus_test_123',
            'paid_extra_employee_allowance' => 1,
        ]);

        $plan = Plan::create([
            'slug' => 'extra-pay-' . Str::lower(Str::random(6)),
            'name' => 'Extra pay',
            'description' => 'Plano extra',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 10,
            'features' => [],
            'quotas' => ['max_employees' => 15],
            'extra_employee_price_cents' => 500,
            'stripe_extra_employee_price_id' => 'price_extra_br',
        ]);

        $charge = ExtraEmployeeCharge::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'quantity' => 2,
            'status' => 'pending',
            'due_at' => now()->addDays(7),
        ]);

        $event = Event::constructFrom([
            'id' => 'evt_checkout_extra_paid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'object' => 'checkout.session',
                    'id' => 'cs_extra_paid',
                    'customer' => 'cus_test_123',
                    'payment_status' => 'paid',
                    'metadata' => [
                        'billing_component' => 'extra_employee_pending',
                        'company_id' => $company->id,
                        'plan_id' => $plan->id,
                        'extra_employee_charge_id' => $charge->id,
                        'quantity' => '2',
                    ],
                ],
            ],
        ]);

        $this->app->make(StripeBillingService::class)->processEvent($event);

        $charge->refresh();
        $company->refresh();

        $this->assertSame('paid', $charge->status);
        $this->assertNotNull($charge->paid_at);
        $this->assertSame('cs_extra_paid', $charge->stripe_checkout_session_id);
        $this->assertSame(3, $company->paid_extra_employee_allowance);
    }
}
