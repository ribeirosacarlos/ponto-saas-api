<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CompanySubscriptionBillingService;
use App\Services\StripeBillingService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class BillingExtraEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_plans_seeder_creates_pro_br_plan_with_extra_employee_pricing(): void
    {
        (new PlansSeeder())->run();

        $plan = Plan::where('slug', 'pro_br_monthly')->first();

        $this->assertNotNull($plan);
        $this->assertSame('BRL', $plan->currency);
        $this->assertSame(12000, $plan->price_cents);
        $this->assertSame(1500, $plan->extra_employee_price_cents);
        $this->assertSame(15, data_get($plan->quotas, 'max_employees'));
    }

    public function test_company_subscription_billing_service_calculates_and_syncs_extra_employees(): void
    {
        $company = Company::factory()->create();
        $plan = $this->makePlan([
            'currency' => 'BRL',
            'price_cents' => 12000,
            'quotas' => ['max_employees' => 15],
            'extra_employee_price_cents' => 1500,
            'stripe_price_id' => 'price_base_br',
            'stripe_extra_employee_price_id' => 'price_extra_br',
        ]);

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'stripe_customer_id' => 'cus_123',
        ]);

        User::withoutEvents(function () use ($company): void {
            User::factory()->count(18)->create(['company_id' => $company->id]);
        });

        $stripeService = Mockery::mock(StripeBillingService::class);
        $stripeService->shouldReceive('syncExtraEmployeeSubscriptionItem')
            ->once()
            ->withArgs(function (Company $argCompany, Subscription $argSubscription, Plan $argPlan, array $summary) use ($company, $subscription, $plan) {
                return $argCompany->is($company)
                    && $argSubscription->is($subscription)
                    && $argPlan->is($plan)
                    && $summary['currency'] === 'BRL'
                    && $summary['base_price_cents'] === 12000
                    && $summary['included_employees'] === 15
                    && $summary['active_employees'] === 18
                    && $summary['extra_employees'] === 3
                    && $summary['extra_employee_price_cents'] === 1500
                    && $summary['extra_total_cents'] === 4500
                    && $summary['total_price_cents'] === 16500;
            })
            ->andReturnUsing(fn ($argCompany, $argSubscription, $argPlan, array $summary) => $summary);

        $this->app->instance(StripeBillingService::class, $stripeService);

        $summary = $this->app->make(CompanySubscriptionBillingService::class)
            ->syncRecurringUsage($company->fresh(['subscription.plan', 'currentPlan']));

        $this->assertSame(3, $summary['extra_employees']);
        $this->assertSame(16500, $summary['total_price_cents']);
    }

    public function test_user_observer_triggers_company_billing_sync_when_user_is_created_or_deleted(): void
    {
        $company = Company::factory()->create();

        $service = Mockery::mock(CompanySubscriptionBillingService::class);
        $service->shouldReceive('syncRecurringUsageForCompanyId')->with($company->id)->twice();
        $this->app->instance(CompanySubscriptionBillingService::class, $service);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->delete();

        $this->addToAssertionCount(1);
    }

    protected function makePlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'slug' => 'plan-' . Str::lower(Str::random(8)),
            'name' => 'Plano teste',
            'description' => 'Plano utilizado em testes.',
            'price_cents' => 1800,
            'currency' => 'EUR',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 100,
            'features' => [],
            'quotas' => ['max_employees' => 20],
            'extra_employee_price_cents' => 0,
        ], $overrides));
    }
}
