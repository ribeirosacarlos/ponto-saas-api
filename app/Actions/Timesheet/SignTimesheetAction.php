<?php

namespace App\Actions\Timesheet;

use App\Models\EmployeeTimesheet;
use App\Models\TimesheetSignature;
use App\Models\User;
use App\Services\Timesheet\TimesheetSignatureService;
use Illuminate\Http\Request;

class SignTimesheetAction
{
    public function __construct(
        protected TimesheetSignatureService $signatureService
    ) {}

    public function executeAsEmployee(EmployeeTimesheet $timesheet, User $signer, Request $request): TimesheetSignature
    {
        return $this->signatureService->signAsEmployee($timesheet, $signer, $request);
    }

    public function executeAsManager(EmployeeTimesheet $timesheet, User $signer, Request $request): TimesheetSignature
    {
        return $this->signatureService->signAsManager($timesheet, $signer, $request);
    }
}
