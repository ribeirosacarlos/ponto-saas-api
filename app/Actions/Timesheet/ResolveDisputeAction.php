<?php

namespace App\Actions\Timesheet;

use App\Models\TimesheetDispute;
use App\Models\User;
use App\Services\Timesheet\TimesheetDisputeService;

class ResolveDisputeAction
{
    public function __construct(
        protected TimesheetDisputeService $disputeService
    ) {}

    public function execute(TimesheetDispute $dispute, User $resolver, string $resolutionNote): TimesheetDispute
    {
        return $this->disputeService->resolve($dispute, $resolver, $resolutionNote);
    }
}
