<?php

namespace App\Services\TimeEntry;

use App\Models\User;
use App\Support\TimeEntryDaySummary;
use Carbon\CarbonImmutable;

class WorkedTodayService
{
    protected $timesheetCalculationService;

    public function __construct(TimesheetCalculationService $timesheetCalculationService)
    {
        $this->timesheetCalculationService = $timesheetCalculationService;
    }

    /**
     * @return array<string, mixed>
     */
    public function getWorkedToday(User $user, ?CarbonImmutable $overrideNow = null): array
    {
        $timezone = $user->company ? $user->company->timezone : config('app.timezone', 'UTC');
        $today = ($overrideNow ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();

        $result = $this->timesheetCalculationService->calculateForEmployee(
            $user,
            $today,
            $today,
            false
        );

        $day = $result['days'][0] ?? [
            'date' => $today->toDateString(),
            'entries' => [],
            'summary' => [],
        ];
        $summary = TimeEntryDaySummary::normalize($day['summary'] ?? null);

        return array_merge([
            'date' => $day['date'],
            'worked_seconds' => $summary['worked_minutes'] * 60,
            'worked_minutes' => $summary['worked_minutes'],
            'worked_hours_decimal' => round($summary['worked_minutes'] / 60, 2),
            'expected_break_minutes' => $summary['allowed_break_minutes'],
            'break_seconds_deducted' => $summary['exceeded_break_minutes'] * 60,
            'open_session' => $summary['open_session'],
            'summary' => $summary,
            'details' => [
                'open_pair' => $summary['open_pair'],
                'pairs' => array_map(function (array $pair) {
                    return [
                        'in' => $pair['in'],
                        'out' => $pair['out'],
                        'seconds' => ((int) $pair['minutes']) * 60,
                    ];
                }, $summary['pair_details']),
                'entries' => $day['entries'],
            ],
        ], TimeEntryDaySummary::rootAliases($summary));
    }
}
