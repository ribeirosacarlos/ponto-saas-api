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
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MonthlyClosureTest extends TestCase
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

    public function test_admin_can_close_a_past_month(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 4,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_year', 2026)
            ->assertJsonPath('data.reference_month', 4)
            ->assertJsonPath('data.status', ClosureStatus::PROCESSING->value);

        $this->assertDatabaseHas('monthly_closures', [
            'company_id' => $admin->company_id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::PROCESSING->value,
        ]);
    }

    public function test_cannot_close_current_or_future_month(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $now = CarbonImmutable::now();

        $response = $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => $now->year,
            'reference_month' => $now->month,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reference_month');
    }

    public function test_cannot_close_same_month_twice(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 3,
        ])->assertStatus(201);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 3,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('reference_month');
    }

    public function test_closure_creates_timesheet_for_each_employee(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee1 = $this->createEmployee($admin->company_id);
        $employee2 = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertStatus(201);

        $closure = MonthlyClosure::where('company_id', $admin->company_id)->first();

        $this->assertDatabaseHas('employee_timesheets', [
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee1->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
        ]);

        $this->assertDatabaseHas('employee_timesheets', [
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee2->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
        ]);

        Queue::assertPushed(GenerateEmployeeTimesheetJob::class, 2);
    }

    public function test_closure_status_advances_to_open_after_all_snapshots_generated(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertStatus(201);

        $closure = MonthlyClosure::where('company_id', $admin->company_id)->first();

        $timesheet = EmployeeTimesheet::where('monthly_closure_id', $closure->id)->first();
        $timesheet->update(['snapshot_generated_at' => now(), 'snapshot' => ['totals' => []]]);

        app(\App\Services\Timesheet\MonthlyClosureService::class)->checkAndAdvanceToOpen($closure);

        $this->assertDatabaseHas('monthly_closures', [
            'id' => $closure->id,
            'status' => ClosureStatus::OPEN->value,
        ]);
    }

    private function createAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function createEmployee(string $companyId): User
    {
        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole('employee');

        return $user;
    }
}
