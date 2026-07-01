<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_admin_can_create_holiday(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->postJson('/v1/admin/holidays', [
            'date' => '2026-12-25',
            'name' => 'Natal',
            'scope' => 'national',
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Natal');

        $this->assertDatabaseHas('holidays', [
            'company_id' => $company->id,
            'name' => 'Natal',
        ]);
    }

    public function test_admin_can_update_holiday(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $holiday = Holiday::create([
            'company_id' => $company->id,
            'date' => '2026-12-25',
            'name' => 'Natal',
            'scope' => 'national',
        ]);

        $response = $this->actingAs($admin)->putJson("/v1/admin/holidays/{$holiday->id}", [
            'date' => '2026-12-25',
            'name' => 'Natal - Feriado Nacional',
            'scope' => 'national',
        ]);

        $response->assertOk()
            ->assertJsonPath('name', 'Natal - Feriado Nacional');
    }

    public function test_admin_can_delete_holiday(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $holiday = Holiday::create([
            'company_id' => $company->id,
            'date' => '2026-12-25',
            'name' => 'Natal',
            'scope' => 'national',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/holidays/{$holiday->id}")
            ->assertOk();

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_admin_cannot_delete_holiday_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherCompany = Company::factory()->create();
        $holiday = Holiday::create([
            'company_id' => $otherCompany->id,
            'date' => '2026-12-25',
            'name' => 'Natal',
            'scope' => 'national',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/holidays/{$holiday->id}")
            ->assertForbidden();
    }

    public function test_employees_can_list_company_holidays(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        Holiday::create([
            'company_id' => $company->id,
            'date' => '2026-09-07',
            'name' => 'Independência',
            'scope' => 'national',
        ]);

        $response = $this->actingAs($employee)->getJson('/v1/holidays');

        $response->assertOk()
            ->assertJsonStructure(['data']);
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
