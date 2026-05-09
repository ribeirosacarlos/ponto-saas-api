<?php

namespace App\Actions\TimeEntries;

use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Services\UserShiftResolver;
use App\Support\ShiftDayEventNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ResolveNextExpectedClockAction
{
    private const OPERATIONAL_MARGIN_HOURS = 4;

    private const OPEN_WINDOW_MINUTES = 30;

    public function __construct(
        private readonly UserShiftResolver $shiftResolver
    ) {}

    /**
     * @return array{
     *   timezone: string,
     *   now: CarbonImmutable,
     *   shift: ?Shift,
     *   assignment: ?UserShift,
     *   shift_day: ?ShiftDay,
     *   expected_events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>,
     *   completed: array<int, array{kind: string, time_entry_id: string, clocked_at: CarbonImmutable, type: string}>,
     *   next_event: ?array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int},
     *   is_working_day: bool,
     *   is_outside_shift: bool,
     *   is_holiday: bool,
     *   holiday_name: ?string,
     *   open_status: array{open: bool, open_reason: ?string, open_since_expected_at: ?CarbonImmutable, expected_next_out_at: ?CarbonImmutable, last_in_at: ?CarbonImmutable}
     * }
     */
    public function handle(User $user, ?CarbonImmutable $now = null): array
    {
        $timezone = $this->resolveCompanyTimezone($user);
        $nowLocal = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);

        $resolved = $this->shiftResolver->resolve($user);
        /** @var Shift|null $shift */
        $shift = $resolved['shift'];
        /** @var UserShift|null $assignment */
        $assignment = $resolved['assignment'];

        $holiday = Holiday::where('company_id', $user->company_id)
            ->whereDate('date', $nowLocal->toDateString())
            ->first();

        if ($holiday) {
            return $this->basePayload($timezone, $nowLocal, $shift, $assignment, null, [], [], null, false, true, true, $holiday->name);
        }

        if (! $shift) {
            return $this->basePayload($timezone, $nowLocal, $shift, $assignment, null, [], [], null, false, true);
        }

        $contexts = $this->buildCandidateContexts($shift, $nowLocal);
        $context = $this->selectContext($contexts, $nowLocal);

        if (! $context || ! $context['shift_day']) {
            return $this->basePayload($timezone, $nowLocal, $shift, $assignment, null, [], [], null, false, true);
        }

        /** @var ShiftDay $shiftDay */
        $shiftDay = $context['shift_day'];
        /** @var array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}> $expectedEvents */
        $expectedEvents = $context['events'];

        $isWorkingDay = (bool) $shiftDay->is_working_day;
        if (! $isWorkingDay || empty($expectedEvents)) {
            return $this->basePayload($timezone, $nowLocal, $shift, $assignment, $shiftDay, [], [], null, false, true);
        }

        $entries = $this->fetchOperationalEntries($user, $context['window_start'], $context['window_end']);
        [$completed, $nextEvent] = $this->matchEntriesToExpectedEvents($entries, $expectedEvents, $timezone);

        $isOutsideShift = ! $context['within_window'];

        return $this->basePayload(
            $timezone,
            $nowLocal,
            $shift,
            $assignment,
            $shiftDay,
            $expectedEvents,
            $completed,
            $nextEvent,
            $isWorkingDay,
            $isOutsideShift
        );
    }

    /**
     * @param  array<int, array{base_date: CarbonImmutable, shift_day: ?ShiftDay, events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>, window_start: ?CarbonImmutable, window_end: ?CarbonImmutable, within_window: bool}>  $contexts
     * @return array{base_date: CarbonImmutable, shift_day: ?ShiftDay, events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>, window_start: ?CarbonImmutable, window_end: ?CarbonImmutable, within_window: bool}|null
     */
    private function selectContext(array $contexts, CarbonImmutable $now): ?array
    {
        $active = collect($contexts)
            ->filter(fn (array $context) => $context['within_window'])
            ->sortByDesc(fn (array $context) => $context['window_end']?->timestamp ?? 0)
            ->values();

        if ($active->isNotEmpty()) {
            return $active->first();
        }

        $todayBase = $now->startOfDay();

        $today = collect($contexts)->first(fn (array $context) => $context['base_date']->equalTo($todayBase));
        if ($today) {
            return $today;
        }

        return $contexts[0] ?? null;
    }

    /**
     * @return array<int, array{base_date: CarbonImmutable, shift_day: ?ShiftDay, events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>, window_start: ?CarbonImmutable, window_end: ?CarbonImmutable, within_window: bool}>
     */
    private function buildCandidateContexts(Shift $shift, CarbonImmutable $now): array
    {
        $baseToday = $now->startOfDay();

        return [
            $this->buildContextForBaseDate($shift, $baseToday, $now),
            $this->buildContextForBaseDate($shift, $baseToday->subDay(), $now),
        ];
    }

    /**
     * @return array{base_date: CarbonImmutable, shift_day: ?ShiftDay, events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>, window_start: ?CarbonImmutable, window_end: ?CarbonImmutable, within_window: bool}
     */
    private function buildContextForBaseDate(Shift $shift, CarbonImmutable $baseDate, CarbonImmutable $now): array
    {
        /** @var ShiftDay|null $shiftDay */
        $shiftDay = $shift->shiftDays->firstWhere('weekday', $baseDate->isoWeekday());

        if (! $shiftDay) {
            return [
                'base_date' => $baseDate,
                'shift_day' => null,
                'events' => [],
                'window_start' => null,
                'window_end' => null,
                'within_window' => false,
            ];
        }

        $events = $this->buildExpectedEvents($shiftDay, $baseDate);

        if (empty($events)) {
            return [
                'base_date' => $baseDate,
                'shift_day' => $shiftDay,
                'events' => [],
                'window_start' => null,
                'window_end' => null,
                'within_window' => false,
            ];
        }

        $firstAt = $events[0]['expected_at'];
        $lastAt = $events[array_key_last($events)]['expected_at'];
        $windowStart = $firstAt->subHours(self::OPERATIONAL_MARGIN_HOURS);
        $windowEnd = $lastAt->addHours(self::OPERATIONAL_MARGIN_HOURS);

        return [
            'base_date' => $baseDate,
            'shift_day' => $shiftDay,
            'events' => $events,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'within_window' => $now->betweenIncluded($windowStart, $windowEnd),
        ];
    }

    /**
     * @return array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>
     */
    private function buildExpectedEvents(ShiftDay $shiftDay, CarbonImmutable $baseDate): array
    {
        $normalized = ShiftDayEventNormalizer::fromShiftDay($shiftDay);

        return array_map(function (array $event) use ($baseDate): array {
            $eventDate = $baseDate->addDays((int) $event['day_offset']);
            $expectedAt = $eventDate->setTimeFromTimeString($event['expected_time']);

            return [
                'kind' => $event['kind'],
                'expected_at' => $expectedAt,
                'expected_type' => $event['expected_type'],
                'day_offset' => (int) $event['day_offset'],
            ];
        }, $normalized);
    }

    private function fetchOperationalEntries(User $user, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): Collection
    {
        return TimeEntry::query()
            ->excludeRejected()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereBetween('clocked_at', [$windowStart->toDateTimeString(), $windowEnd->toDateTimeString()])
            ->orderBy('clocked_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @param  array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>  $expectedEvents
     * @return array{0: array<int, array{kind: string, time_entry_id: string, clocked_at: CarbonImmutable, type: string}>, 1: ?array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}}
     */
    private function matchEntriesToExpectedEvents(Collection $entries, array $expectedEvents, string $timezone): array
    {
        $completed = [];
        $entryList = $entries->values();
        $entryCount = $entryList->count();
        $entryIndex = 0;

        foreach ($expectedEvents as $position => $expectedEvent) {
            $matched = null;

            while ($entryIndex < $entryCount) {
                /** @var TimeEntry $entry */
                $entry = $entryList[$entryIndex];
                $entryIndex++;

                if (! in_array($entry->type, ['in', 'out'], true)) {
                    continue;
                }

                if ($entry->type !== $expectedEvent['expected_type']) {
                    continue;
                }

                $matched = $entry;
                break;
            }

            if (! $matched) {
                return [$completed, $expectedEvents[$position]];
            }

            $completed[] = [
                'kind' => $expectedEvent['kind'],
                'time_entry_id' => $matched->id,
                'clocked_at' => CarbonImmutable::instance($matched->clocked_at)->setTimezone($timezone),
                'type' => $matched->type,
            ];
        }

        return [$completed, null];
    }

    /**
     * @param  array<int, array{kind: string, time_entry_id: string, clocked_at: CarbonImmutable, type: string}>  $completed
     * @param  ?array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}  $nextEvent
     * @return array{open: bool, open_reason: ?string, open_since_expected_at: ?CarbonImmutable, expected_next_out_at: ?CarbonImmutable, last_in_at: ?CarbonImmutable}
     */
    private function buildOpenStatus(CarbonImmutable $now, array $completed, ?array $nextEvent): array
    {
        $lastCompleted = empty($completed) ? null : $completed[array_key_last($completed)];

        $lastInAt = null;
        if ($lastCompleted && $lastCompleted['type'] === 'in') {
            $lastInAt = $lastCompleted['clocked_at'];
        }

        if (! $lastCompleted || ! $nextEvent) {
            return [
                'open' => false,
                'open_reason' => null,
                'open_since_expected_at' => null,
                'expected_next_out_at' => $nextEvent && $nextEvent['expected_type'] === 'out' ? $nextEvent['expected_at'] : null,
                'last_in_at' => $lastInAt,
            ];
        }

        if ($lastCompleted['type'] !== 'in' || $nextEvent['expected_type'] !== 'out') {
            return [
                'open' => false,
                'open_reason' => null,
                'open_since_expected_at' => null,
                'expected_next_out_at' => $nextEvent['expected_type'] === 'out' ? $nextEvent['expected_at'] : null,
                'last_in_at' => $lastInAt,
            ];
        }

        $openThreshold = $nextEvent['expected_at']->addMinutes(self::OPEN_WINDOW_MINUTES);
        $isOpen = $now->greaterThanOrEqualTo($openThreshold);

        return [
            'open' => $isOpen,
            'open_reason' => $isOpen ? 'Expected out event overdue by at least 30 minutes.' : null,
            'open_since_expected_at' => $isOpen ? $nextEvent['expected_at'] : null,
            'expected_next_out_at' => $nextEvent['expected_at'],
            'last_in_at' => $lastInAt,
        ];
    }

    /**
     * @param  array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>  $expectedEvents
     * @param  array<int, array{kind: string, time_entry_id: string, clocked_at: CarbonImmutable, type: string}>  $completed
     * @param  ?array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}  $nextEvent
     * @return array{
     *   timezone: string,
     *   now: CarbonImmutable,
     *   shift: ?Shift,
     *   assignment: ?UserShift,
     *   shift_day: ?ShiftDay,
     *   expected_events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>,
     *   completed: array<int, array{kind: string, time_entry_id: string, clocked_at: CarbonImmutable, type: string}>,
     *   next_event: ?array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int},
     *   is_working_day: bool,
     *   is_outside_shift: bool,
     *   is_holiday: bool,
     *   holiday_name: ?string,
     *   open_status: array{open: bool, open_reason: ?string, open_since_expected_at: ?CarbonImmutable, expected_next_out_at: ?CarbonImmutable, last_in_at: ?CarbonImmutable}
     * }
     */
    private function basePayload(
        string $timezone,
        CarbonImmutable $now,
        ?Shift $shift,
        ?UserShift $assignment,
        ?ShiftDay $shiftDay,
        array $expectedEvents,
        array $completed,
        ?array $nextEvent,
        bool $isWorkingDay,
        bool $isOutsideShift,
        bool $isHoliday = false,
        ?string $holidayName = null
    ): array {
        return [
            'timezone' => $timezone,
            'now' => $now,
            'shift' => $shift,
            'assignment' => $assignment,
            'shift_day' => $shiftDay,
            'expected_events' => $expectedEvents,
            'completed' => $completed,
            'next_event' => $nextEvent,
            'is_working_day' => $isWorkingDay,
            'is_outside_shift' => $isOutsideShift,
            'is_holiday' => $isHoliday,
            'holiday_name' => $holidayName,
            'open_status' => $this->buildOpenStatus($now, $completed, $nextEvent),
        ];
    }

    private function resolveCompanyTimezone(User $user): string
    {
        return $user->company?->timezone ?: config('app.timezone', 'UTC');
    }
}
