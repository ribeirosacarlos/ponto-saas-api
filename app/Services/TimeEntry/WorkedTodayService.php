<?php

namespace App\Services\TimeEntry;

use App\Models\User;
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
        $summary = $day['summary'];

        return [
            'date' => $day['date'],
            'worked_seconds' => ((int) ($summary['worked_minutes'] ?? 0)) * 60,
            'worked_minutes' => (int) ($summary['worked_minutes'] ?? 0),
            'worked_hours_decimal' => round(((int) ($summary['worked_minutes'] ?? 0)) / 60, 2),
            'expected_break_minutes' => (int) ($summary['allowed_break_minutes'] ?? 0),
            'break_seconds_deducted' => ((int) ($summary['exceeded_break_minutes'] ?? 0)) * 60,
            'open_session' => (bool) ($summary['open_session'] ?? false),
            'summary' => $summary,
            'details' => [
                'open_pair' => $summary['open_pair'] ?? null,
                'pairs' => array_map(function (array $pair) {
                    return [
                        'in' => $pair['in'],
                        'out' => $pair['out'],
                        'seconds' => ((int) $pair['minutes']) * 60,
                    ];
                }, $summary['pair_details'] ?? []),
                'entries' => $day['entries'],
            ],
        ];
    }
}
