<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Resources\Commercial\CommercialAffiliateBonusResource;
use App\Http\Resources\Commercial\CommercialCommissionResource;
use App\Models\CommercialAffiliateBonus;
use App\Models\CommercialCommission;
use App\Services\AuditLogService;
use App\Services\Commercial\CommercialCommissionService;
use Illuminate\Http\Request;

class CommercialCommissionController extends Controller
{
    public function __construct(
        protected CommercialCommissionService $commissionService,
        protected AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialCommission::class);

        $commissions = CommercialCommission::query()
            ->when($request->filled('affiliate_id'), fn ($q) => $q->where('affiliate_id', $request->input('affiliate_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return CommercialCommissionResource::collection($commissions);
    }

    public function approve(string $id)
    {
        $this->authorize('manage', CommercialCommission::class);

        $commission = CommercialCommission::findOrFail($id);
        $this->commissionService->approve($commission);

        $this->auditLogService->log(
            action: 'commission.approved',
            entityType: CommercialCommission::class,
            entityId: $commission->id,
            description: "Comissão aprovada: {$commission->commission_amount}",
        );

        return new CommercialCommissionResource($commission);
    }

    public function markPaid(string $id)
    {
        $this->authorize('manage', CommercialCommission::class);

        $commission = CommercialCommission::findOrFail($id);
        $this->commissionService->markPaid($commission);

        $this->auditLogService->log(
            action: 'commission.paid',
            entityType: CommercialCommission::class,
            entityId: $commission->id,
            description: "Comissão paga: {$commission->commission_amount}",
        );

        return new CommercialCommissionResource($commission);
    }

    public function bonuses(Request $request)
    {
        $this->authorize('viewAny', CommercialCommission::class);

        $bonuses = CommercialAffiliateBonus::query()
            ->when($request->filled('affiliate_id'), fn ($q) => $q->where('affiliate_id', $request->input('affiliate_id')))
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->input('year')))
            ->when($request->filled('month'), fn ($q) => $q->where('month', $request->input('month')))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->integer('per_page', 20));

        return CommercialAffiliateBonusResource::collection($bonuses);
    }
}
