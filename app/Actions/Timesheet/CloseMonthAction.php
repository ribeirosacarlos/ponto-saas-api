<?php

namespace App\Actions\Timesheet;

use App\Models\MonthlyClosure;
use App\Models\User;
use App\Services\Timesheet\MonthlyClosureService;

class CloseMonthAction
{
    public function __construct(
        protected MonthlyClosureService $closureService
    ) {}

    public function execute(User $admin, int $year, int $month): MonthlyClosure
    {
        return $this->closureService->close($admin, $year, $month);
    }
}
