<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\DashboardSummaryResource;
use App\Services\SuperAdmin\SuperAdminAnalyticsService;

class DashboardController extends Controller
{
    public function __construct(
        protected SuperAdminAnalyticsService $analyticsService
    ) {
    }

    public function show()
    {
        return new DashboardSummaryResource($this->analyticsService->dashboardSummary());
    }
}
