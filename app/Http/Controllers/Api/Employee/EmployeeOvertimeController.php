<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\OvertimeReportRequest;
use App\Models\User;
use App\Services\TimeEntry\OvertimeCalculatorService;
use Carbon\CarbonImmutable;

class EmployeeOvertimeController extends Controller
{
    public function __construct(
        protected OvertimeCalculatorService $overtimeCalculator
    ) {
    }

    public function show(OvertimeReportRequest $request, User $employee)
    {
        $this->authorize('view', $employee);

        $validated = $request->validated();

        $timezone = $employee->company?->timezone ?? config('app.timezone', 'UTC');
        $from = CarbonImmutable::parse($validated['from'], $timezone);
        $to = CarbonImmutable::parse($validated['to'], $timezone);
        $includeDays = $request->boolean('include_days');

        $payload = $this->overtimeCalculator->calculateForEmployee($employee, $from, $to, $includeDays);

        if (! $includeDays) {
            unset($payload['days']);
        }

        return response()->json($payload);
    }
}
