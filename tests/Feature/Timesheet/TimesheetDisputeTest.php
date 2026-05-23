<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\DisputeStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\TimesheetDispute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetDisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_employee_can_dispute_own_timesheet(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $response = $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/dispute", [
                'reason' => 'Existem batidas incorretas no período.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', DisputeStatus::OPEN->value);

        $this->assertDatabaseHas('timesheet_disputes', [
            'employee_timesheet_id' => $timesheet->id,
            'employee_id' => $employee->id,
            'status' => DisputeStatus::OPEN->value,
        ]);

        $this->assertDatabaseHas('employee_timesheets', [
            'id' => $timesheet->id,
            'status' => TimesheetStatus::DISPUTED->value,
        ]);
    }

    public function test_dispute_blocks_signing(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::DISPUTED);
        TimesheetDispute::create([
            'company_id' => $timesheet->company_id,
            'employee_timesheet_id' => $timesheet->id,
            'employee_id' => $employee->id,
            'reason' => 'Divergência nos horários.',
            'status' => DisputeStatus::OPEN->value,
        ]);

        $timesheet->update(['status' => TimesheetStatus::PENDING_EMPLOYEE->value]);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('dispute');
    }

    public function test_admin_can_resolve_dispute(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::DISPUTED);
        $dispute = TimesheetDispute::create([
            'company_id' => $timesheet->company_id,
            'employee_timesheet_id' => $timesheet->id,
            'employee_id' => $employee->id,
            'reason' => 'Divergência nos horários.',
            'status' => DisputeStatus::OPEN->value,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/v1/admin/timesheets/{$timesheet->id}/disputes/{$dispute->id}/resolve", [
                'resolution_note' => 'Verificado e corrigido no sistema.',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', DisputeStatus::RESOLVED->value);

        $this->assertDatabaseHas('timesheet_disputes', [
            'id' => $dispute->id,
            'status' => DisputeStatus::RESOLVED->value,
        ]);

        $this->assertDatabaseHas('employee_timesheets', [
            'id' => $timesheet->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
        ]);
    }

    public function test_employee_can_sign_after_dispute_resolved(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::PENDING_MANAGER->value);
    }

    private function validSignPayload(): array
    {
        $pngHeader = "\x89PNG\r\n\x1a\n" . str_repeat("\x00", 100);

        return [
            'signature_image' => 'data:image/png;base64,' . base64_encode($pngHeader),
            'accepted_terms' => true,
            'password' => 'password',
        ];
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createEmployee(?string $companyId = null): User
    {
        $user = User::factory()->create(array_filter(['company_id' => $companyId]));
        $user->assignRole('employee');

        return $user;
    }

    private function createTimesheet(User $employee, TimesheetStatus $status): EmployeeTimesheet
    {
        $closure = MonthlyClosure::create([
            'company_id' => $employee->company_id,
            'closed_by' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        return EmployeeTimesheet::create([
            'company_id' => $employee->company_id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => $status->value,
            'snapshot_generated_at' => now(),
            'snapshot' => ['totals' => []],
        ]);
    }
}
