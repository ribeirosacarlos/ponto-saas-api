<?php

namespace Tests\Feature\Audit;

use App\Enums\SubscriptionStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'employee', 'super_admin'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst(str_replace('_', ' ', $roleName))]
            );
        }
    }

    public function test_admin_can_list_only_logs_from_own_company_scope(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);
        $otherCompany = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->syncRoles(['admin']);

        $actor = User::factory()->create(['company_id' => $company->id]);
        $platformActor = User::factory()->create();
        $platformActor->syncRoles(['super_admin']);

        $companyLog = AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $actor->id,
            'performed_by_role' => 'admin',
            'action' => 'employee.updated',
            'entity_type' => User::class,
            'entity_id' => $actor->id,
            'description' => 'Atualizou colaborador.',
            'new_values' => ['name' => 'Novo Nome'],
            'created_at' => now()->subMinute(),
        ]);

        $targetedPlatformLog = AuditLog::create([
            'target_company_id' => $company->id,
            'user_id' => $platformActor->id,
            'performed_by_role' => 'super_admin',
            'action' => 'platform.company_blocked',
            'entity_type' => Company::class,
            'entity_id' => $company->id,
            'description' => 'Empresa bloqueada.',
            'created_at' => now(),
        ]);

        AuditLog::create([
            'company_id' => $otherCompany->id,
            'performed_by_role' => 'admin',
            'action' => 'employee.deleted',
            'entity_type' => User::class,
            'description' => 'Outro tenant.',
            'created_at' => now()->addSecond(),
        ]);

        $response = $this->actingAs($admin)->getJson('/v1/admin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $targetedPlatformLog->id)
            ->assertJsonPath('data.1.id', $companyLog->id);
    }

    public function test_admin_can_view_only_log_from_own_company_scope(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);
        $otherCompany = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->syncRoles(['admin']);

        $allowedLog = AuditLog::create([
            'company_id' => $company->id,
            'performed_by_role' => 'admin',
            'action' => 'area.created',
            'entity_type' => 'area',
            'description' => 'Área criada.',
            'created_at' => now(),
        ]);

        $forbiddenLog = AuditLog::create([
            'company_id' => $otherCompany->id,
            'performed_by_role' => 'admin',
            'action' => 'area.deleted',
            'entity_type' => 'area',
            'description' => 'Área removida.',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson("/v1/admin/audit-logs/{$allowedLog->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $allowedLog->id);

        $this->actingAs($admin)
            ->getJson("/v1/admin/audit-logs/{$forbiddenLog->id}")
            ->assertNotFound();
    }

    public function test_super_admin_can_list_global_audit_logs_with_filters(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles(['super_admin']);

        $adminA = User::factory()->create(['company_id' => $companyA->id, 'name' => 'Alice Admin']);
        $adminA->syncRoles(['admin']);

        AuditLog::create([
            'company_id' => $companyA->id,
            'user_id' => $adminA->id,
            'performed_by_role' => 'admin',
            'action' => 'employee.updated',
            'entity_type' => User::class,
            'description' => 'Alice alterou colaborador.',
            'created_at' => now()->subDay(),
        ]);

        $matchingLog = AuditLog::create([
            'company_id' => $companyB->id,
            'performed_by_role' => 'super_admin',
            'action' => 'platform.company_blocked',
            'entity_type' => Company::class,
            'entity_id' => $companyB->id,
            'description' => 'Bloqueio por compliance.',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)->getJson('/v1/platform/audit-logs?action=platform.company_blocked&company_id=' . $companyB->id);

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $matchingLog->id)
            ->assertJsonPath('data.0.company.id', $companyB->id);
    }

    public function test_non_super_admin_cannot_access_platform_audit_logs(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->syncRoles(['admin']);

        $this->actingAs($admin)
            ->getJson('/v1/platform/audit-logs')
            ->assertForbidden();
    }
}
