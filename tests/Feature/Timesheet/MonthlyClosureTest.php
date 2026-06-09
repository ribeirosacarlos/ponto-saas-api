<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\Area;
use App\Models\Company;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
        Role::updateOrCreate(['name' => 'area_manager'], ['display_name' => 'Area Manager']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_admin_can_close_a_past_month(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $response = $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_year', 2026)
            ->assertJsonPath('data.reference_month', 4)
            ->assertJsonPath('data.employee_id', (string) $employee->id)
            ->assertJsonPath('data.status', ClosureStatus::PROCESSING->value);

        $this->assertDatabaseHas('monthly_closures', [
            'company_id' => $admin->company_id,
            'employee_id' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::PROCESSING->value,
        ]);
    }

    public function test_cannot_close_current_or_future_month(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $now = CarbonImmutable::now();

        $response = $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee->id,
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
        $employee = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee->id,
            'reference_year' => 2026,
            'reference_month' => 3,
        ])->assertStatus(201);

        $this->actingAs($admin)->postJson('/api/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee->id,
            'reference_year' => 2026,
            'reference_month' => 3,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('reference_month');
    }

    public function test_closure_creates_timesheet_only_for_requested_employee(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee1 = $this->createEmployee($admin->company_id);
        $employee2 = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee1->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertStatus(201);

        $closure = MonthlyClosure::where('company_id', $admin->company_id)->first();

        $this->assertDatabaseHas('employee_timesheets', [
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee1->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
        ]);

        $this->assertDatabaseMissing('employee_timesheets', [
            'employee_id' => $employee2->id,
        ]);

        Queue::assertPushed(GenerateEmployeeTimesheetJob::class, 1);
    }

    public function test_same_month_can_be_closed_for_different_employees(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee1 = $this->createEmployee($admin->company_id);
        $employee2 = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee1->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertStatus(201);

        $secondResponse = $this->actingAs($admin)->postJson('/api/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee2->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ]);

        $secondResponse->assertStatus(201);

        $this->assertDatabaseHas('monthly_closures', [
            'company_id' => $admin->company_id,
            'employee_id' => $employee1->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ]);

        $this->assertDatabaseHas('monthly_closures', [
            'company_id' => $admin->company_id,
            'employee_id' => $employee2->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ]);

        Queue::assertPushed(GenerateEmployeeTimesheetJob::class, 2);
    }

    public function test_legacy_company_monthly_closure_blocks_individual_closure(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        MonthlyClosure::create([
            'company_id' => $admin->company_id,
            'closed_by' => $admin->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('reference_month');
    }

    public function test_closure_status_advances_to_open_after_all_snapshots_generated(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'employee_id' => (string) $employee->id,
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

    public function test_index_lists_only_closures_from_authenticated_company(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin();

        $ownClosure = $this->createClosure($admin, 2026, 4);
        $otherClosure = $this->createClosure($otherAdmin, 2026, 3);

        $response = $this->actingAs($admin)->getJson('/v1/admin/monthly-closures');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$ownClosure->id], $ids);
        $this->assertNotContains($otherClosure->id, $ids);
    }

    public function test_user_cannot_view_closure_from_another_company(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin();
        $otherClosure = $this->createClosure($otherAdmin, 2026, 4);

        $this->actingAs($admin)
            ->getJson("/v1/admin/monthly-closures/{$otherClosure->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_list_timesheets_from_closure_in_another_company(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin();
        $otherEmployee = $this->createEmployee($otherAdmin->company_id);
        $otherClosure = $this->createClosure($otherAdmin, 2026, 4);

        $this->createTimesheet($otherClosure, $otherEmployee);

        $this->actingAs($admin)
            ->getJson("/v1/admin/monthly-closures/{$otherClosure->id}/timesheets")
            ->assertForbidden();
    }

    public function test_area_manager_lists_only_timesheets_from_managed_areas(): void
    {
        $company = Company::factory()->create();
        $visibleArea = Area::factory()->create(['company_id' => $company->id]);
        $hiddenArea = Area::factory()->create(['company_id' => $company->id]);

        $areaManager = $this->createAreaManager($company->id, [$visibleArea]);
        $visibleEmployee = $this->createEmployee($company->id, $visibleArea->id);
        $hiddenEmployee = $this->createEmployee($company->id, $hiddenArea->id);
        $closure = $this->createClosure($areaManager, 2026, 4, $visibleEmployee);

        $visibleTimesheet = $this->createTimesheet($closure, $visibleEmployee);
        $hiddenTimesheet = $this->createTimesheet($closure, $hiddenEmployee);

        $response = $this->actingAs($areaManager)
            ->getJson("/v1/admin/monthly-closures/{$closure->id}/timesheets");

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([$visibleTimesheet->id], $ids);
        $this->assertNotContains($hiddenTimesheet->id, $ids);
    }

    private function createAdmin(?string $companyId = null): User
    {
        $user = User::factory()->create(array_filter(['company_id' => $companyId]));
        $user->assignRole('admin');

        return $user;
    }

    private function createAreaManager(string $companyId, array $managedAreas): User
    {
        $user = User::factory()->create(['company_id' => $companyId]);
        $user->assignRole('area_manager');

        $payload = collect($managedAreas)
            ->mapWithKeys(fn (Area $area) => [$area->id => [
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
            ]])
            ->all();

        $user->managedAreas()->sync($payload);

        return $user->fresh('roles', 'managedAreas');
    }

    private function createEmployee(string $companyId, ?string $areaId = null): User
    {
        $user = User::factory()->create([
            'company_id' => $companyId,
            'area_id' => $areaId,
        ]);
        $user->assignRole('employee');

        return $user;
    }

    private function createClosure(User $closedBy, int $year, int $month, ?User $employee = null): MonthlyClosure
    {
        $employee ??= $this->createEmployee($closedBy->company_id);

        return MonthlyClosure::create([
            'company_id' => $closedBy->company_id,
            'closed_by' => $closedBy->id,
            'employee_id' => $employee->id,
            'reference_year' => $year,
            'reference_month' => $month,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);
    }

    private function createTimesheet(MonthlyClosure $closure, User $employee): EmployeeTimesheet
    {
        return EmployeeTimesheet::create([
            'company_id' => $closure->company_id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
            'snapshot_generated_at' => now(),
            'snapshot' => ['totals' => []],
        ]);
    }
}
