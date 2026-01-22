<?php

namespace App\Services\TimeEntry;

use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use App\Services\UserShiftResolver;

class OpenStatusService
{
    private const SHIFT_TOLERANCE_MINUTES = 10;
    public function __construct(
        protected UserShiftResolver $shiftResolver
    ) {
    }

    public function getStatus(User $user): array
    {
        $timezone = config('app.timezone') ?? 'UTC';
        $today = CarbonImmutable::now($timezone);
        $periodStart = $today->startOfDay();
        $periodEnd = $today->endOfDay();

        $entries = $user->timeEntries()
            ->whereBetween('clocked_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
            ->orderBy('clocked_at')
            ->get();

        $shiftResult = $this->shiftResolver->resolve($user);
        $shift = $shiftResult['shift'];
        $shiftContext = $this->buildShiftContext($shift, $today);
        $status = $this->determineStatus($entries, $shiftContext['break_expected'], $timezone);
        $shiftPayload = $this->buildShiftPayload($shift, $today, $timezone, $status['first_in']);

        return [
            'date' => $today->toDateString(),
            'has_open_entry' => $status['has_open_entry'],
            'open_type' => $status['open_type'],
            'last_entry' => $status['last_entry'],
            'next_action' => $status['next_action'],
            'shift' => $shiftPayload,
            'assignment' => $this->serializeAssignment($shiftResult['assignment']),
        ];
    }

    private function buildShiftContext(?Shift $shift, CarbonImmutable $today): array
    {
        if (! $shift) {
            return ['break_expected' => false];
        }

        $weekday = $today->isoWeekday();
        $definition = $shift->shiftDays->firstWhere('weekday', $weekday);

        return [
            'break_expected' => (bool) ($definition?->break_start_time && $definition?->break_end_time),
        ];
    }

    private function determineStatus(Collection $entries, bool $breakExpected, string $timezone): array
    {
        $status = [
            'has_open_entry' => false,
            'open_type' => null,
            'next_action' => 'clock_in',
            'last_entry' => null,
            'first_in' => null,
        ];

        if ($entries->isEmpty()) {
            return $status;
        }

        $lastEntry = $entries->last();
        $status['last_entry'] = $this->serializeEntry($lastEntry, $timezone);
        $status['first_in'] = $entries->firstWhere('type', 'in');

        $openBreak = $this->findOpenBreak($entries);

        if ($openBreak) {
            $status['has_open_entry'] = true;
            $status['open_type'] = 'break';
            $status['next_action'] = 'break_end';
            return $status;
        }

        $openWork = $this->findOpenWork($entries);

        if ($openWork) {
            $status['has_open_entry'] = true;
            $status['open_type'] = 'work';
            $status['next_action'] = 'clock_out';

            if ($breakExpected && ! $this->hasBreakAfterEntry($entries, $openWork)) {
                $status['next_action'] = 'break_start';
            }

            return $status;
        }

        return $status;
    }

    private function buildShiftPayload(?Shift $shift, CarbonImmutable $today, string $timezone, ?TimeEntry $firstIn): array
    {
        $payload = [
            'start' => null,
            'end' => null,
            'is_within_shift_window' => null,
            'late' => null,
            'tolerance_minutes' => self::SHIFT_TOLERANCE_MINUTES,
        ];

        if (! $shift || ! $shift->start_time || ! $shift->end_time) {
            return $payload;
        }

        $shiftStart = $today->setTimeFromTimeString($shift->start_time);
        $shiftEnd = $today->setTimeFromTimeString($shift->end_time);
        $now = CarbonImmutable::now($timezone);
        $tolerance = self::SHIFT_TOLERANCE_MINUTES;
        $windowStart = $shiftStart->subMinutes($tolerance);
        $windowEnd = $shiftEnd->addMinutes($tolerance);

        $payload['start'] = $shift->start_time;
        $payload['end'] = $shift->end_time;
        $payload['is_within_shift_window'] = $now->between($windowStart, $windowEnd, true);

        if ($firstIn) {
            $firstInAt = $firstIn->clocked_at->timezone($timezone);
            $payload['late'] = $firstInAt->greaterThan($shiftStart->addMinutes($tolerance));
        }

        $payload['shift_days'] = $this->serializeShiftDays($shift->shiftDays);

        return $payload;
    }

    private function findOpenBreak(Collection $entries): ?TimeEntry
    {
        $pending = null;

        foreach ($entries as $entry) {
            if ($entry->type === 'break_start') {
                $pending = $entry;
            }

            if ($entry->type === 'break_end') {
                $pending = null;
            }
        }

        return $pending;
    }

    private function findOpenWork(Collection $entries): ?TimeEntry
    {
        $pending = null;

        foreach ($entries as $entry) {
            if ($entry->type === 'in') {
                $pending = $entry;
            }

            if ($entry->type === 'out' && $pending) {
                $pending = null;
            }
        }

        return $pending;
    }

    private function hasBreakAfterEntry(Collection $entries, TimeEntry $entry): bool
    {
        foreach ($entries as $current) {
            if ($current->clocked_at->lessThanOrEqualTo($entry->clocked_at)) {
                continue;
            }

            if ($current->type === 'break_start') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{weekday: int, is_working_day: bool, start_time: string|null, end_time: string|null, break_start_time: string|null, break_end_time: string|null}>
     */
    private function serializeShiftDays(Collection $days): array
    {
        return $days->map(fn ($day) => [
            'weekday' => (int) $day->weekday,
            'is_working_day' => (bool) $day->is_working_day,
            'start_time' => $day->start_time,
            'end_time' => $day->end_time,
            'break_start_time' => $day->break_start_time,
            'break_end_time' => $day->break_end_time,
        ])->toArray();
    }

    private function serializeAssignment(?UserShift $assignment): ?array
    {
        if (! $assignment) {
            return null;
        }

        return [
            'id' => $assignment->id,
            'shift_id' => $assignment->shift_id,
            'start_date' => $assignment->start_date?->toDateString(),
            'end_date' => $assignment->end_date?->toDateString(),
        ];
    }

    private function serializeEntry(TimeEntry $entry, string $timezone): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'clocked_at' => $entry->clocked_at->timezone($timezone)->toIso8601String(),
        ];
    }
}
