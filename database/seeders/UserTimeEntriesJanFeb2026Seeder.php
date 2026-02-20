<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserTimeEntriesJanFeb2026Seeder extends Seeder
{
    private const USER_ID = '49f2abc1-a356-4e5f-bc32-29b5799ccc82';
    private const COMPANY_ID = '019ba8e5-2ec4-702e-9122-113347970f2f';

    private const TIMEZONE = 'America/Sao_Paulo';

    // 2026-01-01 to 2026-02-28 (inclusive)
    private const START_DATE = '2026-01-01';
    private const END_DATE = '2026-02-28';

    // Target: keep positive overtime (>= 8h10 net worked)
    private const MIN_NET_WORK_MINUTES = 490;

    public function run(): void
    {
        mt_srand(20260220);

        $start = CarbonImmutable::parse(self::START_DATE, self::TIMEZONE)->startOfDay();
        $end = CarbonImmutable::parse(self::END_DATE, self::TIMEZONE)->endOfDay();

        DB::table('time_entries')
            ->where('company_id', self::COMPANY_ID)
            ->where('user_id', self::USER_ID)
            ->whereBetween('clocked_at', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->delete();

        $rows = [];
        $now = CarbonImmutable::now(self::TIMEZONE)->format('Y-m-d H:i:s');

        for ($day = $start->startOfDay(); $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            [$workIn, $breakOut, $breakIn, $workOut] = $this->generateDayPunches($day);

            $rows[] = $this->payload($workIn, 'in', 'work_start', $now);
            $rows[] = $this->payload($breakOut, 'out', 'break_start', $now);
            $rows[] = $this->payload($breakIn, 'in', 'break_end', $now);
            $rows[] = $this->payload($workOut, 'out', 'work_end', $now);
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('time_entries')->insert($chunk);
        }
    }

    /**
     * Return 4 punches in the order IN/OUT/IN/OUT
     * within requested windows and with positive overtime.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function generateDayPunches(CarbonImmutable $day): array
    {
        // Heavier delay days for variation.
        $highDelayDay = in_array($day->dayOfWeekIso, [2, 4], true); // Tue/Thu

        for ($tries = 0; $tries < 100; $tries++) {
            $workIn = $this->pickTime($day, '08:00', '09:30', $highDelayDay ? 65 : 35, $highDelayDay ? 95 : 65);
            $breakOut = $this->pickTime($day, '12:00', '13:00', 0, 60);
            $breakIn = $this->pickTime($day, '13:00', '14:00', 0, 60);
            $workOut = $this->pickTime($day, '17:40', '18:30', 20, 50);

            // Ensure valid chronological order.
            if ($breakOut->lessThanOrEqualTo($workIn->addHours(2))) {
                continue;
            }

            if ($breakIn->lessThanOrEqualTo($breakOut)) {
                continue;
            }

            if ($workOut->lessThanOrEqualTo($breakIn->addHours(2))) {
                continue;
            }

            $workedMinutes = $workIn->diffInMinutes($breakOut) + $breakIn->diffInMinutes($workOut);

            if ($workedMinutes < self::MIN_NET_WORK_MINUTES) {
                continue;
            }

            return [$workIn, $breakOut, $breakIn, $workOut];
        }

        // Safe fallback with delay and positive overtime.
        return [
            $day->setTime(9, 5),
            $day->setTime(12, 45),
            $day->setTime(13, 15),
            $day->setTime(18, 20),
        ];
    }

    private function pickTime(
        CarbonImmutable $day,
        string $rangeStart,
        string $rangeEnd,
        int $softStartMinute,
        int $softEndMinute
    ): CarbonImmutable {
        $base = $day->setTimeFromTimeString($rangeStart);
        $rangeMinutes = $base->diffInMinutes($day->setTimeFromTimeString($rangeEnd));

        $start = max(0, min($rangeMinutes, $softStartMinute));
        $end = max($start, min($rangeMinutes, $softEndMinute));

        $offset = random_int($start, $end);

        return $base->addMinutes($offset);
    }

    private function payload(CarbonImmutable $clockedAt, string $type, string $eventKind, string $now): array
    {
        return [
            'id' => (string) Str::uuid(),
            'company_id' => self::COMPANY_ID,
            'user_id' => self::USER_ID,
            'clocked_at' => $clockedAt->format('Y-m-d H:i:s'),
            'type' => $type,
            'event_kind' => $eventKind,
            'source' => 'seed',
            'latitude' => '-23.561234',
            'longitude' => '-46.655432',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}