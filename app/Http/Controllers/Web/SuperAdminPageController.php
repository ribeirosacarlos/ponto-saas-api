<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminAnalyticsService;

class SuperAdminPageController extends Controller
{
    public function __construct(
        protected SuperAdminAnalyticsService $analyticsService
    ) {
    }

    public function index()
    {
        return view('super-admin.index', [
            'summary' => $this->analyticsService->dashboardSummary(),
            'companies' => $this->analyticsService->companyMetricsPage([
                'per_page' => 10,
                'sort' => '-last_activity_at',
            ]),
        ]);
    }
}
