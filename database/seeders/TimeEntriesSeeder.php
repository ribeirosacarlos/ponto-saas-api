<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\UserShiftService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TimeEntriesSeeder extends Seeder
{
    private const NORMAL_VARIANCE_MINUTES = 10;
    private const LATE_PROBABILITY = 0.10;
    private const OVERTIME_PROBABILITY = 0.10;
    private const LATE_AND_OVERTIME_PROBABILITY = 0.03;
    private const INCOMPLETE_PROBABILITY = 0.04;

    private const LATITUDE_RANGE = ['min' => -23.65, 'max' => -23.55];
    private const LONGITUDE_RANGE = ['min' => -46.70, 'max' => -46.60];

    public function run(): void
    {
        $timezone = config('app.timezone') ?? 'UTC';
        $today = CarbonImmutable::now($timezone);
        $periodStart = $today->startOfMonth()->startOfDay();

        // Dica para validação: rode php artisan db:seed --class=TimeEntriesSeeder e use queries agrupadas por usuário/dia para confirmar atrasos e horas extras.

        $userShiftService = app(UserShiftService::class);

        $users = User::with(['userShifts' => function ($query) {
            $query->active()
                ->with('shift.shiftDays')
                ->orderByDesc('start_date');
        }])->get();

        foreach ($users as $user) {
            if (! $user->company_id) {
                Log::info('TimeEntriesSeeder: usuário sem empresa associada, pulando', ['user_id' => $user->id]);
                continue;
            }

            $shift = $this->resolveShiftForUser($user);

            if (! $shift) {
                Log::info('TimeEntriesSeeder: usuário sem jornada ativa, pulando', ['user_id' => $user->id]);
                $shift = $this->assignCompanyShift($user, $userShiftService);

                if (! $shift) {
                    continue;
                }
            }

            if (! $shift->start_time || ! $shift->end_time) {
                Log::info('TimeEntriesSeeder: jornada sem horários definidos, pulando', ['shift_id' => $shift->id]);
                continue;
            }

            TimeEntry::where('user_id', $user->id)
                ->whereBetween('clocked_at', [$periodStart->toDateTimeString(), $today->toDateTimeString()])
                ->delete();

            $entries = $this->buildEntriesForUser($user, $shift, $periodStart, $today, $timezone);

            if (! empty($entries)) {
                DB::table('time_entries')->insert($entries);
            }
        }

        // Dica de query: SELECT user_id, DATE_TRUNC('day', clocked_at) AS dia, COUNT(*) AS batidas FROM time_entries WHERE clocked_at BETWEEN '{$periodStart->toDateString()}' AND '{$today->toDateString()}' GROUP BY user_id, DATE_TRUNC('day', clocked_at');
    }

    private function resolveShiftForUser(User $user): ?Shift
    {
        $assignment = $user->userShifts->first();
        $shift = $assignment?->shift;

        if (! $shift && $user->company_id) {
            $shift = Shift::where('company_id', $user->company_id)
                ->where('is_default', true)
                ->with('shiftDays')
                ->first();
        }

        $shift?->loadMissing('shiftDays');

        return $shift;
    }

    private function assignCompanyShift(User $user, UserShiftService $userShiftService): ?Shift
    {
        if (! $user->company_id) {
            return null;
        }

        $shift = Shift::where('company_id', $user->company_id)
            ->with('shiftDays')
            ->orderByDesc('is_default')
            ->first();

        if (! $shift) {
            return null;
        }

        $userShiftService->assign($user, $shift);

        return $shift;
    }

    private function buildEntriesForUser(User $user, Shift $shift, CarbonImmutable $start, CarbonImmutable $end, string $timezone): array
    {
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

            $entryTime = $this->calculateTime($current, $shift->start_time, $isLate, false);
            $entries[] = $this->buildPayload($user, $entryTime, 'in', $timezone);

            if (! $hasIncomplete) {
                $exitTime = $this->calculateTime($current, $shift->end_time, $isOvertime, true, $entryTime);
                $entries[] = $this->buildPayload($user, $exitTime, 'out', $timezone);
            }

            $current = $current->addDay();
        }

        return $entries;
    }

    private function calculateTime(CarbonImmutable $day, string $baseTime, bool $shouldDelay, bool $isExit, ?CarbonImmutable $entryTime = null): CarbonImmutable
    {
        $timestamp = $day->setTimeFromTimeString($baseTime);

        if ($shouldDelay) {
            $delay = $isExit ? random_int(30, 120) : random_int(15, 60);
            $timestamp = $timestamp->addMinutes($delay);
        } else {
            $variance = random_int(-self::NORMAL_VARIANCE_MINUTES, self::NORMAL_VARIANCE_MINUTES);
            $timestamp = $timestamp->addMinutes($variance);
        }

        if ($isExit && $entryTime && $timestamp->lessThanOrEqualTo($entryTime)) {
            $timestamp = $entryTime->addMinute();
        }

        return $timestamp;
    }

    private function buildPayload(User $user, CarbonImmutable $clockedAt, string $type, string $timezone): array
    {
        $location = $this->randomLocation();
        $now = CarbonImmutable::now($timezone)->toDateTimeString();

        return [
            'id' => (string) Str::uuid(),
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => $clockedAt->timezone($timezone)->format('Y-m-d H:i:s'),
            'type' => $type,
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
