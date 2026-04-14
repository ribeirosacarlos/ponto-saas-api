<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyHasAccess;
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
            ->assertJsonPath('data.usage.employees.over_limit', false);
    }
}
