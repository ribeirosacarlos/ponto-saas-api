<?php

namespace App\Http\Controllers\Api\Employee;

use App\Actions\TimeEntries\CreateTimeEntryAdjustmentAction;
use App\Actions\TimeEntries\ResolveNextExpectedClockAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeTimeEntryHistoryRequest;
use App\Http\Requests\TimeEntryStoreRequest;
use App\Models\TimeEntry;
use App\Models\VacationDay;
use App\Services\TimeEntry\OvertimeCalculatorService;
use App\Services\UserShiftResolver;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function clock(
        TimeEntryStoreRequest $request,
        ResolveNextExpectedClockAction $resolveNextExpectedClock,
        CreateTimeEntryAdjustmentAction $createAdjustment
    ) {
        $this->authorize('create', TimeEntry::class);

        $validated = $request->validated();
        $user = $request->user();
        $timezone = $this->resolveCompanyTimezone($user);
        $now = CarbonImmutable::now($timezone);

        $isOnVacation = VacationDay::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $now->toDateString())
            ->exists();

        if ($isOnVacation) {
            return response()->json([
                'message' => 'Voce esta de ferias e nao pode registrar ponto neste dia.',
            ], 422);
        }

        $lastEntry = $user->timeEntries()->excludeRejected()->latest('clocked_at')->first();
        if ($lastEntry && $lastEntry->clocked_at->diffInSeconds($now) < 60) {
            return response()->json(['message' => 'Aguarde 1 minuto entre os registros.'], 422);
        }

        $resolved = $resolveNextExpectedClock->handle($user, $now);
        $nextEvent = $resolved['next_event'];

        if ($resolved['is_outside_shift'] || ! $resolved['is_working_day'] || ! $resolved['shift_day']) {
            $adjustment = $createAdjustment->handle($user, $user, [
                'clocked_at' => $now,
                'reason' => 'Fora do turno/jornada (dia nao trabalhado ou sem jornada).',
                'source' => $validated['source'] ?? 'web',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'resolved_type' => $nextEvent['expected_type'] ?? null,
                'event_kind' => $nextEvent['kind'] ?? 'free',
                'user_shift_id' => $resolved['assignment']?->id,
            ]);

            return response()->json([
                'message' => 'Fora da jornada prevista. Solicitacao de ajuste criada.',
                'status' => 'adjustment_requested',
                'adjustment' => $adjustment,
            ], 202);
        }

        if (! $nextEvent) {
            $adjustment = $createAdjustment->handle($user, $user, [
                'clocked_at' => $now,
                'reason' => 'Dia ja completo; batida extra requer ajuste.',
                'source' => $validated['source'] ?? 'web',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'event_kind' => 'free',
                'user_shift_id' => $resolved['assignment']?->id,
            ]);

            return response()->json([
                'message' => 'Dia ja completo. Solicitacao de ajuste criada.',
                'status' => 'adjustment_requested',
                'adjustment' => $adjustment,
            ], 202);
        }

        $entry = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $resolved['assignment']?->id,
            'clocked_at' => $now,
            'type' => $nextEvent['expected_type'],
            'event_kind' => $nextEvent['kind'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'source' => $validated['source'] ?? 'web',
        ]);

        $nextResolved = $resolveNextExpectedClock->handle($user, $now);

        return response()->json([
            'entry' => $entry,
            'next_event' => $this->serializeEvent($nextResolved['next_event']),
        ], 201);
    }

    public function myEntries(Request $request)
    {
        $entries = $request->user()
            ->timeEntries()
            ->excludeRejected()
            ->orderBy('clocked_at', 'desc')
            ->paginate(20);

        foreach ($entries as $entry) {
            $this->authorize('view', $entry);
        }

        return response()->json($entries);
    }

    public function history(
        EmployeeTimeEntryHistoryRequest $request,
        OvertimeCalculatorService $overtimeCalculator
    ) {
        $user = $request->user();
        $timezone = CompanyTime::companyTz($request);
        $limit = (int) ($request->input('limit', 7));
        $limit = max(1, min($limit, 60));

        $today = CarbonImmutable::now($timezone)->startOfDay();
        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        $from = $fromInput ? CarbonImmutable::parse($fromInput, $timezone)->startOfDay() : null;
        $to = $toInput ? CarbonImmutable::parse($toInput, $timezone)->startOfDay() : null;

        if ($from && ! $to) {
            $to = $from;
        }

        if ($to && ! $from) {
            $from = $to->subDays($limit - 1);
        }

        if (! $from && ! $to) {
            $to = $today;
            $from = $today->subDays($limit - 1);
        }

        $fromLocal = $from->startOfDay();
        $toLocal = $to->endOfDay();

        $overtime = $overtimeCalculator->calculateForEmployee($user, $fromLocal, $toLocal, true);
        $daySummaries = collect($overtime['days'] ?? [])->keyBy('date');

        $fromUtc = $fromLocal->setTimezone('UTC');
        $toUtc = $toLocal->setTimezone('UTC');

        $entries = $user->timeEntries()
            ->excludeRejected()
            ->whereBetween('clocked_at', [$fromUtc->toDateTimeString(), $toUtc->toDateTimeString()])
            ->orderBy('clocked_at')
            ->get();

        $entriesByDate = [];

        foreach ($entries as $entry) {
            if (! in_array($entry->type, ['in', 'out'], true)) {
                continue;
            }

            $local = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);
            $date = $local->toDateString();

            $entriesByDate[$date][] = [
                'type' => $entry->type,
                'time' => $local,
            ];
        }

        foreach ($entriesByDate as &$items) {
            usort($items, fn ($a, $b) => $a['time']->lessThan($b['time']) ? -1 : 1);
        }

        $days = [];
        $cursor = $fromLocal->startOfDay();
        $end = $toLocal->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $date = $cursor->toDateString();
            $items = $entriesByDate[$date] ?? [];

            $firstIn = null;
            $lastOut = null;
            $entryCount = 0;

            foreach ($items as $item) {
                $entryCount++;

                if ($item['type'] === 'in' && ! $firstIn) {
                    $firstIn = $item['time'];
                }

                if ($item['type'] === 'out') {
                    $lastOut = $item['time'];
                }
            }

            $summary = $daySummaries->get($date);

            $days[] = [
                'date' => $date,
                'first_in' => $firstIn?->toIso8601String(),
                'last_out' => $lastOut?->toIso8601String(),
                'worked_hhmm' => $summary['worked_hhmm'] ?? '00:00',
                'expected_hhmm' => $summary['expected_hhmm'] ?? '00:00',
                'balance_hhmm' => $summary['balance_hhmm'] ?? '00:00',
                'status' => $summary['status'] ?? 'even',
                'open_day' => $entryCount % 2 !== 0,
            ];

            $cursor = $cursor->addDay();
        }

        return response()->json([
            'from' => $fromLocal->toDateString(),
            'to' => $toLocal->toDateString(),
            'timezone' => $timezone,
            'days' => $days,
        ]);
    }

    public function openStatus(Request $request, ResolveNextExpectedClockAction $resolveNextExpectedClock)
    {
        $status = $resolveNextExpectedClock->handle($request->user());
        $openStatus = $status['open_status'];

        return response()->json([
            'open' => $openStatus['open'],
            'open_reason' => $openStatus['open_reason'],
            'expected_next_out_at' => $openStatus['expected_next_out_at']?->toIso8601String(),
            'last_in_at' => $openStatus['last_in_at']?->toIso8601String(),
            'shift_day' => $status['shift_day'] ? [
                'weekday' => (int) $status['shift_day']->weekday,
                'is_working_day' => (bool) $status['shift_day']->is_working_day,
            ] : null,
            'assignment_id' => $status['assignment']?->id,
            'next_event' => $this->serializeEvent($status['next_event']),
            'is_outside_shift' => $status['is_outside_shift'],
        ]);
    }

    public function shift(Request $request, UserShiftResolver $shiftResolver)
    {
        $result = $shiftResolver->resolve($request->user());
        $shift = $result['shift'];
        $assignment = $result['assignment'];

        if (! $shift) {
            return response()->json([
                'shift' => null,
                'assignment' => null,
            ]);
        }

        $shiftDays = collect($shift->shiftDays)
            ->sortBy('weekday')
            ->values()
            ->map(fn ($day) => [
                'id' => $day->id,
                'weekday' => (int) $day->weekday,
                'is_working_day' => (bool) $day->is_working_day,
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
                'break_start_time' => $day->break_start_time,
                'break_end_time' => $day->break_end_time,
                'break_minutes' => $day->break_minutes,
                'events' => $day->events->map(fn ($event) => [
                    'kind' => $event->kind,
                    'expected_time' => $event->expected_time,
                    'day_offset' => (int) $event->day_offset,
                    'expected_type' => $event->expected_type,
                    'sort_order' => (int) $event->sort_order,
                ])->values()->all(),
            ]);

        return response()->json([
            'shift' => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'is_flexible' => $shift->is_flexible,
                'is_default' => $shift->is_default,
                'shift_days' => $shiftDays,
            ],
            'assignment' => $assignment ? [
                'id' => $assignment->id,
                'start_date' => $assignment->start_date?->toDateString(),
                'end_date' => $assignment->end_date?->toDateString(),
            ] : null,
        ]);
    }

    /**
     * @param  ?array{kind: string, expected_at: CarbonImmutable, expected_type: string, day_offset: int}  $event
     */
    private function serializeEvent(?array $event): ?array
    {
        if (! $event) {
            return null;
        }

        return [
            'kind' => $event['kind'],
            'expected_type' => $event['expected_type'],
            'expected_at' => $event['expected_at']->toIso8601String(),
            'day_offset' => (int) $event['day_offset'],
        ];
    }

    private function resolveCompanyTimezone($user): string
    {
        return $user->company?->timezone ?: config('app.timezone', 'UTC');
    }
}
