<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\TimesheetDispute;
use App\Models\TimesheetSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthlyClosureDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        foreach (['admin', 'manager', 'area_manager', 'employee'] as $role) {
            Role::updateOrCreate(['name' => $role], ['display_name' => $role]);
        }
    }

    public function test_admin_deletes_unsigned_closure_and_dependents_preserving_entries_and_audit(): void
    {
        [$admin, $employee, $closure, $timesheet] = $this->fixture();
        $dispute = TimesheetDispute::create([
            'company_id' => $admin->company_id,
            'employee_timesheet_id' => $timesheet->id,
            'employee_id' => $employee->id,
            'reason' => 'Corrigir horário',
            'status' => 'open',
        ]);
        $timesheet->update(['status' => TimesheetStatus::DISPUTED]);
        $entry = TimeEntry::create([
            'company_id' => $admin->company_id,
            'user_id' => $employee->id,
            'clocked_at' => '2026-04-01 08:00:00',
            'type' => 'in',
            'source' => 'web',
        ]);
        app(\App\Services\AuditLogService::class)->log(
            action: 'monthly_closure.created',
            entityType: MonthlyClosure::class,
            entityId: $closure->id,
            companyId: $admin->company_id,
        );

        $this->actingAs($admin)->deleteJson($this->url($closure))->assertNoContent();

        $this->assertDatabaseMissing('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseMissing('employee_timesheets', ['id' => $timesheet->id]);
        $this->assertDatabaseMissing('timesheet_disputes', ['id' => $dispute->id]);
        $this->assertDatabaseHas('time_entries', ['id' => $entry->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $closure->id, 'action' => 'monthly_closure.created']);
        $audit = AuditLog::where('entity_id', $closure->id)->where('action', 'monthly_closure.deleted')->sole();
        $this->assertSame($admin->company_id, $audit->company_id);
        $this->assertSame((string) $admin->id, (string) $audit->user_id);
        $this->assertSame((string) $employee->id, $audit->old_values['employee_id']);
        $this->assertSame(4, $audit->old_values['reference_month']);
        $this->assertSame('open', $audit->old_values['status']);
    }

    #[DataProvider('signatureCases')]
    public function test_any_recorded_signature_blocks_deletion(string $role, bool $superseded): void
    {
        [$admin, $employee, $closure, $timesheet] = $this->fixture();
        $signature = TimesheetSignature::create([
            'company_id' => $closure->company_id,
            'employee_timesheet_id' => $timesheet->id,
            'signer_id' => $role === 'employee' ? $employee->id : $admin->id,
            'role' => $role,
            'signed_at' => now(),
            'superseded_at' => $superseded ? now() : null,
        ]);

        $this->actingAs($admin)->deleteJson($this->url($closure))
            ->assertUnprocessable()->assertJsonValidationErrors('closure');

        $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseHas('employee_timesheets', ['id' => $timesheet->id]);
        $this->assertDatabaseHas('timesheet_signatures', ['id' => $signature->id]);
        $this->assertDatabaseMissing('audit_logs', ['entity_id' => $closure->id, 'action' => 'monthly_closure.deleted']);
    }

    public static function signatureCases(): array
    {
        return [
            'employee active' => ['employee', false],
            'employee superseded' => ['employee', true],
            'manager active' => ['manager', false],
            'manager superseded' => ['manager', true],
        ];
    }

    #[DataProvider('managerCases')]
    public function test_managers_respect_managed_areas(string $role, bool $visible): void
    {
        [$admin, $employee, $closure] = $this->fixture();
        $area = Area::factory()->create(['company_id' => $admin->company_id]);
        $employee->update(['area_id' => $area->id]);
        $manager = $this->user($role, $admin->company_id);
        if ($visible) {
            $manager->managedAreas()->attach($area->id, [
                'id' => (string) Str::uuid(), 'company_id' => $admin->company_id,
            ]);
        }

        $response = $this->actingAs($manager)->deleteJson($this->url($closure));
        if ($visible) {
            $response->assertNoContent();
            $this->assertDatabaseMissing('monthly_closures', ['id' => $closure->id]);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
        }
    }

    public static function managerCases(): array
    {
        return [
            'manager visible' => ['manager', true],
            'manager hidden' => ['manager', false],
            'area manager visible' => ['area_manager', true],
            'area manager hidden' => ['area_manager', false],
        ];
    }

    public function test_employee_cannot_delete_their_own_closure(): void
    {
        [, $employee, $closure] = $this->fixture();
        $this->actingAs($employee)->deleteJson($this->url($closure))->assertForbidden();
        $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
    }

    public function test_another_company_cannot_delete_or_cascade_the_closure(): void
    {
        [, , $closure, $timesheet] = $this->fixture();
        $otherAdmin = $this->user('admin');
        $this->actingAs($otherAdmin)->deleteJson($this->url($closure))->assertForbidden();
        $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseHas('employee_timesheets', ['id' => $timesheet->id]);
    }

    public function test_cascade_cannot_delete_a_timesheet_with_a_mismatched_company(): void
    {
        [$admin, , $closure, $timesheet] = $this->fixture();
        $otherEmployee = $this->user('employee');
        $timesheet->update(['company_id' => $otherEmployee->company_id, 'employee_id' => $otherEmployee->id]);
        $this->actingAs($admin)->deleteJson($this->url($closure))->assertForbidden();
        $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseHas('employee_timesheets', ['id' => $timesheet->id]);
    }

    public function test_unknown_closure_returns_not_found(): void
    {
        $this->actingAs($this->user('admin'))
            ->deleteJson('http://localhost/api/v1/admin/monthly-closures/'.Str::uuid())->assertNotFound();
    }

    public function test_cascade_cannot_delete_a_dispute_with_a_mismatched_company(): void
    {
        [$admin, , $closure, $timesheet] = $this->fixture();
        $otherEmployee = $this->user('employee');
        $dispute = TimesheetDispute::create([
            'company_id' => $otherEmployee->company_id,
            'employee_timesheet_id' => $timesheet->id,
            'employee_id' => $otherEmployee->id,
            'reason' => 'Invalid tenant association',
            'status' => 'open',
        ]);

        $this->actingAs($admin)->deleteJson($this->url($closure))->assertForbidden();
        $this->assertDatabaseHas('monthly_closures', ['id' => $closure->id]);
        $this->assertDatabaseHas('timesheet_disputes', ['id' => $dispute->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->deleteJson('http://localhost/api/v1/admin/monthly-closures/'.Str::uuid())->assertUnauthorized();
    }

    public function test_processing_closure_can_be_deleted_and_period_closed_again(): void
    {
        Queue::fake();
        [$admin, $employee, $closure] = $this->fixture();
        $closure->update(['status' => ClosureStatus::PROCESSING]);
        $this->actingAs($admin)->deleteJson($this->url($closure))->assertNoContent();
        $this->actingAs($admin)->postJson('http://localhost/api/v1/admin/monthly-closures', [
            'employee_id' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertCreated();
        Queue::assertPushed(GenerateEmployeeTimesheetJob::class);
    }

    public function test_legacy_closure_checks_every_timesheet_for_signatures(): void
    {
        [$admin, , $closure] = $this->fixture();
        $closure->update(['employee_id' => null]);
        $employee = $this->user('employee', $admin->company_id);
        $timesheet = $this->timesheet($closure, $employee);
        TimesheetSignature::create([
            'company_id' => $closure->company_id, 'employee_timesheet_id' => $timesheet->id,
            'signer_id' => $employee->id, 'role' => 'employee', 'signed_at' => now(),
        ]);
        $this->actingAs($admin)->deleteJson($this->url($closure))
            ->assertUnprocessable()->assertJsonValidationErrors('closure');
        $this->assertSame(2, $closure->timesheets()->count());
    }

    public function test_legacy_closure_requires_visibility_of_every_employee(): void
    {
        [$admin, $employee, $closure] = $this->fixture();
        $closure->update(['employee_id' => null]);
        $visibleArea = Area::factory()->create(['company_id' => $admin->company_id]);
        $hiddenArea = Area::factory()->create(['company_id' => $admin->company_id]);
        $employee->update(['area_id' => $visibleArea->id]);
        $otherEmployee = $this->user('employee', $admin->company_id);
        $otherEmployee->update(['area_id' => $hiddenArea->id]);
        $this->timesheet($closure, $otherEmployee);
        $manager = $this->user('manager', $admin->company_id);
        $manager->managedAreas()->attach($visibleArea->id, [
            'id' => (string) Str::uuid(), 'company_id' => $admin->company_id,
        ]);
        $this->actingAs($manager)->deleteJson($this->url($closure))->assertForbidden();
        $this->assertSame(2, $closure->timesheets()->count());

        $manager->managedAreas()->attach($hiddenArea->id, [
            'id' => (string) Str::uuid(), 'company_id' => $admin->company_id,
        ]);
        $this->actingAs($manager)->deleteJson($this->url($closure))->assertNoContent();
        $this->assertDatabaseMissing('employee_timesheets', ['monthly_closure_id' => $closure->id]);
    }

    public function test_legacy_closure_without_employees_is_restricted_to_admin(): void
    {
        [$admin, , $closure] = $this->fixture();
        $closure->update(['employee_id' => null]);
        $closure->timesheets()->delete();
        $manager = $this->user('manager', $admin->company_id);
        $this->actingAs($manager)->deleteJson($this->url($closure))->assertForbidden();
        $this->actingAs($admin)->deleteJson($this->url($closure))->assertNoContent();
    }

    private function fixture(): array
    {
        $admin = $this->user('admin');
        $employee = $this->user('employee', $admin->company_id);
        $closure = MonthlyClosure::create([
            'company_id' => $admin->company_id, 'employee_id' => $employee->id,
            'closed_by' => $admin->id, 'reference_year' => 2026, 'reference_month' => 4,
            'status' => ClosureStatus::OPEN, 'closed_at' => now(),
        ]);

        return [$admin, $employee, $closure, $this->timesheet($closure, $employee)];
    }

    private function timesheet(MonthlyClosure $closure, User $employee): EmployeeTimesheet
    {
        return EmployeeTimesheet::create([
            'company_id' => $closure->company_id, 'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id, 'status' => TimesheetStatus::PENDING_EMPLOYEE,
            'snapshot' => ['totals' => []], 'snapshot_generated_at' => now(),
        ]);
    }

    private function user(string $role, ?string $companyId = null): User
    {
        $user = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
        $user->assignRole($role);

        return $user;
    }

    private function url(MonthlyClosure $closure): string
    {
        return "http://localhost/api/v1/admin/monthly-closures/{$closure->id}";
    }
}
