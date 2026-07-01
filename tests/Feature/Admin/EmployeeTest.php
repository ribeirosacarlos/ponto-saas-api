<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_admin_can_list_employees(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $otherEmployee = User::factory()->create();

        $response = $this->actingAs($admin)->getJson('/v1/admin/employees');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains((string) $employee->id, $ids);
        $this->assertNotContains((string) $otherEmployee->id, $ids);
    }

    public function test_admin_can_show_employee(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');

        $response = $this->actingAs($admin)->getJson("/v1/admin/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonPath('id', (string) $employee->id);
    }

    public function test_admin_cannot_show_employee_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherEmployee = User::factory()->create();

        $this->actingAs($admin)
            ->getJson("/v1/admin/employees/{$otherEmployee->id}")
            ->assertNotFound();
    }

    public function test_admin_can_create_employee(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->postJson('/v1/admin/employees', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'role' => 'employee',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'João Silva')
            ->assertJsonPath('email', 'joao@example.com');

        $this->assertDatabaseHas('users', [
            'company_id' => $company->id,
            'email' => 'joao@example.com',
        ]);
    }

    public function test_admin_can_update_employee_name(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');

        $response = $this->actingAs($admin)->putJson("/v1/admin/employees/{$employee->id}", [
            'name' => 'Nome Atualizado',
            'email' => $employee->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Nome Atualizado');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Nome Atualizado',
        ]);
    }

    public function test_admin_can_delete_employee(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/employees/{$employee->id}")
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $employee->id]);
    }

    public function test_admin_cannot_delete_employee_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherEmployee = User::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/employees/{$otherEmployee->id}")
            ->assertNotFound();
    }

    public function test_employee_cannot_access_admin_employee_list(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $this->actingAs($employee)
            ->getJson('/v1/admin/employees')
            ->assertForbidden();
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->syncRoles([$role]);

        return $user;
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
