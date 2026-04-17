<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Area;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_admin_can_list_company_areas(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $areaA = Area::factory()->create(['company_id' => $company->id, 'name' => 'Financeiro']);
        $areaB = Area::factory()->create(['company_id' => $company->id, 'name' => 'Operacoes']);
        Area::factory()->create();

        $response = $this->actingAs($admin)->getJson('/v1/admin/areas?page=1');

        $response->assertOk();
        $response->assertJsonPath('total', 2);
        $this->assertEqualsCanonicalizing([$areaA->id, $areaB->id], collect($response->json('data'))->pluck('id')->all());
    }

    public function test_manager_can_list_company_areas(): void
    {
        $company = Company::factory()->create();
        $manager = $this->createUser($company, 'manager');
        $area = Area::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($manager)->getJson('/v1/admin/areas');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $area->id);
    }

    public function test_admin_can_create_area(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->postJson('/v1/admin/areas', [
            'name' => 'Recursos Humanos',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Recursos Humanos')
            ->assertJsonPath('company_id', $company->id);

        $this->assertDatabaseHas('areas', [
            'company_id' => $company->id,
            'name' => 'Recursos Humanos',
        ]);
    }

    public function test_manager_cannot_create_area(): void
    {
        $company = Company::factory()->create();
        $manager = $this->createUser($company, 'manager');

        $this->actingAs($manager)
            ->postJson('/v1/admin/areas', [
                'name' => 'Juridico',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_update_area(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $area = Area::factory()->create(['company_id' => $company->id, 'name' => 'Comercial']);

        $this->actingAs($admin)
            ->putJson("/v1/admin/areas/{$area->id}", [
                'name' => 'Comercial B2B',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Comercial B2B');
    }

    public function test_admin_cannot_delete_area_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherArea = Area::factory()->create();

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/areas/{$otherArea->id}")
            ->assertForbidden();
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);
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
