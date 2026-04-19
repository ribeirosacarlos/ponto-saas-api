<?php

namespace Tests\Feature\Audit;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLogFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        foreach (['admin', 'employee'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst($roleName)]
            );
        }
    }

    public function test_area_creation_writes_business_audit_log(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->syncRoles(['admin']);

        $this->actingAs($admin)->postJson('/v1/admin/areas', [
            'name' => 'Financeiro',
        ])->assertCreated();

        $audit = AuditLog::query()
            ->where('action', 'area.created')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((string) $company->id, (string) $audit->company_id);
        $this->assertSame((string) $admin->id, (string) $audit->user_id);
        $this->assertSame('admin', $audit->performed_by_role);
        $this->assertSame('Financeiro', data_get($audit->new_values, 'name'));
    }

    public function test_accepting_invite_creates_audit_log_without_exposing_password(): void
    {
        $company = Company::factory()->create();
        $employee = User::factory()->create([
            'company_id' => $company->id,
            'invite_code_hash' => hash('sha256', 'ABCD1234'),
            'invite_expires_at' => now()->addDay(),
            'must_change_password' => true,
        ]);
        $employee->syncRoles(['employee']);

        $this->postJson('/v1/invites/accept', [
            'invite_code' => 'ABCD1234',
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])->assertOk();

        $audit = AuditLog::query()
            ->where('action', 'invite.accepted')
            ->latest('created_at')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame((string) $company->id, (string) $audit->company_id);
        $this->assertSame((string) $employee->id, (string) $audit->entity_id);
        $this->assertNull(data_get($audit->new_values, 'password'));
        $this->assertFalse(filter_var(data_get($audit->new_values, 'must_change_password'), FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(Hash::check('new-secret-password', $employee->fresh()->password));
    }
}
