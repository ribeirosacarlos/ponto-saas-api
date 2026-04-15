<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\ExtraEmployeeCharge;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySettingsOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_usage_counts_only_users_with_employee_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'plan-usage-test',
            'name' => 'Plano uso',
            'description' => 'Plano para testar usage.',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 2],
            'extra_employee_price_cents' => 0,
        ]);

        $admin->company()->update(['current_plan_id' => $plan->id]);

        $employeeOnly = User::factory()->create(['company_id' => $admin->company_id]);
        $employeeOnly->assignRole('employee');

        $hybridUser = User::factory()->create(['company_id' => $admin->company_id]);
        $hybridUser->assignRole('employee');
        $hybridUser->assignRole('admin');

        $adminOnly = User::factory()->create(['company_id' => $admin->company_id]);
        $adminOnly->assignRole('admin');

        $response = $this->actingAs($admin)->getJson('/v1/settings/overview');

        $response->assertOk()
            ->assertJsonPath('data.usage.employees.current', 2)
            ->assertJsonPath('data.usage.employees.limit', 2)
            ->assertJsonPath('data.usage.employees.over_limit', false)
            ->assertJsonPath('data.usage.extra_employees.paid_allowance', 0)
            ->assertJsonPath('data.usage.extra_employees.has_pending_payment', false);
    }

    public function test_usage_exposes_pending_extra_employee_payment_state(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'plan-usage-extra-test',
            'name' => 'Plano uso extra',
            'description' => 'Plano para testar extra employee pendente.',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 2],
            'extra_employee_price_cents' => 500,
            'stripe_extra_employee_price_id' => 'price_extra',
        ]);

        $admin->company()->update([
            'current_plan_id' => $plan->id,
            'paid_extra_employee_allowance' => 1,
        ]);

        ExtraEmployeeCharge::create([
            'company_id' => $admin->company_id,
            'plan_id' => $plan->id,
            'quantity' => 2,
            'status' => 'pending',
            'due_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($admin)->getJson('/v1/settings/overview');

        $response->assertOk()
            ->assertJsonPath('data.usage.extra_employees.paid_allowance', 1)
            ->assertJsonPath('data.usage.extra_employees.has_pending_payment', true)
            ->assertJsonPath('data.usage.extra_employees.pending_quantity', 2)
            ->assertJsonPath('data.usage.extra_employees.payment_overdue', false);
    }
}
