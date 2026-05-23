<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TimesheetSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_snapshot_is_generated_by_job(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertStatus(201);

        Queue::assertPushed(GenerateEmployeeTimesheetJob::class);
    }

    public function test_snapshot_contains_expected_structure(): void
    {
        $employee = $this->createEmployee();
        $timesheet = $this->createTimesheet($employee);

        $snapshotService = app(\App\Services\Timesheet\TimesheetSnapshotService::class);
        $snapshotService->generate($timesheet);

        $timesheet->refresh();

        $this->assertNotNull($timesheet->snapshot_generated_at);
        $this->assertNotNull($timesheet->snapshot);
        $this->assertArrayHasKey('totals', $timesheet->snapshot);
        $this->assertArrayHasKey('days', $timesheet->snapshot);
        $this->assertArrayHasKey('employee_id', $timesheet->snapshot);
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

    private function createTimesheet(User $employee): EmployeeTimesheet
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
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
        ]);
    }
}
