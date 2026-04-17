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

    public function test_usage_counts_employees_and_non_employees_with_time_entries(): void
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
        $adminOnly->timeEntries()->create([
            'company_id' => $admin->company_id,
            'clocked_at' => now(),
            'type' => 'in',
            'source' => 'web',
        ]);

        $response = $this->actingAs($admin)->getJson('/v1/settings/overview');

        $response->assertOk()
            ->assertJsonPath('data.usage.employees.current', 3)
            ->assertJsonPath('data.usage.employees.limit', 2)
            ->assertJsonPath('data.usage.employees.over_limit', true)
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
            ->assertJsonPath('data.usage.extra_employees.has_pending_payment', false)
            ->assertJsonPath('data.usage.extra_employees.pending_quantity', 0)
            ->assertJsonPath('data.usage.extra_employees.payment_due_at', null)
            ->assertJsonPath('data.usage.extra_employees.payment_overdue', false);
    }

    public function test_usage_pending_quantity_reflects_current_overage_when_greater_than_stored_charge(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'plan-usage-outdated-extra-test',
            'name' => 'Plano uso extra defasado',
            'description' => 'Plano para testar overview com cobrança defasada.',
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
            'paid_extra_employee_allowance' => 0,
        ]);

        foreach (range(1, 5) as $index) {
            $user = User::factory()->create([
                'company_id' => $admin->company_id,
                'email' => "usage-outdated-{$index}@example.com",
            ]);
            $user->assignRole('employee');
        }

        ExtraEmployeeCharge::create([
            'company_id' => $admin->company_id,
            'plan_id' => $plan->id,
            'quantity' => 1,
            'status' => 'pending',
            'due_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($admin)->getJson('/v1/settings/overview');

        $response->assertOk()
            ->assertJsonPath('data.usage.employees.current', 5)
            ->assertJsonPath('data.usage.employees.limit', 2)
            ->assertJsonPath('data.usage.extra_employees.has_pending_payment', true)
            ->assertJsonPath('data.usage.extra_employees.pending_quantity', 3);
    }

    public function test_usage_pending_quantity_is_zero_when_company_is_within_plan_limit_even_with_pending_charge(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'plan-usage-within-limit-extra-test',
            'name' => 'Plano uso dentro do limite',
            'description' => 'Plano para testar overview sem excedente atual.',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 15],
            'extra_employee_price_cents' => 500,
            'stripe_extra_employee_price_id' => 'price_extra',
        ]);

        $admin->company()->update([
            'current_plan_id' => $plan->id,
            'paid_extra_employee_allowance' => 0,
        ]);

        foreach (range(1, 7) as $index) {
            $user = User::factory()->create([
                'company_id' => $admin->company_id,
                'email' => "usage-within-limit-{$index}@example.com",
            ]);
            $user->assignRole('employee');
        }

        ExtraEmployeeCharge::create([
            'company_id' => $admin->company_id,
            'plan_id' => $plan->id,
            'quantity' => 1,
            'status' => 'pending',
            'due_at' => now()->addDays(4),
        ]);

        $response = $this->actingAs($admin)->getJson('/v1/settings/overview');

        $response->assertOk()
            ->assertJsonPath('data.usage.employees.current', 7)
            ->assertJsonPath('data.usage.employees.limit', 15)
            ->assertJsonPath('data.usage.employees.over_limit', false)
            ->assertJsonPath('data.usage.extra_employees.has_pending_payment', false)
            ->assertJsonPath('data.usage.extra_employees.pending_quantity', 0);
    }
}
