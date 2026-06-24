<?php

namespace App\Services\Commercial;

use App\Models\CommercialAffiliate;

class CommercialAffiliateMetricsService
{
    public function build(CommercialAffiliate $affiliate): array
    {
        $totalClicks = $affiliate->clicks()->count();
        $uniqueClicks = $affiliate->clicks()->whereNotNull('ip_hash')->pluck('ip_hash')->unique()->count();

        $totalLeads = $affiliate->leads()->count();
        $wonLeads = $affiliate->leads()->where('status', 'won')->count();

        $pendingCommissions = $affiliate->commissions()->whereIn('status', ['pending', 'approved'])->sum('commission_amount');
        $paidCommissions = $affiliate->commissions()->where('status', 'paid')->sum('commission_amount');

        $pendingBonus = $affiliate->bonuses()->where('status', '!=', 'paid')->sum('total_bonus_amount');
        $paidBonus = $affiliate->bonuses()->where('status', 'paid')->sum('total_bonus_amount');

        return [
            'total_clicks' => $totalClicks,
            'unique_clicks' => $uniqueClicks,
            'total_leads' => $totalLeads,
            'won_leads' => $wonLeads,
            'conversion_rate_click_to_lead' => $totalClicks > 0 ? round($totalLeads / $totalClicks * 100, 2) : 0.0,
            'conversion_rate_lead_to_customer' => $totalLeads > 0 ? round($wonLeads / $totalLeads * 100, 2) : 0.0,
            'pending_commissions' => (float) $pendingCommissions,
            'paid_commissions' => (float) $paidCommissions,
            'pending_bonus' => (float) $pendingBonus,
            'paid_bonus' => (float) $paidBonus,
        ];
    }
}
