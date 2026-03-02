<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Vacation consumption engine.
 *
 * Calculates how many days will be deducted when a vacation request is made.
 */
class VacationConsumptionService
{
    public function calculate(User $user, Carbon $start, Carbon $end, string $countingMethod): array
    {
        return match ($countingMethod) {
            'working_days' => $this->calculateWorkingDays($user, $start, $end),
            default => $this->calculateCalendarDays($start, $end),
        };
    }

    protected function calculateCalendarDays(Carbon $start, Carbon $end): array
    {
        $period = CarbonPeriod::create($start, $end);
        $dates = collect();

        foreach ($period as $date) {
            $dates->push($date->copy());
        }

        return [
            'days' => $dates,
            'count' => round($dates->count(), 2),
        ];
    }

    protected function calculateWorkingDays(User $user, Carbon $start, Carbon $end): array
    {
        $shift = $user->userShifts()->active()->with('shift.shiftDays')->orderByDesc('start_date')->first()?->shift;
        $workingWeekdays = $shift?->shiftDays?->where('is_working_day', true)->pluck('weekday')->map(fn ($w) => (int) $w)->unique()->values();

        if (! $workingWeekdays || $workingWeekdays->isEmpty()) {
            $workingWeekdays = collect([1, 2, 3, 4, 5]);
        }

        $holidays = Holiday::where('company_id', $user->company_id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->toArray();

        $period = CarbonPeriod::create($start, $end);
        $dates = collect();

        foreach ($period as $date) {
            $weekday = (int) $date->isoWeekday();

            if (! $workingWeekdays->contains($weekday)) {
                continue;
            }

            if (in_array($date->toDateString(), $holidays, true)) {
                continue;
            }

            $dates->push($date->copy());
        }

        return [
            'days' => $dates,
            'count' => round($dates->count(), 2),
        ];
    }
}
