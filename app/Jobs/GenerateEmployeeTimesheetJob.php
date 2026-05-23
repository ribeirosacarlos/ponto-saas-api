<?php

namespace App\Jobs;

use App\Models\EmployeeTimesheet;
use App\Services\TenantManager;
use App\Services\Timesheet\MonthlyClosureService;
use App\Services\Timesheet\TimesheetSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateEmployeeTimesheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public EmployeeTimesheet $timesheet
    ) {}

    public function handle(
        TimesheetSnapshotService $snapshotService,
        MonthlyClosureService $closureService
    ): void {
        $timesheet = $this->timesheet->loadMissing(['monthlyClosure', 'employee.company']);

        app(TenantManager::class)->setTenant($timesheet->employee->company);

        $snapshotService->generate($timesheet);

        $closureService->checkAndAdvanceToOpen($timesheet->monthlyClosure);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Falha ao gerar snapshot da folha', [
            'timesheet_id' => $this->timesheet->id,
            'employee_id' => $this->timesheet->employee_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
