<?php

namespace App\Http\Controllers\Api\AffiliatePortal;

use App\Http\Controllers\Controller;
use App\Models\CommercialAffiliateBonus;
use App\Models\CommercialCommission;
use Illuminate\Http\Request;

class AffiliatePortalCommissionController extends Controller
{
    public function commissions(Request $request)
    {
        $affiliateId = $request->user()->id;

        $commissions = CommercialCommission::where('affiliate_id', $affiliateId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($commissions);
    }

    public function bonuses(Request $request)
    {
        $affiliateId = $request->user()->id;

        $bonuses = CommercialAffiliateBonus::where('affiliate_id', $affiliateId)
            ->when($request->filled('year'), fn ($q) => $q->where('year', $request->input('year')))
            ->when($request->filled('month'), fn ($q) => $q->where('month', $request->input('month')))
            ->orderByDesc('year')->orderByDesc('month')
            ->paginate($request->integer('per_page', 20));

        return response()->json($bonuses);
    }
}
