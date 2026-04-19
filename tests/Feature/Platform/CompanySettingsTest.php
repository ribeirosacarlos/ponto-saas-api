<?php

namespace Tests\Feature\Platform;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'super_admin'], ['display_name' => 'Platform Administrator']);
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
    }

    public function test_super_admin_can_view_company_admin_settings(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->syncRoles(['super_admin']);

        $company = Company::factory()->create([
            'timezone' => 'America/Sao_Paulo',
            'audit_logs_enabled' => true,
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->getJson("/v1/platform/companies/{$company->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.company.id', $company->id)
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.audit_logs_enabled', true);
    }

    public function test_super_admin_can_update_company_admin_settings_and_audits_changes(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->syncRoles(['super_admin']);

        $company = Company::factory()->create([
            'timezone' => 'Europe/Madrid',
            'audit_logs_enabled' => false,
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/v1/platform/companies/{$company->id}/settings", [
            'timezone' => 'America/Sao_Paulo',
            'audit_logs_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.audit_logs_enabled', true);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'timezone' => 'America/Sao_Paulo',
            'audit_logs_enabled' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.company_settings_updated',
            'entity_type' => Company::class,
            'entity_id' => $company->id,
            'target_company_id' => $company->id,
            'performed_by_role' => 'super_admin',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.company_timezone_updated',
            'entity_type' => Company::class,
            'entity_id' => $company->id,
            'target_company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.company_audit_logs_enabled',
            'entity_type' => Company::class,
            'entity_id' => $company->id,
            'target_company_id' => $company->id,
        ]);
    }

    public function test_super_admin_can_disable_company_audit_logs_and_generates_specific_audit(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->syncRoles(['super_admin']);

        $company = Company::factory()->create([
            'audit_logs_enabled' => true,
        ]);

        Sanctum::actingAs($superAdmin, ['*']);

        $this->patchJson("/v1/platform/companies/{$company->id}/settings", [
            'audit_logs_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('data.audit_logs_enabled', false);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform.company_audit_logs_disabled',
            'entity_type' => Company::class,
            'entity_id' => $company->id,
            'target_company_id' => $company->id,
        ]);
    }

    public function test_admin_cannot_access_platform_company_settings_endpoints(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->syncRoles(['admin']);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson("/v1/platform/companies/{$company->id}/settings")
            ->assertForbidden();
    }

    public function test_settings_update_validates_timezone(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->syncRoles(['super_admin']);

        $company = Company::factory()->create();

        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/v1/platform/companies/{$company->id}/settings", [
            'timezone' => 'Invalid/Timezone',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('timezone');
    }

    public function test_settings_update_requires_at_least_one_supported_field(): void
    {
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->syncRoles(['super_admin']);

        $company = Company::factory()->create();

        Sanctum::actingAs($superAdmin, ['*']);

        $this->putJson("/v1/platform/companies/{$company->id}/settings", [
            'unexpected' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings');
    }
}
