<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeTimesheet;
use App\Services\TimeEntry\TimesheetCalculationService;
use Carbon\CarbonImmutable;

class TimesheetSnapshotService
{
    public function __construct(
        protected TimesheetCalculationService $calculationService
    ) {}

    public function generate(EmployeeTimesheet $timesheet): void
    {
        $closure = $timesheet->monthlyClosure;
        $employee = $timesheet->employee;

        $timezone = $employee->company?->timezone ?? config('app.timezone', 'UTC');

        $from = CarbonImmutable::create(
            $closure->reference_year,
            $closure->reference_month,
            1,
            0, 0, 0,
            $timezone
        );

        $to = $from->endOfMonth();

        $snapshot = $this->calculationService->calculateForEmployee($employee, $from, $to);

        $timesheet->update([
            'snapshot' => $snapshot,
            'snapshot_generated_at' => now(),
        ]);
    }
}
