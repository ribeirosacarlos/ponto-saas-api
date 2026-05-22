<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_employee_can_sign_own_timesheet(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $response = $this->actingAs($employee)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload());

        $response->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::PENDING_MANAGER->value);

        $this->assertDatabaseHas('timesheet_signatures', [
            'employee_timesheet_id' => $timesheet->id,
            'signer_id' => $employee->id,
            'role' => 'employee',
            'accepted_terms' => 1,
        ]);
    }

    public function test_employee_cannot_sign_others_timesheet(): void
    {
        $employee1 = $this->createEmployee();
        $employee2 = $this->createEmployee($employee1->company_id);
        $timesheet = $this->createTimesheet($employee1, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($employee2)
            ->postJson("/v1/employee/timesheets/{$timesheet->id}/sign", $this->validSignPayload())
            ->assertForbidden();
    }

    public function test_manager_can_sign_after_employee(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_MANAGER);

        $response = $this->actingAs($admin)
            ->postJson("/v1/admin/timesheets/{$timesheet->id}/sign");

        $response->assertOk()
            ->assertJsonPath('data.status', TimesheetStatus::COMPLETED->value);

        $this->assertDatabaseHas('timesheet_signatures', [
            'employee_timesheet_id' => $timesheet->id,
            'signer_id' => $admin->id,
            'role' => 'manager',
        ]);
    }

    public function test_manager_cannot_sign_before_employee(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $timesheet = $this->createTimesheet($employee, TimesheetStatus::PENDING_EMPLOYEE);

        $this->actingAs($admin)
            ->postJson("/v1/admin/timesheets/{$timesheet->id}/sign")
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_manager_cannot_sign_timesheet_of_unmanaged_employee(): void
    {
        $admin = $this->createAdmin();
        $otherEmployee = $this->createEmployee();
        $timesheet = $this->createTimesheet($otherEmployee, TimesheetStatus::PENDING_MANAGER);

        $this->actingAs($admin)
            ->postJson("/v1/admin/timesheets/{$timesheet->id}/sign")
            ->assertForbidden();
    }

    public function test_closure_completes_when_all_timesheets_signed(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $closure = $this->createClosure($admin, ClosureStatus::OPEN);
        $timesheet = $this->createTimesheetForClosure($closure, $employee, TimesheetStatus::PENDING_MANAGER);

        $this->actingAs($admin)
            ->postJson("/v1/admin/timesheets/{$timesheet->id}/sign")
            ->assertOk();

        $this->assertDatabaseHas('monthly_closures', [
            'id' => $closure->id,
            'status' => ClosureStatus::COMPLETED->value,
        ]);
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

    private function createClosure(User $admin, ClosureStatus $status): MonthlyClosure
    {
        return MonthlyClosure::create([
            'company_id' => $admin->company_id,
            'closed_by' => $admin->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => $status->value,
            'closed_at' => now(),
        ]);
    }

    private function createTimesheetForClosure(
        MonthlyClosure $closure,
        User $employee,
        TimesheetStatus $status
    ): EmployeeTimesheet {
        return EmployeeTimesheet::create([
            'company_id' => $closure->company_id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => $status->value,
            'snapshot_generated_at' => now(),
            'snapshot' => ['totals' => []],
        ]);
    }
}
