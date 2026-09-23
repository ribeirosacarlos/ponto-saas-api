<?php

namespace App\Services\TimeEntry;

use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\UserShiftResolver;
use App\Support\ShiftDayEventNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimeEntryDayNormalizer
{
    private const OPERATIONAL_MARGIN_HOURS = 4;

    public function __construct(
        private readonly UserShiftResolver $shiftResolver
    ) {}

    /**
     * @return array{base_date: string, timezone: string}
     */
    public function resolveOperationalDayKey(User $user, CarbonImmutable $reference): array
    {
        $context = $this->resolveOperationalContext($user, $reference);

        return [
            'base_date' => $context['base_date']->toDateString(),
            'timezone' => $context['timezone'],
        ];
    }

    public function normalizeForReference(User $user, CarbonImmutable $reference): void
    {
        $context = $this->resolveOperationalContext($user, $reference);
        $entries = $this->fetchEntries($user, $context['window_start'], $context['window_end']);

        DB::transaction(function () use ($entries, $context): void {
            foreach ($entries->values() as $index => $entry) {
                $normalized = $this->normalizedAttributesForIndex($context['expected_events'], $index);

                if (
                    $entry->type === $normalized['type']
                    && $entry->event_kind === $normalized['event_kind']
                ) {
                    continue;
                }

                $entry->forceFill($normalized)->saveQuietly();
            }
        });
    }

    /**
     * @return array{type: string, event_kind: string}
     */
    public function resolveAttributesForNewEntry(User $user, CarbonImmutable $reference): array
    {
        $context = $this->resolveOperationalContext($user, $reference);
        $entries = $this->fetchEntries($user, $context['window_start'], $context['window_end']);

        $priorCount = $entries
            ->filter(fn (TimeEntry $entry) => $entry->clocked_at->lessThanOrEqualTo($reference))
            ->count();

        return $this->normalizedAttributesForIndex($context['expected_events'], $priorCount);
    }

    /**
     * @return array{
     *   timezone: string,
     *   base_date: CarbonImmutable,
     *   window_start: CarbonImmutable,
     *   window_end: CarbonImmutable,
     *   expected_events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>
     * }
     */
    private function resolveOperationalContext(User $user, CarbonImmutable $reference): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone', 'UTC');
        $localReference = $reference->setTimezone($timezone);
        $resolved = $this->shiftResolver->resolve($user, $localReference);
        /** @var Shift|null $shift */
        $shift = $resolved['shift'];

        if (! $shift) {
            return $this->fallbackContext($timezone, $localReference);
        }

        $baseToday = $localReference->startOfDay();
        $contexts = [
            $this->buildContextForBaseDate($shift, $baseToday, $localReference),
            $this->buildContextForBaseDate($shift, $baseToday->subDay(), $localReference),
        ];

        $context = $this->selectContext($contexts, $localReference);

        if (
            ! $context
            || ! $context['shift_day']
            || ! $context['shift_day']->is_working_day
            || empty($context['events'])
        ) {
            return $this->fallbackContext($timezone, $localReference);
        }

        $windowStart = $context['window_start'];
        $windowEnd = $context['window_end'];

        if ($localReference->lessThan($windowStart) || $localReference->greaterThan($windowEnd)) {
            $windowStart = $windowStart->min($context['base_date']->startOfDay());
            $windowEnd = $windowEnd->max($context['base_date']->endOfDay());
        }

        return [
            'timezone' => $timezone,
            'base_date' => $context['base_date'],
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'expected_events' => $context['events'],
        ];
    }

    /**
     * @param  array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>  $expectedEvents
     * @return array{type: string, event_kind: string}
     */
    private function normalizedAttributesForIndex(array $expectedEvents, int $index): array
    {
        if (array_key_exists($index, $expectedEvents)) {
            return [
                'type' => $expectedEvents[$index]['expected_type'],
                'event_kind' => $expectedEvents[$index]['kind'],
            ];
        }

        $extraIndex = $index - count($expectedEvents);

        return [
            'type' => $extraIndex % 2 === 0 ? 'in' : 'out',
            'event_kind' => 'free',
        ];
    }

    private function fetchEntries(User $user, CarbonImmutable $windowStart, CarbonImmutable $windowEnd): Collection
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
     * @return array{timezone: string, base_date: CarbonImmutable, window_start: CarbonImmutable, window_end: CarbonImmutable, expected_events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>}
     */
    private function fallbackContext(string $timezone, CarbonImmutable $reference): array
    {
        return [
            'timezone' => $timezone,
            'base_date' => $reference->startOfDay(),
            'window_start' => $reference->startOfDay(),
            'window_end' => $reference->endOfDay(),
            'expected_events' => [],
        ];
    }

    /**
     * @return array{base_date: CarbonImmutable, shift_day: ?ShiftDay, events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>, window_start: CarbonImmutable, window_end: CarbonImmutable, within_window: bool}|null
     */
    private function selectContext(array $contexts, CarbonImmutable $reference): ?array
    {
        $active = collect($contexts)
            ->filter(fn (array $context) => $context['within_window'])
            ->sortByDesc(fn (array $context) => $context['window_end']->timestamp)
            ->values();

        if ($active->isNotEmpty()) {
            return $active->first();
        }

        $todayBase = $reference->startOfDay();
        $today = collect($contexts)->first(fn (array $context) => $context['base_date']->equalTo($todayBase));

        if ($today) {
            return $today;
        }

        return $contexts[0] ?? null;
    }

    /**
     * @return array{base_date: CarbonImmutable, shift_day: ?ShiftDay, events: array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>, window_start: CarbonImmutable, window_end: CarbonImmutable, within_window: bool}
     */
    private function buildContextForBaseDate(Shift $shift, CarbonImmutable $baseDate, CarbonImmutable $reference): array
    {
        /** @var ShiftDay|null $shiftDay */
        $shiftDay = $shift->shiftDays->firstWhere('weekday', $baseDate->isoWeekday());

        if (! $shiftDay) {
            return [
                'base_date' => $baseDate,
                'shift_day' => null,
                'events' => [],
                'window_start' => $baseDate->startOfDay(),
                'window_end' => $baseDate->endOfDay(),
                'within_window' => false,
            ];
        }

        $events = $this->buildExpectedEvents($shiftDay, $baseDate);

        if (empty($events)) {
            return [
                'base_date' => $baseDate,
                'shift_day' => $shiftDay,
                'events' => [],
                'window_start' => $baseDate->startOfDay(),
                'window_end' => $baseDate->endOfDay(),
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
            'within_window' => $reference->betweenIncluded($windowStart, $windowEnd),
        ];
    }

    /**
     * @return array<int, array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}>
     */
    private function buildExpectedEvents(ShiftDay $shiftDay, CarbonImmutable $baseDate): array
    {
        return array_map(function (array $event) use ($baseDate): array {
            $eventDate = $baseDate->addDays((int) $event['day_offset']);

            return [
                'kind' => $event['kind'],
                'expected_at' => $eventDate->setTimeFromTimeString($event['expected_time']),
                'expected_type' => $event['expected_type'],
                'day_offset' => (int) $event['day_offset'],
            ];
        }, ShiftDayEventNormalizer::fromShiftDay($shiftDay));
    }
}
