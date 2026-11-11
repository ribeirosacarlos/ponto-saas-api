<?php

namespace Tests\Feature\Jobs;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\Company;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\User;
use App\Services\TenantManager;
use App\Services\Timesheet\MonthlyClosureService;
use App\Services\Timesheet\TimesheetSnapshotService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedHandler;
use Mockery;
use Tests\TestCase;

class GenerateEmployeeTimesheetJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_job_is_discarded_when_timesheet_was_deleted(): void
    {
        $timesheet = $this->timesheet();
        $command = serialize(new GenerateEmployeeTimesheetJob($timesheet));
        $timesheet->monthlyClosure->delete();

        $job = Mockery::mock(Job::class);
        $job->shouldReceive('resolveQueuedJobClass')->once()->andReturn(GenerateEmployeeTimesheetJob::class);
        $job->shouldReceive('delete')->once();
        $job->shouldNotReceive('fail');
        $this->mock(TimesheetSnapshotService::class)->shouldNotReceive('generate');

        app(CallQueuedHandler::class)->call($job, ['command' => $command]);
    }

    public function test_already_loaded_job_skips_a_deleted_closure(): void
    {
        $timesheet = $this->timesheet();
        $job = new GenerateEmployeeTimesheetJob($timesheet);
        $timesheet->monthlyClosure->delete();
        $previousTenant = Company::factory()->create();
        app(TenantManager::class)->setTenant($previousTenant);
        $snapshotService = $this->mock(TimesheetSnapshotService::class);
        $snapshotService->shouldNotReceive('generate');

        $job->handle($snapshotService, app(MonthlyClosureService::class));

        $this->assertSame($previousTenant, app(TenantManager::class)->tenant());
        $this->assertDatabaseMissing('employee_timesheets', ['id' => $timesheet->id]);
    }

    public function test_generation_uses_job_company_and_restores_previous_tenant(): void
    {
        $timesheet = $this->timesheet();
        $previousTenant = Company::factory()->create();
        app(TenantManager::class)->setTenant($previousTenant);
        $snapshotService = $this->mock(TimesheetSnapshotService::class);
        $snapshotService->shouldReceive('generate')->once()->andReturnUsing(function ($loaded) use ($timesheet) {
            $this->assertSame($timesheet->id, $loaded->id);
            $this->assertSame($timesheet->company_id, app(TenantManager::class)->id());
            $loaded->update(['snapshot' => ['totals' => []], 'snapshot_generated_at' => now()]);
        });

        (new GenerateEmployeeTimesheetJob($timesheet))->handle($snapshotService, app(MonthlyClosureService::class));

        $this->assertSame($previousTenant, app(TenantManager::class)->tenant());
        app(TenantManager::class)->setTenant(null);
        $this->assertDatabaseHas('monthly_closures', [
            'id' => $timesheet->monthly_closure_id, 'status' => ClosureStatus::OPEN->value,
        ]);
    }

    public function test_generation_restores_previous_tenant_on_failure(): void
    {
        $timesheet = $this->timesheet();
        $previousTenant = Company::factory()->create();
        app(TenantManager::class)->setTenant($previousTenant);
        $snapshotService = $this->mock(TimesheetSnapshotService::class);
        $snapshotService->shouldReceive('generate')->once()->andThrow(new \RuntimeException('Snapshot failed'));

        try {
            (new GenerateEmployeeTimesheetJob($timesheet))->handle($snapshotService, app(MonthlyClosureService::class));
            $this->fail('Expected generation failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Snapshot failed', $exception->getMessage());
            $this->assertSame($previousTenant, app(TenantManager::class)->tenant());
        }
    }

    private function timesheet(): EmployeeTimesheet
    {
        $employee = User::factory()->create();
        $closure = MonthlyClosure::create([
            'company_id' => $employee->company_id, 'employee_id' => $employee->id,
            'closed_by' => $employee->id, 'reference_year' => 2026, 'reference_month' => 4,
            'status' => ClosureStatus::PROCESSING, 'closed_at' => now(),
        ]);

        return EmployeeTimesheet::create([
            'company_id' => $closure->company_id, 'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id, 'status' => TimesheetStatus::PENDING_EMPLOYEE,
        ]);
    }
}
