<?php

namespace App\Services\TimeEntry;

use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\User;
use App\Services\UserShiftResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WorkedTodayService
{
    public function __construct(
        protected UserShiftResolver $shiftResolver
    ) {
    }

    /**
     * @return array{
     *   date: string,
     *   worked_seconds: int,
     *   worked_minutes: int,
     *   worked_hours_decimal: float,
     *   expected_break_minutes: int,
     *   break_seconds_deducted: int,
     *   open_session: bool,
     *   details: array{
     *     pairs: array<int, array{in: string, out: string, seconds: int}>,
     *     entries: array<int, array{id: string, clocked_at: string, type: string|null, event_kind: string|null, adjustment_status: string|null, adjustment_reason: string|null, source: string|null}>
     *   }
     * }
     */
    public function getWorkedToday(User $user, ?CarbonImmutable $overrideNow = null): array
    {
        $timezone = config('app.timezone') ?? 'UTC';
        $now = $overrideNow ?? CarbonImmutable::now($timezone);
        $periodStart = $now->startOfDay();
        $periodEnd = $now->endOfDay();

        $entries = $user->timeEntries()
            ->whereBetween('clocked_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
            ->excludeRejected()
            ->orderBy('clocked_at')
            ->orderBy('created_at')
            ->get();

        $shift = $this->shiftResolver->resolve($user)['shift'];
        $shiftDay = $this->resolveShiftDay($shift, $now);

        [$pairs, $openSession] = $this->buildWorkPairs($entries, $timezone);
        $workedSecondsBruto = (int) array_sum(array_column($pairs, 'seconds'));
        $expectedBreakMinutes = $this->determineExpectedBreakMinutes($shiftDay);
        $breakSecondsDeducted = 0;
        $workedSeconds = $workedSecondsBruto;
        $detailsPairs = $this->formatPairsForOutput($pairs, $timezone);

        return [
            'date' => $now->toDateString(),
            'worked_seconds' => $workedSeconds,
            'worked_minutes' => (int) floor($workedSeconds / 60),
            'worked_hours_decimal' => round($workedSeconds / 3600, 2),
            'expected_break_minutes' => $expectedBreakMinutes,
            'break_seconds_deducted' => $breakSecondsDeducted,
            'open_session' => $openSession,
            'details' => [
                'pairs' => $detailsPairs,
                'entries' => $this->formatEntriesForOutput($entries, $timezone),
            ],
        ];
    }

    private function resolveShiftDay(?Shift $shift, CarbonImmutable $today): ?ShiftDay
    {
        if (! $shift) {
            return null;
        }

        $weekday = $today->isoWeekday();
        $definition = $shift->shiftDays->firstWhere('weekday', $weekday);

        if (! $definition || ! $definition->is_working_day) {
            return null;
        }

        return $definition;
    }

    private function buildWorkPairs(Collection $entries, string $timezone): array
    {
        $pairs = [];
        /** @var CarbonImmutable|null $pendingIn */
        $pendingIn = null;

        foreach ($entries as $entry) {
            $entryTime = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);

            if ($entry->type === 'in') {
                if ($pendingIn) {
                    $pairs[] = $this->createPair($pendingIn, $entryTime);
                }

                $pendingIn = $entryTime;
                continue;
            }

            if ($entry->type === 'out' && $pendingIn) {
                $pairs[] = $this->createPair($pendingIn, $entryTime);
                $pendingIn = null;
            }
        }

        $openSession = (bool) $pendingIn;

        return [$pairs, $openSession];
    }

    private function createPair(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $seconds = $end->greaterThan($start) ? $end->diffInSeconds($start, true) : 0;

        return [
            'in' => $start,
            'out' => $end,
            'seconds' => $seconds,
        ];
    }

    private function determineExpectedBreakMinutes(?ShiftDay $shiftDay): int
    {
        if (! $shiftDay) {
            return 0;
        }

        return (int) ($shiftDay->break_minutes ?? 0);
    }

    private function formatPairsForOutput(array $pairs, string $timezone): array
    {
        return array_map(function (array $pair) use ($timezone) {
            return [
                'in' => $pair['in']->timezone($timezone)->toIso8601String(),
                'out' => $pair['out']->timezone($timezone)->toIso8601String(),
                'seconds' => $pair['seconds'],
            ];
        }, $pairs);
    }

    private function formatEntriesForOutput(Collection $entries, string $timezone): array
    {
        return $entries->map(function ($entry) use ($timezone) {
            return [
                'id' => $entry->id,
                'clocked_at' => CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toIso8601String(),
                'type' => $entry->type,
                'event_kind' => $entry->event_kind,
                'adjustment_status' => $entry->adjustment_status,
                'adjustment_reason' => $entry->adjustment_reason,
                'source' => $entry->source,
            ];
        })->values()->all();
    }
}
