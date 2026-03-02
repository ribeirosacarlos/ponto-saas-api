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
    public function __construct(
        protected VacationAccrualService $accrualService
    ) {
    }

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
                'policy' => null,
                'period_start' => null,
                'period_end' => null,
                'annual_entitlement_days' => 0.0,
                'accrual_basis' => null,
                'computable_days' => 0,
                'non_computable_days' => 0,
                'accrual_rate' => 0.0,
                'accrued_days' => 0.0,
                'used_days' => 0.0,
                'available_days' => 0.0,
                'adjustment_days' => 0.0,
                'breakdown' => [],
            ];
        }

        $periodStart = Carbon::create($year, 1, 1)->startOfDay();
        $periodEnd = Carbon::create($year, 12, 31)->startOfDay();
        $today = Carbon::today();

        if ($today->lt($periodEnd)) {
            $periodEnd = $today;
        }

        $startDate = $user->created_at?->copy()->startOfDay() ?? now()->startOfDay();

        if (! empty($user->employment_start_date)) {
            $startDate = Carbon::parse($user->employment_start_date)->startOfDay();
        }

        if ($startDate->greaterThan($periodEnd)) {
            return [
                'policy' => $policy,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'annual_entitlement_days' => (float) ($policy->annual_entitlement_days ?? $policy->days_per_year ?? 30),
                'accrual_basis' => $policy->accrual_basis ?? 'calendar_days',
                'computable_days' => 0,
                'non_computable_days' => 0,
                'accrual_rate' => 0.0,
                'accrued_days' => 0.0,
                'used_days' => 0.0,
                'available_days' => 0.0,
                'adjustment_days' => 0.0,
                'breakdown' => [],
            ];
        }

        $effectiveStart = $startDate->greaterThan($periodStart) ? $startDate : $periodStart;

        $accrual = $this->accrualService->calculate($user, $policy, $effectiveStart, $periodEnd);
        $usedDays = $this->calculateUsedDays($user, $effectiveStart, $periodEnd);
        $adjustment = $this->getManualAdjustment($user, $year);
        $availableDays = round($accrual['accrued_days'] - $usedDays + $adjustment, 2);

        return [
            'policy' => $policy,
            ...$accrual,
            'used_days' => $usedDays,
            'available_days' => $availableDays,
            'adjustment_days' => $adjustment,
        ];
    }

    protected function calculateUsedDays(User $user, Carbon $periodStart, Carbon $periodEnd): float
    {
        return (float) VacationRequest::approved()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('start_date', '>=', $periodStart->toDateString())
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
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

    public function ensureEnoughBalance(User $user, float $requestedDays): bool
    {
        $balance = $this->calculateBalance($user);
        return ($balance['available_days'] ?? 0) >= $requestedDays;
    }
}
