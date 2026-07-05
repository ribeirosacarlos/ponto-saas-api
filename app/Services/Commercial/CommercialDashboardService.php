<?php

namespace App\Services\Commercial;

use App\Models\CommercialLead;
use App\Models\User;
use Illuminate\Support\Carbon;

class CommercialDashboardService
{
    public function build(User $user): array
    {
        $query = $this->scopedLeadsQuery($user);

        $statusCounts = (clone $query)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $today = Carbon::now()->startOfDay();
        $todayEnd = Carbon::now()->endOfDay();

        return [
            'total_leads' => (clone $query)->count(),
            'new_leads' => (int) ($statusCounts['new'] ?? 0),
            'leads_in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
            'demos_scheduled' => (int) ($statusCounts['demo_scheduled'] ?? 0),
            'proposals_sent' => (int) ($statusCounts['proposal_sent'] ?? 0),
            'won_leads' => (int) ($statusCounts['won'] ?? 0),
            'lost_leads' => (int) ($statusCounts['lost'] ?? 0),
            'leads_by_step' => (clone $query)
                ->selectRaw('current_step_id, count(*) as total')
                ->groupBy('current_step_id')
                ->pluck('total', 'current_step_id'),
            'leads_by_priority' => (clone $query)
                ->selectRaw('priority, count(*) as total')
                ->groupBy('priority')
                ->pluck('total', 'priority'),
            'leads_by_agent' => (clone $query)
                ->selectRaw('assigned_to_user_id, count(*) as total')
                ->groupBy('assigned_to_user_id')
                ->pluck('total', 'assigned_to_user_id'),
            'leads_by_affiliate' => (clone $query)
                ->selectRaw('affiliate_id, count(*) as total')
                ->whereNotNull('affiliate_id')
                ->groupBy('affiliate_id')
                ->pluck('total', 'affiliate_id'),
            'next_actions_today' => (clone $query)
                ->whereBetween('next_action_at', [$today, $todayEnd])
                ->count(),
            'overdue_next_actions' => (clone $query)
                ->whereNotNull('next_action_at')
                ->where('next_action_at', '<', $today)
                ->whereNotIn('status', ['won', 'lost'])
                ->count(),
        ];
    }

    private function scopedLeadsQuery(User $user)
    {
        return CommercialLead::query()
            ->when(
                $user->hasRole('commercial_agent'),
                fn ($query) => $query->where('assigned_to_user_id', $user->id)
            );
    }
}
