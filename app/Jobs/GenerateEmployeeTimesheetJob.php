<?php

namespace App\Jobs;

use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Services\TenantManager;
use App\Services\Timesheet\MonthlyClosureService;
use App\Services\Timesheet\TimesheetSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateEmployeeTimesheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public EmployeeTimesheet $timesheet
    ) {}

    public function handle(
        TimesheetSnapshotService $snapshotService,
        MonthlyClosureService $closureService
    ): void {
        $tenantManager = app(TenantManager::class);
        $previousTenant = $tenantManager->tenant();

        try {
            // Workers may still hold the previous job's tenant context.
            $tenantManager->setTenant(null);

            DB::transaction(function () use ($snapshotService, $closureService, $tenantManager) {
                $closure = MonthlyClosure::query()
                    ->where('company_id', $this->timesheet->company_id)
                    ->lockForUpdate()
                    ->find($this->timesheet->monthly_closure_id);

                if (! $closure) {
                    return;
                }

                $timesheet = $closure->timesheets()
                    ->where('company_id', $closure->company_id)
                    ->lockForUpdate()
                    ->find($this->timesheet->id);

                if (! $timesheet) {
                    return;
                }

                $timesheet->load('employee.company')->setRelation('monthlyClosure', $closure);
                abort_unless((string) $timesheet->employee->company_id === (string) $closure->company_id, 403);
                $tenantManager->setTenant($timesheet->employee->company);

                $snapshotService->generate($timesheet);

                $closureService->checkAndAdvanceToOpen($closure);
            });
        } finally {
            $tenantManager->setTenant($previousTenant);
        }
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
