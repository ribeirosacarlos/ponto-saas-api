<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\Company;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AreaVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->seedRoles();
    }

    public function test_admin_sees_all_company_employees(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $admin = $this->createUser($company, 'admin');
        $employeeA = $this->createUser($company, 'employee', $areaA);
        $employeeB = $this->createUser($company, 'employee', $areaB);
        $otherCompanyEmployee = $this->createUser(Company::factory()->create(), 'employee');

        $response = $this->actingAs($admin)->getJson('/v1/admin/employees');

        $response->assertOk();
        $response->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing(
            [$admin->id, $employeeA->id, $employeeB->id],
            collect($response->json('data'))->pluck('id')->all()
        );
        $this->assertNotContains($otherCompanyEmployee->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_manager_sees_only_employees_from_managed_areas(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $visibleEmployee = $this->createUser($company, 'employee', $areaA);
        $hiddenEmployee = $this->createUser($company, 'employee', $areaB);

        $this->assignManagedAreas($manager, [$areaA]);

        $response = $this->actingAs($manager)->getJson('/v1/admin/employees');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $this->assertSame([$visibleEmployee->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($hiddenEmployee->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_area_manager_sees_only_employees_from_managed_areas(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $areaManager = $this->createUser($company, 'area_manager');
        $visibleEmployee = $this->createUser($company, 'employee', $areaB);
        $hiddenEmployee = $this->createUser($company, 'employee', $areaA);

        $this->assignManagedAreas($areaManager, [$areaB]);

        $response = $this->actingAs($areaManager)->getJson('/v1/admin/employees');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $this->assertSame([$visibleEmployee->id], collect($response->json('data'))->pluck('id')->all());
        $this->assertNotContains($hiddenEmployee->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_manager_cannot_view_employee_from_unmanaged_area(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $employee = $this->createUser($company, 'employee', $areaB);

        $this->assignManagedAreas($manager, [$areaA]);

        $this->actingAs($manager)
            ->getJson("/v1/admin/employees/{$employee->id}")
            ->assertForbidden();
    }

    public function test_area_manager_cannot_update_employee_from_unmanaged_area(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $areaManager = $this->createUser($company, 'area_manager');
        $employee = $this->createUser($company, 'employee', $areaA);

        $this->assignManagedAreas($areaManager, [$areaB]);

        $this->actingAs($areaManager)
            ->putJson("/v1/admin/employees/{$employee->id}", [
                'name' => 'Nome alterado',
            ])
            ->assertForbidden();
    }

    public function test_manager_cannot_approve_adjustment_from_unmanaged_area(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $employee = $this->createUser($company, 'employee', $areaB);

        $this->assignManagedAreas($manager, [$areaA]);

        $timeEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => now(),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
        ]);

        $this->actingAs($manager)
            ->postJson("/v1/admin/time-entries/{$timeEntry->id}/adjustment/approve", [])
            ->assertForbidden();
    }

    public function test_manager_report_returns_only_entries_from_managed_areas(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $visibleEmployee = $this->createUser($company, 'employee', $areaA);
        $hiddenEmployee = $this->createUser($company, 'employee', $areaB);
        $otherCompany = Company::factory()->create();
        $otherCompanyArea = Area::factory()->create(['company_id' => $otherCompany->id]);
        $otherCompanyEmployee = $this->createUser($otherCompany, 'employee', $otherCompanyArea);

        $this->assignManagedAreas($manager, [$areaA]);

        $visibleEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $visibleEmployee->id,
            'clocked_at' => '2026-04-10 12:00:00',
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $hiddenEmployee->id,
            'clocked_at' => '2026-04-10 13:00:00',
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherCompanyEmployee->id,
            'clocked_at' => '2026-04-10 14:00:00',
            'type' => 'in',
            'source' => 'web',
        ]);

        $response = $this->actingAs($manager)
            ->getJson('/v1/admin/reports/time?start=2026-04-10%2000:00:00&end=2026-04-10%2023:59:59');

        $response->assertOk();
        $this->assertSame([$visibleEntry->id], collect($response->json())->pluck('id')->all());
    }

    public function test_manager_cannot_view_employee_from_other_company(): void
    {
        [$company, $areaA] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $this->assignManagedAreas($manager, [$areaA]);

        $otherCompany = Company::factory()->create();
        $otherArea = Area::factory()->create(['company_id' => $otherCompany->id]);
        $otherEmployee = $this->createUser($otherCompany, 'employee', $otherArea);

        $this->actingAs($manager)
            ->getJson("/v1/admin/employees/{$otherEmployee->id}")
            ->assertNotFound();
    }

    private function createCompanyWithAreas(): array
    {
        $company = Company::factory()->create();
        $areaA = Area::factory()->create([
            'company_id' => $company->id,
            'name' => 'Financeiro',
        ]);
        $areaB = Area::factory()->create([
            'company_id' => $company->id,
            'name' => 'Operacoes',
        ]);

        return [$company, $areaA, $areaB];
    }

    private function createUser(Company $company, string $role, ?Area $area = null): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'area_id' => $area?->id,
        ]);
        $user->syncRoles([$role]);

        return $user->fresh('roles', 'managedAreas');
    }

    private function assignManagedAreas(User $user, array $areas): void
    {
        $payload = collect($areas)
            ->mapWithKeys(fn (Area $area) => [$area->id => [
                'id' => (string) Str::uuid(),
                'company_id' => $user->company_id,
            ]])
            ->all();

        $user->managedAreas()->sync($payload);
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'manager', 'area_manager', 'employee'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst(str_replace('_', ' ', $roleName))]
            );
        }
    }
}
