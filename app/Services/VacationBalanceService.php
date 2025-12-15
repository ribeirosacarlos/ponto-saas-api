<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeavePolicy;
use App\Models\User;
use App\Models\UserLeavePolicy;
use App\Models\VacationRequest;
use Carbon\Carbon;

class VacationBalanceService
{
    public function getActivePolicyForUser(User $user): ?LeavePolicy
    {
        $assignment = UserLeavePolicy::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where(function ($query) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', today());
            })
            ->orderByDesc('start_date')
            ->first();

        if ($assignment) {
            return $assignment->leavePolicy;
        }

        return LeavePolicy::where('company_id', $user->company_id)
            ->orderByDesc('effective_from')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function calculateBalance(User $user, ?int $year = null): array
    {
        $year = $year ?? now()->year;
        $policy = $this->getActivePolicyForUser($user);

        if (! $policy) {
            return [
                'policy'    => null,
                'accrued'   => 0.0,
                'used'      => 0.0,
                'available' => 0.0,
                'adjustment'=> 0.0,
            ];
        }

        $accrued = $this->calculateAccruedDays($user, $policy, $year);
        $used = $this->calculateUsedDays($user, $year);
        $adjustment = $this->getManualAdjustment($user, $year);

        return [
            'policy'    => $policy,
            'accrued'   => $accrued,
            'used'      => $used,
            'adjustment'=> $adjustment,
            'available' => round($accrued - $used + $adjustment, 2),
        ];
    }

    protected function calculateAccruedDays(User $user, LeavePolicy $policy, int $year): float
    {
        $startDate = $user->created_at?->copy()->startOfDay() ?? now();

        if (! empty($user->employment_start_date)) {
            $startDate = Carbon::parse($user->employment_start_date)->startOfDay();
        }

        $periodStart = Carbon::create($year, 1, 1);
        $periodEnd = Carbon::create($year, 12, 31);
        $today = Carbon::today();

        if ($today->lt($periodEnd)) {
            $periodEnd = $today;
        }

        if ($startDate->greaterThan($periodEnd)) {
            return 0.0;
        }

        $effectiveStart = $startDate->greaterThan($periodStart) ? $startDate : $periodStart;
        $monthsWorked = $this->monthsBetween($effectiveStart, $periodEnd);

        return round($monthsWorked * (float) $policy->accrual_rate_per_month, 2);
    }

    protected function calculateUsedDays(User $user, int $year): float
    {
        return (float) VacationRequest::approved()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereYear('start_date', $year)
            ->sum('requested_days');
    }

    protected function getManualAdjustment(User $user, int $year): float
    {
        $balance = LeaveBalance::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        return (float) ($balance?->manual_adjustment_days ?? 0);
    }

    protected function monthsBetween(Carbon $start, Carbon $end): int
    {
        if ($start->gt($end)) {
            return 0;
        }

        $startMonth = $start->copy()->startOfMonth();
        $endMonth = $end->copy()->startOfMonth();

        return $startMonth->diffInMonths($endMonth) + 1;
    }

    public function ensureEnoughBalance(User $user, float $requestedDays): bool
    {
        $balance = $this->calculateBalance($user);
        return $balance['available'] >= $requestedDays;
    }
}
