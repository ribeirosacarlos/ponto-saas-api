<?php

namespace App\Services\TimeEntry;

use App\Models\User;
use App\Support\TimeEntryDaySummary;
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
            $summary = TimeEntryDaySummary::normalize($day['summary'] ?? null);
            $isIgnored = $summary['has_incomplete_entries'] && $summary['worked_minutes'] === 0;
            $reason = null;

            if ($isIgnored) {
                $reason = $summary['open_session'] ? 'open_day_odd_entries' : 'incomplete_entries';
            }

            return array_merge([
                'date' => $day['date'],
                'summary' => $summary,
                'ignored' => $isIgnored,
                'reason' => $reason,
            ], TimeEntryDaySummary::rootAliases($summary));
        }, $result['days']);

        return $result;
    }
}
