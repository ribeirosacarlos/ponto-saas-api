<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_admin_can_get_company_info(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa Teste']);
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->getJson('/v1/admin/company/info');

        $response->assertOk()
            ->assertJsonPath('name', 'Empresa Teste')
            ->assertJsonStructure(['name', 'email', 'phone', 'address', 'city', 'state']);
    }

    public function test_admin_can_update_company_info(): void
    {
        $company = Company::factory()->create(['name' => 'Nome Antigo']);
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->patchJson('/v1/admin/company/info', [
            'name' => 'Nome Novo',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Nome Novo');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Nome Novo',
        ]);
    }

    public function test_manager_cannot_update_company_info(): void
    {
        $company = Company::factory()->create();
        $manager = $this->createUser($company, 'manager');

        $this->actingAs($manager)
            ->patchJson('/v1/admin/company/info', ['name' => 'Tentativa'])
            ->assertForbidden();
    }

    public function test_admin_can_get_company_locale(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->getJson('/v1/admin/company/locale');

        $response->assertOk();
    }

    public function test_admin_can_get_signature_settings(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->getJson('/v1/admin/company/signatures');

        $response->assertOk();
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
