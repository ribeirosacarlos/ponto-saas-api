<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacationDay;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VacationRequestService
{
    public function __construct(
        protected VacationDaysCalculator $daysCalculator,
        protected VacationBalanceService $balanceService
    ) {
    }

    public function calculateRequestedDays(User $user, Carbon $start, Carbon $end, string $countingMethod): array
    {
        return $this->daysCalculator->calculate($user, $start, $end, $countingMethod);
    }

    public function ensureNoConflicts(User $user, Carbon $start, Carbon $end, ?string $ignoreRequestId = null): void
    {
        $query = VacationRequest::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function (Builder $builder) use ($start, $end) {
                $builder->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_date', '<=', $start->toDateString())
                          ->where('end_date', '>=', $end->toDateString());
                    });
            });

        if ($ignoreRequestId) {
            $query->where('id', '!=', $ignoreRequestId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'dates' => 'Já existe um pedido de férias que conflita com este período.',
            ]);
        }
    }

    public function ensureEnoughBalance(User $user, float $requestedDays): void
    {
        if (! $this->balanceService->ensureEnoughBalance($user, $requestedDays)) {
            throw ValidationException::withMessages([
                'requested_days' => 'Você não possui saldo suficiente para este período.',
            ]);
        }
    }

    public function createVacationDays(VacationRequest $request, Collection $dates): void
    {
        if ($dates->isEmpty()) {
            return;
        }

        $payload = $dates->map(function (Carbon $date) use ($request) {
            return [
                'id'                  => (string) Str::uuid(),
                'company_id'          => $request->company_id,
                'user_id'             => $request->user_id,
                'vacation_request_id' => $request->id,
                'date'                => $date->toDateString(),
                'day_type'            => 'full_day',
                'created_at'          => now(),
                'updated_at'          => now(),
            ];
        })->toArray();

        VacationDay::insert($payload);
    }

    public function deleteVacationDays(VacationRequest $request): void
    {
        $request->vacationDays()->delete();
    }
}
