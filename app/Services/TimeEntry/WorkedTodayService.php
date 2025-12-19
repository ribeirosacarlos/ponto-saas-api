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
    /**
     * Automatically deduct the scheduled break after six hours of gross work.
     * Use this to decide when the auto-deduction rule should kick in.
     */
    private const AUTOMATIC_BREAK_THRESHOLD_SECONDS = 6 * 3600;

    public function __construct(
        protected UserShiftResolver $shiftResolver
    ) {
    }

    /**
     * @return array{date: string, worked_seconds: int, worked_minutes: int, worked_hours_decimal: float, expected_break_minutes: int, break_seconds_deducted: int, open_session: bool, details: array}
     */
    public function getWorkedToday(User $user, ?CarbonImmutable $overrideNow = null): array
    {
        $timezone = config('app.timezone') ?? 'UTC';
        $now = $overrideNow ?? CarbonImmutable::now($timezone);
        $periodStart = $now->startOfDay();
        $periodEnd = $now->endOfDay();

        $entries = $user->timeEntries()
            ->whereBetween('clocked_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
            ->orderBy('clocked_at')
            ->orderBy('created_at')
            ->get();

        $shift = $this->shiftResolver->resolve($user)['shift'];
        $shiftDay = $this->resolveShiftDay($shift, $now);

        [$pairs, $openSession] = $this->buildWorkPairs($entries, $now, $timezone);
        $workedSecondsBruto = array_sum(array_column($pairs, 'seconds'));
        $explicitBreakSeconds = $this->calculateExplicitBreakSeconds($entries, $now, $timezone);
        $expectedBreakMinutes = $this->determineExpectedBreakMinutes($shiftDay);

        $autoDeduct = $this->shouldAutoDeductBreak(
            $explicitBreakSeconds,
            $expectedBreakMinutes,
            $workedSecondsBruto,
            $pairs,
            $shiftDay,
            $now
        );

        $breakSecondsDeducted = $this->determineBreakSecondsDeducted(
            $explicitBreakSeconds,
            $expectedBreakMinutes,
            $autoDeduct,
            $workedSecondsBruto
        );

        $workedSeconds = max(0, $workedSecondsBruto - $breakSecondsDeducted);
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

    private function buildWorkPairs(Collection $entries, CarbonImmutable $now, string $timezone): array
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

        $openSession = false;

        if ($pendingIn) {
            $pairs[] = $this->createPair($pendingIn, $now);
            $openSession = true;
        }

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

    private function calculateExplicitBreakSeconds(Collection $entries, CarbonImmutable $now, string $timezone): int
    {
        $breakSeconds = 0;
        /** @var CarbonImmutable|null $pendingBreak */
        $pendingBreak = null;

        foreach ($entries as $entry) {
            if ($entry->type === 'break_start') {
                $pendingBreak = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);
                continue;
            }

            if ($entry->type === 'break_end' && $pendingBreak) {
                $end = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);
                if ($end->greaterThan($pendingBreak)) {
                    $breakSeconds += $end->diffInSeconds($pendingBreak);
                }
                $pendingBreak = null;
            }
        }

        if ($pendingBreak) {
            if ($now->greaterThan($pendingBreak)) {
                $breakSeconds += $now->diffInSeconds($pendingBreak);
            }
        }

        return $breakSeconds;
    }

    private function determineExpectedBreakMinutes(?ShiftDay $shiftDay): int
    {
        if (! $shiftDay) {
            return 0;
        }

        return (int) ($shiftDay->break_minutes ?? 0);
    }

    /**
     * Only deduct the scheduled break automatically when no explicit break entries exist,
     * and the gross work exceeds the configured threshold OR crosses the scheduled break window.
     */
    private function shouldAutoDeductBreak(
        int $explicitBreakSeconds,
        int $expectedBreakMinutes,
        int $workedSecondsBruto,
        array $pairs,
        ?ShiftDay $shiftDay,
        CarbonImmutable $today
    ): bool {
        if ($explicitBreakSeconds > 0 || $expectedBreakMinutes <= 0) {
            return false;
        }

        if ($workedSecondsBruto > self::AUTOMATIC_BREAK_THRESHOLD_SECONDS) {
            return true;
        }

        return $this->pairsCrossBreakWindow($pairs, $shiftDay, $today);
    }

    private function pairsCrossBreakWindow(array $pairs, ?ShiftDay $shiftDay, CarbonImmutable $today): bool
    {
        if (! $shiftDay || ! $shiftDay->break_start_time || ! $shiftDay->break_end_time) {
            return false;
        }

        $breakStart = $today->setTimeFromTimeString($shiftDay->break_start_time);
        $breakEnd = $today->setTimeFromTimeString($shiftDay->break_end_time);

        foreach ($pairs as $pair) {
            $in = $pair['in'];
            $out = $pair['out'];
            if ($in->lessThan($breakEnd) && $out->greaterThan($breakStart)) {
                return true;
            }
        }

        return false;
    }

    private function determineBreakSecondsDeducted(
        int $explicitBreakSeconds,
        int $expectedBreakMinutes,
        bool $autoDeduct,
        int $workedSecondsBruto
    ): int {
        if ($explicitBreakSeconds > 0) {
            return min($explicitBreakSeconds, $workedSecondsBruto);
        }

        if ($autoDeduct) {
            $expectedSeconds = $expectedBreakMinutes * 60;
            return min($expectedSeconds, $workedSecondsBruto);
        }

        return 0;
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
}
