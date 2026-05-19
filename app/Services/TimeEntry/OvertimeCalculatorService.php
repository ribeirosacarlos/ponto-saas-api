<?php

namespace App\Services\TimeEntry;

use App\Models\User;
use Carbon\CarbonImmutable;

class OvertimeCalculatorService
{
    protected $timesheetCalculationService;

    public function __construct(TimesheetCalculationService $timesheetCalculationService)
    {
        $this->timesheetCalculationService = $timesheetCalculationService;
    }

    /**
     * @return array{employee_id: string, from: string, to: string, timezone: string, totals: array<string, int|string>, days?: array<int, array<string, mixed>>}
     */
    public function calculateForEmployee(User $employee, ?CarbonImmutable $from, CarbonImmutable $to, bool $includeDays): array
    {
        $result = $this->timesheetCalculationService->calculateForEmployee(
            $employee,
            $from,
            $to,
            false
        );

        if (! $includeDays) {
            unset($result['days']);

            return $result;
        }

        $result['days'] = array_map(function (array $day) {
            $summary = $day['summary'];

            return [
                'date' => $day['date'],
                'worked_minutes' => $summary['worked_minutes'],
                'expected_minutes' => $summary['expected_minutes'],
                'balance_minutes' => $summary['balance_minutes'],
                'worked_hhmm' => $summary['worked_hhmm'],
                'expected_hhmm' => $summary['expected_hhmm'],
                'balance_hhmm' => $summary['balance_hhmm'],
                'status' => $summary['status'],
                'extra_minutes' => $summary['extra_minutes'],
                'extra_hhmm' => $summary['extra_hhmm'],
                'debt_minutes' => $summary['debt_minutes'],
                'debt_hhmm' => $summary['debt_hhmm'],
                'raw_worked_minutes' => $summary['raw_worked_minutes'],
                'raw_worked_hhmm' => $summary['raw_worked_hhmm'],
                'real_break_minutes' => $summary['real_break_minutes'],
                'allowed_break_minutes' => $summary['allowed_break_minutes'],
                'exceeded_break_minutes' => $summary['exceeded_break_minutes'],
                'is_holiday' => $summary['is_holiday'],
                'holiday_name' => $summary['holiday_name'],
                'is_day_off' => $summary['is_day_off'],
                'is_vacation' => $summary['is_vacation'],
                'is_absence' => $summary['is_absence'],
                'absence_type' => $summary['absence_type'],
                'has_incomplete_entries' => $summary['has_incomplete_entries'],
                'ignored' => $summary['has_incomplete_entries'] && $summary['worked_minutes'] === 0,
                'reason' => $summary['has_incomplete_entries'] ? 'incomplete_entries' : null,
            ];
        }, $result['days']);

        return $result;
    }
}
