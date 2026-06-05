<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Services\UserShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TimeEntriesMarch2026Seeder extends Seeder
{
    private const NORMAL_VARIANCE_MINUTES = 10;

    private const LATE_PROBABILITY = 0.10;

    private const OVERTIME_PROBABILITY = 0.10;

    private const LATE_AND_OVERTIME_PROBABILITY = 0.03;

    private const INCOMPLETE_PROBABILITY = 0.04;

    private const BREAK_DURATION_MINUTES = 60;

    private const LATITUDE_RANGE = ['min' => -23.65, 'max' => -23.55];

    private const LONGITUDE_RANGE = ['min' => -46.70, 'max' => -46.60];

    private const PERIOD_START = '2026-02-01';

    private const PERIOD_END = '2026-02-28';

    public function run(): void
    {
        $timezone = config('app.timezone') ?? 'UTC';
        $periodStart = CarbonImmutable::parse(self::PERIOD_START, $timezone)->startOfDay();
        $periodEnd = CarbonImmutable::parse(self::PERIOD_END, $timezone)->endOfDay();

        $userShiftService = app(UserShiftService::class);

        $users = User::with(['userShifts' => function ($query) use ($periodStart) {
            $query->where('start_date', '<=', $periodStart->toDateString())
                ->where(function ($q) use ($periodStart) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $periodStart->toDateString());
                })
                ->with('shift.shiftDays')
                ->orderByDesc('start_date');
        }])->get();

        foreach ($users as $user) {
            if (! $user->company_id) {
                Log::info('TimeEntriesMarch2026Seeder: usuario sem empresa, pulando', ['user_id' => $user->id]);

                continue;
            }

            $userShift = $user->userShifts->first();
            $shift = $userShift?->shift;

            if (! $shift) {
                $shift = $this->fallbackShift($user, $userShiftService);
            }

            if (! $shift) {
                Log::info('TimeEntriesMarch2026Seeder: sem jornada, pulando', ['user_id' => $user->id]);

                continue;
            }

            if (! $shift->start_time || ! $shift->end_time) {
                Log::info('TimeEntriesMarch2026Seeder: jornada sem horarios, pulando', ['shift_id' => $shift->id]);

                continue;
            }

            $shift->loadMissing('shiftDays');

            TimeEntry::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->whereBetween('clocked_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
                ->delete();

            $entries = $this->buildEntriesForUser($user, $shift, $userShift, $periodStart, $periodEnd, $timezone);

            if (! empty($entries)) {
                foreach (array_chunk($entries, 500) as $chunk) {
                    DB::table('time_entries')->insert($chunk);
                }
            }
        }
    }

    private function fallbackShift(User $user, UserShiftService $userShiftService): ?Shift
    {
        $shift = Shift::where('company_id', $user->company_id)
            ->with('shiftDays')
            ->orderByDesc('is_default')
            ->first();

        if ($shift) {
            $userShiftService->assign($user, $shift);
        }

        return $shift;
    }

    private function buildEntriesForUser(
        User $user,
        Shift $shift,
        ?UserShift $userShift,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $timezone
    ): array {
        $entries = [];
        $current = $start;

        while ($current->lessThanOrEqualTo($end)) {
            if (! $this->isWorkingDay($shift, $current)) {
                $current = $current->addDay();

                continue;
            }

            $hasIncomplete = $this->chance(self::INCOMPLETE_PROBABILITY);
            $isLate = false;
            $isOvertime = false;

            if ($this->chance(self::LATE_AND_OVERTIME_PROBABILITY)) {
                $isLate = true;
                $isOvertime = true;
            } else {
                $isLate = $this->chance(self::LATE_PROBABILITY);
                $isOvertime = $this->chance(self::OVERTIME_PROBABILITY);
            }

            if ($hasIncomplete) {
                $isOvertime = false;
            }

            $workStart = $this->calculateTime($current, $shift->start_time, $isLate, false);
            $entries[] = $this->buildPayload($user, $userShift, $workStart, 'in', 'work_start', $timezone);

            if (! $hasIncomplete) {
                $shiftMinutes = $this->shiftDurationMinutes($shift);
                $breakOffset = (int) ($shiftMinutes / 2);
                $breakStart = $workStart->addMinutes($breakOffset);
                $breakEnd = $breakStart->addMinutes(self::BREAK_DURATION_MINUTES);
                $workEnd = $this->calculateTime($current, $shift->end_time, $isOvertime, true, $breakEnd);

                $entries[] = $this->buildPayload($user, $userShift, $breakStart, 'out', 'break_start', $timezone);
                $entries[] = $this->buildPayload($user, $userShift, $breakEnd, 'in', 'break_end', $timezone);
                $entries[] = $this->buildPayload($user, $userShift, $workEnd, 'out', 'work_end', $timezone);
            }

            $current = $current->addDay();
        }

        return $entries;
    }

    private function calculateTime(
        CarbonImmutable $day,
        string $baseTime,
        bool $shouldDelay,
        bool $isExit,
        ?CarbonImmutable $after = null
    ): CarbonImmutable {
        $timestamp = $day->setTimeFromTimeString($baseTime);

        if ($shouldDelay) {
            $delay = $isExit ? random_int(30, 120) : random_int(15, 60);
            $timestamp = $timestamp->addMinutes($delay);
        } else {
            $variance = random_int(-self::NORMAL_VARIANCE_MINUTES, self::NORMAL_VARIANCE_MINUTES);
            $timestamp = $timestamp->addMinutes($variance);
        }

        if ($after && $timestamp->lessThanOrEqualTo($after)) {
            $timestamp = $after->addMinute();
        }

        return $timestamp;
    }

    private function shiftDurationMinutes(Shift $shift): int
    {
        $start = CarbonImmutable::createFromTimeString($shift->start_time);
        $end = CarbonImmutable::createFromTimeString($shift->end_time);
        $minutes = $start->diffInMinutes($end);

        return $minutes > 0 ? (int) $minutes : 480;
    }

    private function buildPayload(
        User $user,
        ?UserShift $userShift,
        CarbonImmutable $clockedAt,
        string $type,
        string $eventKind,
        string $timezone
    ): array {
        $location = $this->randomLocation();
        $now = CarbonImmutable::now($timezone)->toDateTimeString();

        return [
            'id' => (string) Str::uuid(),
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $userShift?->id,
            'clocked_at' => $clockedAt->timezone($timezone)->format('Y-m-d H:i:s'),
            'type' => $type,
            'event_kind' => $eventKind,
            'source' => 'seed',
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function randomLocation(): array
    {
        return [
            'latitude' => number_format($this->randomFloat(self::LATITUDE_RANGE['min'], self::LATITUDE_RANGE['max']), 6, '.', ''),
            'longitude' => number_format($this->randomFloat(self::LONGITUDE_RANGE['min'], self::LONGITUDE_RANGE['max']), 6, '.', ''),
        ];
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + (lcg_value() * ($max - $min));
    }

    private function isWorkingDay(Shift $shift, CarbonImmutable $date): bool
    {
        $weekday = $date->isoWeekday();
        $definition = $shift->shiftDays->firstWhere('weekday', $weekday);

        if ($definition) {
            return (bool) $definition->is_working_day;
        }

        return ! in_array($weekday, [6, 7], true);
    }

    private function chance(float $probability): bool
    {
        if ($probability <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $probability;
    }
}
