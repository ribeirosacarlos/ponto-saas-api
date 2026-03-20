<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.default_plan_slug' => 'basic_monthly']);
        (new PlansSeeder())->run();
    }

    public function test_super_admin_can_view_dashboard_summary(): void
    {
        Carbon::setTestNow('2026-03-20 10:00:00');

        $superAdmin = $this->createSuperAdmin();
        [$activeCompany, $trialCompany] = $this->createCompaniesForMetrics();

        TimeEntry::create([
            'id' => (string) Str::uuid(),
            'company_id' => $activeCompany->id,
            'user_id' => $activeCompany->users()->first()->id,
            'clocked_at' => now()->subDays(1),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'id' => (string) Str::uuid(),
            'company_id' => $activeCompany->id,
            'user_id' => $activeCompany->users()->first()->id,
            'clocked_at' => now()->subHours(2),
            'type' => 'out',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'id' => (string) Str::uuid(),
            'company_id' => $trialCompany->id,
            'user_id' => $trialCompany->users()->first()->id,
            'clocked_at' => now()->subDays(10),
            'type' => 'in',
            'source' => 'web',
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->getJson('/api/v1/platform/super-admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.companies.total', 2)
            ->assertJsonPath('data.companies.paying', 1)
            ->assertJsonPath('data.companies.trialing', 1)
            ->assertJsonPath('data.employees.total', 2)
            ->assertJsonPath('data.time_entries.today', 0)
            ->assertJsonPath('data.time_entries.last_30_days', 3);
    }

    public function test_super_admin_can_list_companies_with_metrics(): void
    {
        Carbon::setTestNow('2026-03-20 10:00:00');

        $superAdmin = $this->createSuperAdmin();
        [$activeCompany] = $this->createCompaniesForMetrics();
        $employee = $activeCompany->users()->first();

        TimeEntry::create([
            'id' => (string) Str::uuid(),
            'company_id' => $activeCompany->id,
            'user_id' => $employee->id,
            'clocked_at' => now()->subHours(3),
            'type' => 'in',
            'source' => 'web',
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->getJson('/api/v1/platform/super-admin/companies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'subscription_status',
                    'employees_count',
                    'active_employees_30d',
                    'time_entries_30d',
                    'last_activity_at',
                    'health_status',
                ]],
                'links',
                'meta',
            ])
            ->assertJsonFragment([
                'id' => $activeCompany->id,
                'employees_count' => 1,
                'active_employees_30d' => 1,
            ]);
    }

    protected function createSuperAdmin(): User
    {
        Role::updateOrCreate(['name' => 'super_admin'], ['display_name' => 'Platform Administrator']);

        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles(['super_admin']);

        return $user;
    }

    protected function createCompaniesForMetrics(): array
    {
        $monthlyPlan = Plan::where('slug', 'basic_monthly')->firstOrFail();
        $yearlyPlan = Plan::where('slug', 'pro_yearly')->firstOrFail();

        $activeCompany = Company::factory()->create(['name' => 'Empresa Ativa']);
        $trialCompany = Company::factory()->create(['name' => 'Empresa Trial']);

        $activeCompany->subscription()->update([
            'plan_id' => $monthlyPlan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);
        $activeCompany->update([
            'current_plan_id' => $monthlyPlan->id,
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $trialCompany->subscription()->update([
            'plan_id' => $yearlyPlan->id,
            'status' => SubscriptionStatus::TRIALING->value,
        ]);
        $trialCompany->update([
            'current_plan_id' => $yearlyPlan->id,
            'subscription_status' => SubscriptionStatus::TRIALING->value,
        ]);

        User::factory()->create(['company_id' => $activeCompany->id, 'email' => 'active@example.com']);
        User::factory()->create(['company_id' => $trialCompany->id, 'email' => 'trial@example.com']);

        return [$activeCompany->fresh(), $trialCompany->fresh()];
    }
}
