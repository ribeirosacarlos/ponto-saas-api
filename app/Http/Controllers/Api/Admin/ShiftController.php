<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserVisibilityService;
use App\Support\ShiftDayEventNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService,
        protected AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request)
    {
        return Shift::with([
            'shiftDays' => function ($query) {
                $query->orderBy('weekday');
            },
            'shiftDays.events' => function ($query) {
                $query->orderBy('sort_order');
            },
        ])
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));
    }

    public function store(StoreShiftRequest $request)
    {
        $data = $request->validated();
        $companyId = $request->user()->company_id;

        try {
            $shift = DB::transaction(function () use ($data, $companyId) {
                $times = $this->resolveShiftTimes($data['days']);

                $shift = Shift::create([
                    'company_id'  => $companyId,
                    'name'        => $data['name'],
                    'start_time'  => $times['start_time'],
                    'end_time'    => $times['end_time'],
                    'is_flexible' => $data['is_flexible'] ?? false,
                    'is_default'  => false,
                ]);

                $this->syncShiftDays($shift, $data['days']);
                $this->toggleDefault($shift, $data['is_default'] ?? false);

                return $shift->load([
                    'shiftDays' => function ($query) {
                        $query->orderBy('weekday');
                    },
                    'shiftDays.events' => function ($query) {
                        $query->orderBy('sort_order');
                    },
                ]);
            });
        } catch (QueryException $exception) {
            throw $this->handleShiftQueryException($exception);
        }

        $this->auditLogService->log(
            action: 'shift.created',
            entityType: Shift::class,
            entityId: $shift->id,
            description: 'Jornada criada.',
            newValues: $this->shiftSnapshot($shift),
            companyId: $shift->company_id,
        );

        return response()->json($shift, 201);
    }

    public function show(Request $request, Shift $shift)
    {
        $this->authorizeCompany($request, $shift);

        return $shift->load([
            'shiftDays' => function ($query) {
                $query->orderBy('weekday');
            },
            'shiftDays.events' => function ($query) {
                $query->orderBy('sort_order');
            },
        ]);
    }

    public function byUser(Request $request, User $user)
    {
        if (! $this->userVisibilityService->canViewUser($request->user(), $user)) {
            abort(403, 'Usuário não pertence à empresa atual.');
        }

        return Shift::with([
            'shiftDays' => function ($query) {
                $query->orderBy('weekday');
            },
            'shiftDays.events' => function ($query) {
                $query->orderBy('sort_order');
            },
        ])->where('company_id', $user->company_id)->get();
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        $this->authorizeCompany($request, $shift);
        $before = $this->shiftSnapshot($shift->load([
            'shiftDays' => fn ($query) => $query->orderBy('weekday'),
            'shiftDays.events' => fn ($query) => $query->orderBy('sort_order'),
        ]));

        $data = $request->validated();

        try {
            $shift = DB::transaction(function () use ($shift, $data) {
                $payload = [];

                if (array_key_exists('name', $data)) {
                    $payload['name'] = $data['name'];
                }

                if (array_key_exists('is_flexible', $data)) {
                    $payload['is_flexible'] = $data['is_flexible'];
                }

                if (! empty($data['days'])) {
                    $times = $this->resolveShiftTimes($data['days']);
                    $payload['start_time'] = $times['start_time'];
                    $payload['end_time'] = $times['end_time'];
                }

                if (! empty($payload)) {
                    $shift->update($payload);
                }

                if (! empty($data['days'])) {
                    $this->syncShiftDays($shift, $data['days']);
                }

                if (array_key_exists('is_default', $data)) {
                    $this->toggleDefault($shift, (bool) $data['is_default']);
                }

                return $shift->load([
                    'shiftDays' => function ($query) {
                        $query->orderBy('weekday');
                    },
                    'shiftDays.events' => function ($query) {
                        $query->orderBy('sort_order');
                    },
                ]);
            });
        } catch (QueryException $exception) {
            throw $this->handleShiftQueryException($exception);
        }

        [$oldValues, $newValues] = $this->auditLogService->diff($before, $this->shiftSnapshot($shift));

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditLogService->log(
                action: 'shift.updated',
                entityType: Shift::class,
                entityId: $shift->id,
                description: 'Jornada atualizada.',
                oldValues: $oldValues,
                newValues: $newValues,
                companyId: $shift->company_id,
            );
        }

        return $shift;
    }

    public function destroy(Request $request, Shift $shift)
    {
        $this->authorizeCompany($request, $shift);

        if ($shift->userShifts()->exists()) {
            throw ValidationException::withMessages([
                'shift' => 'Não é possível remover uma jornada vinculada a colaboradores.',
            ]);
        }

        $snapshot = $this->shiftSnapshot($shift->load([
            'shiftDays' => fn ($query) => $query->orderBy('weekday'),
            'shiftDays.events' => fn ($query) => $query->orderBy('sort_order'),
        ]));

        $shift->delete();

        $this->auditLogService->log(
            action: 'shift.deleted',
            entityType: Shift::class,
            entityId: $shift->id,
            description: 'Jornada removida.',
            oldValues: $snapshot,
            companyId: $shift->company_id,
        );

        return response()->json(['message' => 'Deletado']);
    }

    protected function authorizeCompany(Request $request, Shift $shift): void
    {
        if ($shift->company_id !== $request->user()->company_id) {
            abort(403, 'Jornada não pertence à empresa atual.');
        }
    }

    protected function syncShiftDays(Shift $shift, array $days): void
    {
        $weekdays = [];

        foreach ($days as $day) {
            $weekdays[] = $day['weekday'];
            $isWorkingDay = (bool) ($day['is_working_day'] ?? false);

            /** @var ShiftDay $shiftDay */
            $shiftDay = $shift->shiftDays()->updateOrCreate(
                ['weekday' => $day['weekday']],
                [
                    'is_working_day'   => $isWorkingDay ? 'true' : 'false',
                    'start_time'       => $isWorkingDay ? $day['start_time'] : null,
                    'end_time'         => $isWorkingDay ? $day['end_time'] : null,
                    'scheduled_minutes' => $isWorkingDay ? $this->resolveScheduledMinutes($day) : null,
                    'break_start_time' => $isWorkingDay ? ($day['break_start_time'] ?? null) : null,
                    'break_end_time'   => $isWorkingDay ? ($day['break_end_time'] ?? null) : null,
                    'break_minutes'    => $isWorkingDay ? ($day['break_minutes'] ?? null) : null,
                ]
            );

            $this->syncShiftDayEvents($shiftDay);
        }

        $shift->shiftDays()->whereNotIn('weekday', $weekdays)->delete();
    }

    protected function syncShiftDayEvents(ShiftDay $shiftDay): void
    {
        $shiftDay->events()->delete();

        $events = ShiftDayEventNormalizer::fromLegacyColumns($shiftDay);

        foreach ($events as $event) {
            $shiftDay->events()->create($event);
        }
    }

    protected function resolveShiftTimes(array $days): array
    {
        $workingDays = collect($days)->filter(fn ($day) => $day['is_working_day'] && $day['start_time'] && $day['end_time']);

        if ($workingDays->isEmpty()) {
            return ['start_time' => null, 'end_time' => null];
        }

        return [
            'start_time' => $workingDays->pluck('start_time')->sort()->first(),
            'end_time'   => $workingDays->pluck('end_time')->sort()->last(),
        ];
    }

    protected function handleShiftQueryException(QueryException $exception): ValidationException
    {
        report($exception);

        return ValidationException::withMessages([
            'days' => 'Type error on shift days. Send boolean values for "is_working_day" and time values compatible with the database.',
        ]);
    }

    protected function toggleDefault(Shift $shift, bool $isDefault): void
    {
        if (! $isDefault) {
            if ($shift->is_default) {
                $shift->update(['is_default' => false]);
            }

            return;
        }

        Shift::where('company_id', $shift->company_id)
            ->where('id', '!=', $shift->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        if (! $shift->is_default) {
            $shift->update(['is_default' => true]);
        }
    }

    protected function shiftSnapshot(Shift $shift): array
    {
        $shift->loadMissing([
            'shiftDays' => fn ($query) => $query->orderBy('weekday'),
            'shiftDays.events' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        return $this->auditLogService->snapshot([
            'name' => $shift->name,
            'is_default' => (bool) $shift->is_default,
            'is_flexible' => (bool) $shift->is_flexible,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'days' => $shift->shiftDays->map(fn (ShiftDay $day) => [
                'weekday' => $day->weekday,
                'is_working_day' => (bool) $day->is_working_day,
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
                'scheduled_minutes' => $day->scheduled_minutes,
                'break_start_time' => $day->break_start_time,
                'break_end_time' => $day->break_end_time,
                'break_minutes' => $day->break_minutes,
                'events' => $day->events->map(fn ($event) => [
                    'kind' => $event->kind,
                    'label' => $event->label,
                    'expected_type' => $event->expected_type,
                    'expected_at' => $event->expected_at,
                    'sort_order' => $event->sort_order,
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    protected function resolveScheduledMinutes(array $day): ?int
    {
        if (isset($day['scheduled_minutes']) && $day['scheduled_minutes'] !== null) {
            return (int) $day['scheduled_minutes'];
        }

        if (empty($day['start_time']) || empty($day['end_time'])) {
            return null;
        }

        return $this->timeToMinutes($day['end_time']) - $this->timeToMinutes($day['start_time']);
    }

    protected function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
