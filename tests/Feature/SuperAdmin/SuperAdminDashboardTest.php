<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
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

        config(['billing.default_plan_slug' => 'starter_monthly']);
        (new PlansSeeder)->run();
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

        AuditLog::create([
            'company_id' => null,
            'target_company_id' => $activeCompany->id,
            'user_id' => $superAdmin->id,
            'performed_by_role' => 'super_admin',
            'action' => 'platform.company_unblocked',
            'entity_type' => Company::class,
            'entity_id' => $activeCompany->id,
            'description' => 'Empresa desbloqueada por super admin.',
            'created_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $response = $this->getJson('/v1/platform/super-admin/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'time_entries_series' => [
                        'granularity',
                        'windows' => [
                            '30d' => ['days', 'from', 'to', 'points'],
                            '60d' => ['days', 'from', 'to', 'points'],
                            '90d' => ['days', 'from', 'to', 'points'],
                        ],
                    ],
                    'active_companies_series',
                    'subscription_status_breakdown',
                    'plan_breakdown',
                    'recent_events',
                    'top_companies_by_activity',
                    'top_companies_by_risk',
                ],
            ])
            ->assertJsonPath('data.companies.total', 2)
            ->assertJsonPath('data.companies.paying', 1)
            ->assertJsonPath('data.companies.trialing', 1)
            ->assertJsonPath('data.employees.total', 2)
            ->assertJsonPath('data.time_entries.today', 1)
            ->assertJsonPath('data.time_entries.last_30_days', 3)
            ->assertJsonPath('data.time_entries_series.granularity', 'day')
            ->assertJsonPath('data.time_entries_series.windows.30d.days', 30)
            ->assertJsonCount(30, 'data.time_entries_series.windows.30d.points')
            ->assertJsonPath('data.active_companies_series.windows.60d.days', 60)
            ->assertJsonCount(90, 'data.active_companies_series.windows.90d.points')
            ->assertJsonPath('data.subscription_status_breakdown.0.status', 'trialing')
            ->assertJsonPath('data.subscription_status_breakdown.0.total', 1)
            ->assertJsonPath('data.subscription_status_breakdown.1.status', 'active')
            ->assertJsonPath('data.subscription_status_breakdown.1.total', 1)
            ->assertJsonFragment(['plan_slug' => 'starter_monthly'])
            ->assertJsonFragment(['plan_slug' => 'business_yearly'])
            ->assertJsonPath('data.top_companies_by_activity.0.company.id', $activeCompany->id)
            ->assertJsonPath('data.top_companies_by_activity.0.time_entries_30d', 2)
            ->assertJsonFragment(['type' => 'company_unblocked']);
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

        $response = $this->getJson('/v1/platform/super-admin/companies');

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
        $monthlyPlan = Plan::where('slug', 'starter_monthly')->firstOrFail();
        $yearlyPlan = Plan::where('slug', 'business_yearly')->firstOrFail();

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
