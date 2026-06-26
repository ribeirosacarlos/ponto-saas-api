<?php

namespace App\Services\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialAffiliateBonus;
use App\Models\CommercialCommission;

class CommercialAffiliateBonusService
{
    /**
     * Recalcula o bônus de um afiliado para um mês calendário, contando
     * clientes distintos com comissão paga naquele mês (padrão: 50€ a cada 5 clientes).
     */
    public function recalculateForMonth(string $affiliateId, int $year, int $month): ?CommercialAffiliateBonus
    {
        $affiliate = CommercialAffiliate::with('commissionPlan')->find($affiliateId);
        $plan = $affiliate?->commissionPlan;

        if (! $plan || ! $plan->bonus_enabled) {
            return null;
        }

        $clientsCount = CommercialCommission::query()
            ->where('affiliate_id', $affiliateId)
            ->where('status', 'paid')
            ->whereNotNull('customer_id')
            ->whereYear('paid_at', $year)
            ->whereMonth('paid_at', $month)
            ->pluck('customer_id')
            ->unique()
            ->count();

        $bonusEveryClients = $plan->bonus_every_clients ?: 5;
        $bonusAmount = (float) $plan->bonus_amount;
        $totalBonus = intdiv($clientsCount, $bonusEveryClients) * $bonusAmount;

        $bonus = CommercialAffiliateBonus::firstOrNew([
            'affiliate_id' => $affiliateId,
            'year' => $year,
            'month' => $month,
        ]);

        if ($bonus->exists && $bonus->status === 'paid') {
            return $bonus;
        }

        $bonus->fill([
            'clients_count' => $clientsCount,
            'bonus_every_clients' => $bonusEveryClients,
            'bonus_amount' => $bonusAmount,
            'total_bonus_amount' => $totalBonus,
            'status' => $bonus->status ?? 'pending',
        ])->save();

        return $bonus;
    }
}
