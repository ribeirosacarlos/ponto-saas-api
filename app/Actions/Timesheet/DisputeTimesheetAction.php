<?php

namespace App\Actions\Timesheet;

use App\Models\EmployeeTimesheet;
use App\Models\TimesheetDispute;
use App\Models\User;
use App\Services\Timesheet\TimesheetDisputeService;

class DisputeTimesheetAction
{
    public function __construct(
        protected TimesheetDisputeService $disputeService
    ) {}

    public function execute(EmployeeTimesheet $timesheet, User $employee, string $reason): TimesheetDispute
    {
        return $this->disputeService->dispute($timesheet, $employee, $reason);
    }
}
