<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\ExtraEmployeeCharge;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Tests\TestCase;

class ExtraEmployeeCheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_admin_can_create_checkout_session_for_pending_extra_employee_charge(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'extra-employee-test',
            'name' => 'Plano teste',
            'description' => 'Plano com extra employee',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 1],
            'extra_employee_price_cents' => 500,
            'stripe_price_id' => 'price_base',
            'stripe_extra_employee_price_id' => 'price_extra',
        ]);

        $admin->company()->update(['current_plan_id' => $plan->id]);

        $charge = ExtraEmployeeCharge::create([
            'company_id' => $admin->company_id,
            'plan_id' => $plan->id,
            'quantity' => 2,
            'status' => 'pending',
            'due_at' => now()->addDays(7),
        ]);

        $session = StripeCheckoutSession::constructFrom([
            'id' => 'cs_extra_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_extra_123',
        ]);

        $service = Mockery::mock(StripeBillingService::class);
        $service->shouldReceive('createExtraEmployeeCheckoutSession')
            ->once()
            ->andReturn($session);
        $this->app->instance(StripeBillingService::class, $service);

        $response = $this->actingAs($admin)->postJson('/v1/admin/billing/extra-employees/checkout-session');

        $response->assertOk()
            ->assertJson(['url' => 'https://checkout.stripe.com/c/pay/cs_extra_123']);

        $this->assertDatabaseHas('extra_employee_charges', [
            'id' => $charge->id,
            'stripe_checkout_session_id' => 'cs_extra_123',
        ]);
    }
}
