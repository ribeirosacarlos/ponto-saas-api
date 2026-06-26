<?php

namespace App\Services\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialCommission;
use App\Models\CommercialCommissionPlan;
use App\Models\CommercialLead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CommercialCommissionService
{
    public function __construct(
        private CommercialAffiliateBonusService $bonusService,
    ) {}

    /**
     * Gera as comissões recorrentes de um lead convertido (padrão: 20% durante 6 meses).
     */
    public function generateRecurringCommissions(CommercialLead $lead, float $baseAmount): Collection
    {
        if (! $lead->affiliate_id) {
            return new Collection;
        }

        $affiliate = $lead->affiliate ?? CommercialAffiliate::find($lead->affiliate_id);
        $plan = $affiliate?->commissionPlan ?? CommercialCommissionPlan::where('active', true)->first();

        if (! $plan) {
            return new Collection;
        }

        $commissions = new Collection;

        for ($month = 1; $month <= $plan->recurrence_months; $month++) {
            $commissions->push(CommercialCommission::create([
                'affiliate_id' => $affiliate->id,
                'lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'commission_plan_id' => $plan->id,
                'base_amount' => $baseAmount,
                'commission_percentage' => $plan->commission_percentage,
                'commission_amount' => round($baseAmount * ((float) $plan->commission_percentage) / 100, 2),
                'month_number' => $month,
                'status' => 'pending',
                'due_date' => Carbon::now()->addMonths($month)->toDateString(),
            ]));
        }

        return $commissions;
    }

    public function approve(CommercialCommission $commission): CommercialCommission
    {
        if ($commission->status === 'pending') {
            $commission->update(['status' => 'approved']);
        }

        return $commission->refresh();
    }

    public function markPaid(CommercialCommission $commission): CommercialCommission
    {
        $paidAt = Carbon::now();

        $commission->update([
            'status' => 'paid',
            'paid_at' => $paidAt,
        ]);

        $this->bonusService->recalculateForMonth(
            $commission->affiliate_id,
            $paidAt->year,
            $paidAt->month,
        );

        return $commission->refresh();
    }

    public function cancel(CommercialCommission $commission): CommercialCommission
    {
        $commission->update(['status' => 'cancelled']);

        return $commission->refresh();
    }
}
