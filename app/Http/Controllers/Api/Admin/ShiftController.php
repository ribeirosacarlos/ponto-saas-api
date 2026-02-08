<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShiftRequest;
use App\Http\Requests\UpdateShiftRequest;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        return Shift::with(['shiftDays' => function ($query) {
            $query->orderBy('weekday');
        }])
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->paginate(20);
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

                return $shift->load(['shiftDays' => function ($query) {
                    $query->orderBy('weekday');
                }]);
            });
        } catch (QueryException $exception) {
            throw $this->handleShiftQueryException($exception);
        }

        return response()->json($shift, 201);
    }

    public function show(Request $request, Shift $shift)
    {
        $this->authorizeCompany($request, $shift);

        return $shift->load(['shiftDays' => function ($query) {
            $query->orderBy('weekday');
        }]);
    }

    public function byUser(Request $request, User $user)
    {
        if ($user->company_id !== $request->user()->company_id) {
            abort(403, 'Usuário não pertence à empresa atual.');
        }

        return Shift::with(['shiftDays' => function ($query) {
            $query->orderBy('weekday');
        }])->where('company_id', $user->company_id)->get();
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        $this->authorizeCompany($request, $shift);

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

                return $shift->load(['shiftDays' => function ($query) {
                    $query->orderBy('weekday');
                }]);
            });
        } catch (QueryException $exception) {
            throw $this->handleShiftQueryException($exception);
        }

        return $shift;
    }

    public function destroy(Request $request, Shift $shift)
    {
        $this->authorizeCompany($request, $shift);

        $shift->delete();

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

            $shift->shiftDays()->updateOrCreate(
                ['weekday' => $day['weekday']],
                [
                    'is_working_day'   => $isWorkingDay ? 'true' : 'false',
                    'start_time'       => $isWorkingDay ? $day['start_time'] : null,
                    'end_time'         => $isWorkingDay ? $day['end_time'] : null,
                    'break_start_time' => $isWorkingDay ? ($day['break_start_time'] ?? null) : null,
                    'break_end_time'   => $isWorkingDay ? ($day['break_end_time'] ?? null) : null,
                    'break_minutes'    => $isWorkingDay ? ($day['break_minutes'] ?? null) : null,
                ]
            );
        }

        $shift->shiftDays()->whereNotIn('weekday', $weekdays)->delete();
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
}
