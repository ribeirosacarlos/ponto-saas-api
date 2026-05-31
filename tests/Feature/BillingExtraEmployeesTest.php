<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CompanySubscriptionBillingService;
use App\Services\StripeBillingService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingExtraEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_plans_seeder_creates_starter_plan_with_extra_employee_pricing(): void
    {
        (new PlansSeeder())->run();

        $plan = Plan::where('slug', 'starter_monthly')->first();

        $this->assertNotNull($plan);
        $this->assertSame('EUR', $plan->currency);
        $this->assertSame(1900, $plan->price_cents);
        $this->assertSame(250, $plan->extra_employee_price_cents);
        $this->assertSame(5, data_get($plan->quotas, 'max_employees'));
        $this->assertSame('starter', $plan->code);
        $this->assertSame(5, $plan->included_employees);
    }

    public function test_company_subscription_billing_service_calculates_and_syncs_extra_employees(): void
    {
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);

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
            $employees = User::factory()->count(16)->create(['company_id' => $company->id]);

            foreach ($employees as $employee) {
                $employee->assignRole('employee');
            }

            $hybridUser = User::factory()->create(['company_id' => $company->id]);
            $hybridUser->assignRole('employee');
            $hybridUser->assignRole('admin');

            $adminOnly = User::factory()->create(['company_id' => $company->id]);
            $adminOnly->assignRole('admin');
            $adminOnly->timeEntries()->create([
                'company_id' => $company->id,
                'clocked_at' => now(),
                'type' => 'in',
                'source' => 'web',
            ]);
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

        $this->assertSame(18, $summary['active_employees']);
        $this->assertSame(3, $summary['extra_employees']);
        $this->assertSame(16500, $summary['total_price_cents']);
    }

    public function test_user_observer_does_not_trigger_company_billing_sync_automatically(): void
    {
        $company = Company::factory()->create();

        $service = Mockery::mock(CompanySubscriptionBillingService::class);
        $service->shouldNotReceive('syncRecurringUsage');
        $service->shouldNotReceive('syncExtraEmployeesAfterAdminConfirmation');
        $this->app->instance(CompanySubscriptionBillingService::class, $service);

        $user = User::factory()->create(['company_id' => $company->id]);
        $user->delete();

        $this->addToAssertionCount(1);
    }

    public function test_admin_can_trigger_extra_employee_sync_after_confirmation(): void
    {
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        $company = Company::factory()->create([
            'subscription_status' => 'active',
        ]);

        $plan = $this->makePlan([
            'currency' => 'BRL',
            'price_cents' => 12000,
            'quotas' => ['max_employees' => 15],
            'extra_employee_price_cents' => 1500,
            'stripe_price_id' => 'price_1TEdOEHo1sUcOy0oZN37fpdT',
            'stripe_extra_employee_price_id' => 'price_1TEdaYHo1sUcOy0oZB6iychw',
        ]);

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'stripe_subscription_id' => 'sub_123',
            'stripe_customer_id' => 'cus_123',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');
        Sanctum::actingAs($admin, ['*']);

        $service = Mockery::mock(CompanySubscriptionBillingService::class);
        $service->shouldReceive('syncExtraEmployeesAfterAdminConfirmation')
            ->once()
            ->andReturn([
                'active_employees' => 18,
                'included_employees' => 15,
                'extra_employees' => 3,
                'stripe_price_id' => 'price_1TEdOEHo1sUcOy0oZN37fpdT',
                'stripe_extra_employee_price_id' => 'price_1TEdaYHo1sUcOy0oZB6iychw',
            ]);
        $this->app->instance(CompanySubscriptionBillingService::class, $service);

        $response = $this->postJson('/v1/admin/billing/extra-employees/sync');

        $response->assertOk()
            ->assertJson([
                'message' => 'Colaboradores extras sincronizados para a próxima cobrança.',
                'summary' => [
                    'active_employees' => 18,
                    'included_employees' => 15,
                    'extra_employees' => 3,
                    'stripe_price_id' => 'price_1TEdOEHo1sUcOy0oZN37fpdT',
                    'stripe_extra_employee_price_id' => 'price_1TEdaYHo1sUcOy0oZB6iychw',
                ],
            ]);
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
