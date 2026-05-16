<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyDeviceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_admin_can_view_default_device_settings(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->getJson('/v1/admin/company/device-settings');

        $response->assertOk()->assertJson([
            'allow_mobile_clock'  => true,
            'allow_desktop_clock' => true,
        ]);
    }

    public function test_admin_can_restrict_desktop_clock(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->putJson('/v1/admin/company/device-settings', [
            'allow_mobile_clock'  => true,
            'allow_desktop_clock' => false,
        ]);

        $response->assertOk()->assertJson([
            'allow_mobile_clock'  => true,
            'allow_desktop_clock' => false,
        ]);

        $this->assertDatabaseHas('companies', [
            'id'                  => $admin->company_id,
            'allow_desktop_clock' => false,
        ]);
    }

    public function test_admin_can_restrict_mobile_clock(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->putJson('/v1/admin/company/device-settings', [
            'allow_mobile_clock'  => false,
            'allow_desktop_clock' => true,
        ]);

        $response->assertOk()->assertJson([
            'allow_mobile_clock'  => false,
            'allow_desktop_clock' => true,
        ]);

        $this->assertDatabaseHas('companies', [
            'id'                 => $admin->company_id,
            'allow_mobile_clock' => false,
        ]);
    }

    public function test_admin_cannot_disable_both_device_types(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->putJson('/v1/admin/company/device-settings', [
            'allow_mobile_clock'  => false,
            'allow_desktop_clock' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'É necessário permitir ao menos um tipo de dispositivo para registro de ponto.');
    }

    public function test_employee_cannot_access_device_settings(): void
    {
        $employee = $this->createEmployee();

        $this->actingAs($employee)->getJson('/v1/admin/company/device-settings')->assertForbidden();

        $putResponse = $this->actingAs($employee)->putJson('/v1/admin/company/device-settings', [
            'allow_mobile_clock'  => true,
            'allow_desktop_clock' => true,
        ]);

        // Role middleware returns 403; if it somehow reaches the controller without company context, returns 404.
        // Either way, the employee is denied access.
        $this->assertContains($putResponse->status(), [403, 404]);
    }

    public function test_validation_rejects_non_boolean_values(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->putJson('/v1/admin/company/device-settings', [
            'allow_mobile_clock'  => 'yes',
            'allow_desktop_clock' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['allow_mobile_clock', 'allow_desktop_clock']);
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }
}
