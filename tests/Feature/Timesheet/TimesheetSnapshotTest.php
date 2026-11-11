<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\Company;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\TimeEntry\TimesheetCalculationService;
use App\Services\Timesheet\TimesheetSnapshotService;
use Carbon\CarbonImmutable;
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
            'employee_id' => $employee->id,
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

    public function test_new_snapshot_hides_pending_entries_without_changing_valid_pairs(): void
    {
        $employee = $this->createEmployee();
        foreach ([['08:00', 'in', null], ['12:00', 'out', null], ['12:50', 'in', 'pending'], ['19:00', 'out', null]] as [$time, $type, $status]) {
            \App\Models\TimeEntry::create([
                'company_id' => $employee->company_id,
                'user_id' => $employee->id,
                'clocked_at' => '2026-04-10 '.$time.':00',
                'type' => $type,
                'source' => $status ? 'adjustment' : 'web',
                'adjustment_status' => $status,
            ]);
        }
        $timesheet = $this->createTimesheet($employee);
        app(\App\Services\Timesheet\TimesheetSnapshotService::class)->generate($timesheet);
        $snapshot = $timesheet->fresh()->snapshot;
        $day = collect($snapshot['days'])->firstWhere('date', '2026-04-10');

        $this->assertCount(3, $day['entries']);
        $this->assertSame([null, null, null], array_column($day['entries'], 'adjustment_status'));
        $this->assertSame(['in', 'out', 'out'], array_column($day['entries'], 'type'));
        $this->assertSame(240, $day['summary']['worked_minutes']);
        $this->assertSame(1, $day['summary']['pair_count']);
        $this->assertTrue($day['summary']['has_incomplete_entries']);
        $this->assertSame(240, $snapshot['totals']['worked_minutes']);
    }

    public function test_monthly_closure_snapshot_includes_only_regular_and_approved_entries(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $entry = $this->createEntry($employee, '2026-04-02 09:00:00', 'in');
        $exit = $this->createEntry($employee, '2026-04-02 17:00:00', 'out', 'approved');
        $pending = $this->createEntry($employee, '2026-04-02 17:33:18', 'out', 'pending');
        $this->createEntry($employee, '2026-04-02 18:00:00', 'in', 'pending');
        $this->createEntry($employee, '2026-04-02 19:00:00', 'out', 'pending');
        $this->createEntry($employee, '2026-04-02 20:00:00', 'in', 'rejected');
        $this->createEntry($employee, '2026-04-02 21:00:00', 'out', 'rejected');
        $this->createEntry($employee, '2026-04-01 09:00:00', 'in', 'pending');

        $response = $this->actingAs($admin)->postJson('/v1/admin/monthly-closures', [
            'reference_year' => 2026,
            'reference_month' => 4,
            'employee_id' => $employee->id,
        ])->assertCreated();

        $timesheet = EmployeeTimesheet::where('monthly_closure_id', $response->json('data.id'))->firstOrFail();
        app()->call([new GenerateEmployeeTimesheetJob($timesheet), 'handle']);

        $snapshot = $timesheet->refresh()->snapshot;
        $day = collect($snapshot['days'])->firstWhere('date', '2026-04-02');

        $this->assertSame([$entry->id, $exit->id], array_column($day['entries'], 'id'));
        $this->assertSame(480, $day['summary']['worked_minutes']);
        $this->assertSame(1, $day['summary']['pair_count']);
        $this->assertSame(480, $snapshot['totals']['worked_minutes']);
        $this->assertSame('2026-04-02', $snapshot['counting_since']);
        $this->assertSame([], collect($snapshot['days'])->firstWhere('date', '2026-04-01')['entries']);
        $this->assertDatabaseHas('time_entries', ['id' => $pending->id, 'adjustment_status' => 'pending']);
    }

    public function test_snapshot_does_not_pair_a_regular_entry_with_a_pending_exit(): void
    {
        $employee = $this->createEmployee();
        $entry = $this->createEntry($employee, '2026-04-02 09:00:00', 'in');
        $this->createEntry($employee, '2026-04-02 17:00:00', 'out', 'pending');
        $timesheet = $this->createTimesheet($employee);

        app(TimesheetSnapshotService::class)->generate($timesheet);

        $snapshot = $timesheet->refresh()->snapshot;
        $day = collect($snapshot['days'])->firstWhere('date', '2026-04-02');

        $this->assertSame([$entry->id], array_column($day['entries'], 'id'));
        $this->assertSame(0, $day['summary']['pair_count']);
        $this->assertTrue($day['summary']['open_session']);
        $this->assertSame(0, $snapshot['totals']['worked_minutes']);
    }

    public function test_snapshot_does_not_join_valid_entries_across_a_pending_exit(): void
    {
        $employee = $this->createEmployee();
        $entry = $this->createEntry($employee, '2026-04-02 09:00:00', 'in');
        $this->createEntry($employee, '2026-04-02 12:00:00', 'out', 'pending');
        $exit = $this->createEntry($employee, '2026-04-02 17:00:00', 'out', 'approved');
        $timesheet = $this->createTimesheet($employee);

        app(TimesheetSnapshotService::class)->generate($timesheet);

        $snapshot = $timesheet->refresh()->snapshot;
        $day = collect($snapshot['days'])->firstWhere('date', '2026-04-02');

        $this->assertSame([$entry->id, $exit->id], array_column($day['entries'], 'id'));
        $this->assertSame(0, $day['summary']['pair_count']);
        $this->assertTrue($day['summary']['has_incomplete_entries']);
        $this->assertSame(0, $snapshot['totals']['worked_minutes']);
    }

    public function test_snapshot_without_valid_entries_has_no_worked_hours_or_counting_since(): void
    {
        $employee = $this->createEmployee();
        $this->createEntry($employee, '2026-03-31 09:00:00', 'in', 'pending');
        $this->createEntry($employee, '2026-04-02 09:00:00', 'in', 'pending');
        $this->createEntry($employee, '2026-04-02 17:00:00', 'out', 'pending');
        $this->createEntry($employee, '2026-04-03 09:00:00', 'in', 'rejected');
        $this->createEntry($employee, '2026-04-03 17:00:00', 'out', 'rejected');
        $timesheet = $this->createTimesheet($employee);

        app(TimesheetSnapshotService::class)->generate($timesheet);

        $snapshot = $timesheet->refresh()->snapshot;

        $this->assertSame([], collect($snapshot['days'])->pluck('entries')->flatten(1)->all());
        $this->assertSame(0, $snapshot['totals']['worked_minutes']);
        $this->assertArrayNotHasKey('counting_since', $snapshot);
    }

    public function test_snapshot_filters_company_and_employee_even_without_an_active_tenant(): void
    {
        $employee = $this->createEmployee();
        $otherEmployee = $this->createEmployee($employee->company_id);
        $otherCompany = Company::factory()->create();
        $this->createEntry($otherEmployee, '2026-04-01 09:00:00', 'in', 'approved');
        $this->createEntry($otherEmployee, '2026-04-01 17:00:00', 'out', 'approved');
        $this->createEntry($employee, '2026-04-01 09:00:00', 'in', 'approved', $otherCompany->id);
        $this->createEntry($employee, '2026-04-01 17:00:00', 'out', 'approved', $otherCompany->id);
        $entry = $this->createEntry($employee, '2026-04-02 09:00:00', 'in', 'approved');
        $exit = $this->createEntry($employee, '2026-04-02 17:00:00', 'out', 'approved');
        $timesheet = $this->createTimesheet($employee);

        app(TenantManager::class)->setTenant(null);
        app(TimesheetSnapshotService::class)->generate($timesheet);

        $snapshot = $timesheet->refresh()->snapshot;
        $ids = collect($snapshot['days'])->pluck('entries')->flatten(1)->pluck('id')->all();

        $this->assertSame([$entry->id, $exit->id], $ids);
        $this->assertSame('2026-04-02', $snapshot['counting_since']);
        $this->assertSame(480, $snapshot['totals']['worked_minutes']);
    }

    public function test_hiding_pending_entries_in_snapshots_preserves_operational_reports_and_totals(): void
    {
        $employee = $this->createEmployee();
        $entry = $this->createEntry($employee, '2026-04-02 09:00:00', 'in', 'pending');
        $exit = $this->createEntry($employee, '2026-04-02 17:00:00', 'out', 'pending');
        $date = CarbonImmutable::parse('2026-04-02', $employee->company->timezone);

        $report = app(TimesheetCalculationService::class)->calculateForEmployee($employee, $date, $date);
        $timesheet = $this->createTimesheet($employee);
        app(TimesheetSnapshotService::class)->generate($timesheet);
        $snapshot = $timesheet->refresh()->snapshot;
        $day = collect($snapshot['days'])->firstWhere('date', '2026-04-02');

        $this->assertSame([$entry->id, $exit->id], array_column($report['days'][0]['entries'], 'id'));
        $this->assertSame($report['days'][0]['summary'], $day['summary']);
        $this->assertSame(0, $snapshot['totals']['worked_minutes']);
        $this->assertSame([], $day['entries']);
    }

    private function createEntry(User $employee, string $clockedAt, string $type, ?string $status = null, ?string $companyId = null): TimeEntry
    {
        return TimeEntry::create([
            'company_id' => $companyId ?? $employee->company_id,
            'user_id' => $employee->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
            'adjustment_status' => $status,
        ]);
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
