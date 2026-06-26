<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialLead;
use App\Services\Commercial\CommercialDashboardService;
use Illuminate\Http\Request;

class CommercialDashboardController extends Controller
{
    public function show(Request $request, CommercialDashboardService $dashboardService)
    {
        $this->authorize('viewAny', CommercialLead::class);

        return response()->json($dashboardService->build($request->user()));
    }
}
