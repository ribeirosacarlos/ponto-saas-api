<?php

namespace App\Http\Controllers\Api\AffiliatePortal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Commercial\CommercialAffiliateResource;
use App\Services\Commercial\CommercialAffiliateMetricsService;
use Illuminate\Http\Request;

class AffiliatePortalController extends Controller
{
    public function me(Request $request)
    {
        $affiliate = $request->user()->commercialAffiliate()->with('commissionPlan')->firstOrFail();

        return new CommercialAffiliateResource($affiliate);
    }

    public function dashboard(Request $request, CommercialAffiliateMetricsService $metricsService)
    {
        $affiliate = $request->user()->commercialAffiliate()->firstOrFail();

        return response()->json($metricsService->build($affiliate));
    }
}
