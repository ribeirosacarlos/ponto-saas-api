<?php

namespace Tests\Feature\Employee;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_employee_can_update_own_name(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $response = $this->actingAs($employee)->patchJson('/v1/employee/profile', [
            'name' => 'Nome Novo',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Nome Novo');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_profile_update_requires_name(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $this->actingAs($employee)
            ->patchJson('/v1/employee/profile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_employee_can_update_own_password(): void
    {
        $company = Company::factory()->create();
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'password' => Hash::make('OldPass123'),
        ]);
        $employee->syncRoles(['employee']);

        $response = $this->actingAs($employee)->putJson('/v1/employee/password', [
            'current_password' => 'OldPass123',
            'password' => 'NewPass1234',
            'password_confirmation' => 'NewPass1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Senha atualizada com sucesso.');

        $this->assertTrue(Hash::check('NewPass1234', $employee->fresh()->password));
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $company = Company::factory()->create();
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'password' => Hash::make('CorrectPass123'),
        ]);
        $employee->syncRoles(['employee']);

        $this->actingAs($employee)
            ->putJson('/v1/employee/password', [
                'current_password' => 'WrongPass123',
                'password' => 'NewPass1234',
                'password_confirmation' => 'NewPass1234',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
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
