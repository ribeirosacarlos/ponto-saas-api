<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Http\Middleware\EnsureSubscriptionTrialOrActive;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimeEntryDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->withoutMiddleware(EnsureSubscriptionTrialOrActive::class);
        $this->withoutMiddleware(EnsurePlanFeature::class);
        $this->seedRoles();
    }

    public function test_admin_can_delete_time_entry_from_own_company(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 08:00:00');

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertOk()
            ->assertJson([
                'message' => 'Registro de ponto excluído com sucesso.',
            ]);

        $this->assertSoftDeleted('time_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_super_admin_can_delete_time_entry_from_own_company(): void
    {
        $company = Company::factory()->create();
        $superAdmin = $this->createUser($company, 'super_admin');
        $employee = $this->createUser($company, 'employee');
        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 09:00:00');

        $this->actingAs($superAdmin)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('time_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_manager_can_delete_time_entry_from_managed_area(): void
    {
        [$company, $areaA] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $employee = $this->createUser($company, 'employee', $areaA);
        $this->assignManagedAreas($manager, [$areaA]);

        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 10:00:00');

        $this->actingAs($manager)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('time_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_area_manager_can_delete_time_entry_from_managed_area(): void
    {
        [$company, $areaA] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'area_manager');
        $employee = $this->createUser($company, 'employee', $areaA);
        $this->assignManagedAreas($manager, [$areaA]);

        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 11:00:00');

        $this->actingAs($manager)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertOk();

        $this->assertSoftDeleted('time_entries', [
            'id' => $entry->id,
        ]);
    }

    public function test_manager_cannot_delete_time_entry_from_unmanaged_area(): void
    {
        [$company, $areaA, $areaB] = $this->createCompanyWithAreas();
        $manager = $this->createUser($company, 'manager');
        $employee = $this->createUser($company, 'employee', $areaB);
        $this->assignManagedAreas($manager, [$areaA]);

        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 12:00:00');

        $this->actingAs($manager)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
    }

    public function test_employee_cannot_delete_time_entry(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 13:00:00');

        $this->actingAs($employee)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_from_other_company_cannot_delete_time_entry(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = $this->createUser($otherCompany, 'admin');
        $employee = $this->createUser($company, 'employee');
        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 14:00:00');

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleted_time_entry_does_not_appear_in_listings_or_reports(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $visibleEntry = $this->createTimeEntry($company, $employee, '2026-04-10 08:00:00');
        $deletedEntry = $this->createTimeEntry($company, $employee, '2026-04-10 12:00:00');

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/time-entries/{$deletedEntry->id}")
            ->assertOk();

        $employeeEntries = $this->actingAs($employee)
            ->getJson('/api/v1/employee/entries');

        $employeeEntries->assertOk();
        $this->assertSame([$visibleEntry->id], collect($employeeEntries->json('data'))->pluck('id')->all());

        $reportResponse = $this->actingAs($admin)
            ->getJson('/api/v1/admin/reports/time?start=2026-04-10%2000:00:00&end=2026-04-10%2023:59:59');

        $reportResponse->assertOk();
        $this->assertSame([$visibleEntry->id], collect($reportResponse->json())->pluck('id')->all());
    }

    public function test_deletion_creates_audit_log_with_expected_payload(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $entry = $this->createTimeEntry($company, $employee, '2026-04-10 15:00:00', 'out');

        $this->actingAs($admin)
            ->withHeader('User-Agent', 'TimeEntryDeleteTest/1.0')
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.10'])
            ->deleteJson("/v1/admin/time-entries/{$entry->id}")
            ->assertOk();

        $auditLog = AuditLog::where('action', 'time_record.deleted')->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($company->id, $auditLog->company_id);
        $this->assertSame($admin->id, $auditLog->user_id);
        $this->assertSame(TimeEntry::class, $auditLog->entity_type);
        $this->assertSame($entry->id, $auditLog->entity_id);
        $this->assertSame('127.0.0.10', $auditLog->ip_address);
        $this->assertSame('TimeEntryDeleteTest/1.0', $auditLog->user_agent);
        $this->assertSame($employee->id, $auditLog->metadata['employee_user_id']);
        $this->assertSame($entry->id, $auditLog->metadata['time_record_id']);
        $this->assertSame('exclusão manual por admin/gestor', $auditLog->metadata['technical_reason']);
        $this->assertSame('out', $auditLog->metadata['type']);
        $this->assertSame(
            $entry->fresh()->clocked_at?->toIso8601String(),
            $auditLog->metadata['original_clocked_at']
        );
    }

    private function createCompanyWithAreas(): array
    {
        $company = Company::factory()->create();
        $areaA = Area::factory()->create(['company_id' => $company->id]);
        $areaB = Area::factory()->create(['company_id' => $company->id]);

        return [$company, $areaA, $areaB];
    }

    private function createUser(Company $company, string $role, ?Area $area = null): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'area_id' => $area?->id,
        ]);
        $user->syncRoles([$role]);

        return $user->fresh(['roles', 'company', 'managedAreas']);
    }

    private function assignManagedAreas(User $user, array $areas): void
    {
        $payload = collect($areas)
            ->mapWithKeys(fn (Area $area) => [$area->id => [
                'id' => (string) Str::uuid(),
                'company_id' => $user->company_id,
            ]])
            ->all();

        $user->managedAreas()->sync($payload);
    }

    private function createTimeEntry(Company $company, User $employee, string $clockedAt, string $type = 'in'): TimeEntry
    {
        return TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
        ]);
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'super_admin', 'manager', 'area_manager', 'employee'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst(str_replace('_', ' ', $roleName))]
            );
        }
    }
}
