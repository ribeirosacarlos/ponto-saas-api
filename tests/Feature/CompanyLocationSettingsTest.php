<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyLocationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        Role::updateOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);
    }

    public function test_admin_can_view_company_location_settings(): void
    {
        $user = $this->createUserWithRole('admin');
        $user->company()->update([
            'company_latitude' => '-23.5505200',
            'company_longitude' => '-46.6333080',
            'allowed_radius_meters' => 150,
            'location_validation_enabled' => true,
        ]);
        $user = $user->fresh();

        $response = $this->actingAs($user)->getJson('/v1/settings/location');

        $response->assertOk()
            ->assertJsonPath('data.company_latitude', '-23.5505200')
            ->assertJsonPath('data.company_longitude', '-46.6333080')
            ->assertJsonPath('data.allowed_radius_meters', 150)
            ->assertJsonPath('data.location_validation_enabled', true)
            ->assertJsonPath('data.is_configured', true);
    }

    public function test_admin_can_update_company_location_settings(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->putJson('/v1/settings/location', [
            'company_latitude' => -23.55052,
            'company_longitude' => -46.633308,
            'allowed_radius_meters' => 100,
            'location_validation_enabled' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company_latitude', '-23.5505200')
            ->assertJsonPath('data.company_longitude', '-46.6333080')
            ->assertJsonPath('data.allowed_radius_meters', 100)
            ->assertJsonPath('data.location_validation_enabled', true)
            ->assertJsonPath('data.is_configured', true);

        $this->assertDatabaseHas('companies', [
            'id' => $user->company_id,
            'company_latitude' => '-23.5505200',
            'company_longitude' => '-46.6333080',
            'allowed_radius_meters' => 100,
            'location_validation_enabled' => true,
        ]);
    }

    public function test_employee_cannot_access_company_location_settings(): void
    {
        $user = $this->createUserWithRole('employee');

        $this->actingAs($user)->getJson('/v1/settings/location')
            ->assertForbidden();
    }

    public function test_update_validates_payload(): void
    {
        $user = $this->createUserWithRole('admin');

        $this->actingAs($user)->putJson('/v1/settings/location', [
            'company_latitude' => 100,
            'company_longitude' => -200,
            'allowed_radius_meters' => 5,
            'location_validation_enabled' => 'invalid',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'company_latitude',
                'company_longitude',
                'allowed_radius_meters',
                'location_validation_enabled',
            ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh('company', 'roles');
    }
}
