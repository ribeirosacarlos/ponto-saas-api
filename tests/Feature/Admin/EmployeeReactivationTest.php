<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeReactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_reactivate_a_deactivated_employee(): void
    {
        $company = $this->activeCompany();

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('manager');

        // Simula o que EmployeeController::destroy() grava ao desativar,
        // sem precisar de uma segunda chamada HTTP nesta mesma execução de teste.
        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'action' => 'employee.deleted',
            'entity_type' => User::class,
            'entity_id' => $employee->id,
            'description' => 'Colaborador removido.',
            'old_values' => ['role' => 'manager'],
            'created_at' => now(),
        ]);

        $employee->delete();

        $this->assertSoftDeleted('users', ['id' => $employee->id]);

        $response = $this->actingAs($admin)->postJson("/v1/admin/employees/{$employee->id}/restore");

        $response->assertStatus(200);
        $response->assertJsonPath('id', (string) $employee->id);
        $response->assertJsonPath('deleted_at', null);
        $response->assertJsonPath('role.name', 'manager');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'deleted_at' => null,
        ]);

        $this->assertTrue($employee->fresh()->hasRole('manager'));

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => User::class,
            'entity_id' => $employee->id,
            'action' => 'employee.restored',
        ]);
    }

    public function test_reactivating_an_active_employee_returns_unprocessable(): void
    {
        $company = $this->activeCompany();

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $response = $this->actingAs($admin)->postJson("/v1/admin/employees/{$employee->id}/restore");

        $response->assertStatus(422);
    }

    public function test_admin_cannot_reactivate_employee_from_another_company(): void
    {
        $companyA = $this->activeCompany();
        $companyB = $this->activeCompany();

        $admin = User::factory()->create(['company_id' => $companyA->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $companyB->id]);
        $employee->assignRole('employee');
        $employee->delete();

        $response = $this->actingAs($admin)->postJson("/v1/admin/employees/{$employee->id}/restore");

        $response->assertStatus(404);
    }

    public function test_manager_cannot_reactivate_employee(): void
    {
        $company = $this->activeCompany();

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->assignRole('manager');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');
        $employee->delete();

        $response = $this->actingAs($manager)->postJson("/v1/admin/employees/{$employee->id}/restore");

        $response->assertStatus(403);
    }

    public function test_default_listing_excludes_deactivated_employees(): void
    {
        $company = $this->activeCompany();

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $activeEmployee = User::factory()->create(['company_id' => $company->id]);
        $activeEmployee->assignRole('employee');

        $inactiveEmployee = User::factory()->create(['company_id' => $company->id]);
        $inactiveEmployee->assignRole('employee');
        $inactiveEmployee->delete();

        $response = $this->actingAs($admin)->getJson('/v1/admin/employees');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $activeEmployee->id));
        $this->assertFalse($ids->contains((string) $inactiveEmployee->id));
    }

    public function test_status_inactive_filter_lists_only_deactivated_employees(): void
    {
        $company = $this->activeCompany();

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $activeEmployee = User::factory()->create(['company_id' => $company->id]);
        $activeEmployee->assignRole('employee');

        $inactiveEmployee = User::factory()->create(['company_id' => $company->id]);
        $inactiveEmployee->assignRole('employee');
        $inactiveEmployee->delete();

        $response = $this->actingAs($admin)->getJson('/v1/admin/employees?status=inactive');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains((string) $inactiveEmployee->id));
        $this->assertFalse($ids->contains((string) $activeEmployee->id));
    }

    private function activeCompany(): Company
    {
        return Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    private function seedRoles(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::firstOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }
}
