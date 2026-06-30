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
        return new CommercialAffiliateResource(
            $request->user()->load('commissionPlan')
        );
    }

    public function dashboard(Request $request, CommercialAffiliateMetricsService $metricsService)
    {
        return response()->json($metricsService->build($request->user()));
    }
}
