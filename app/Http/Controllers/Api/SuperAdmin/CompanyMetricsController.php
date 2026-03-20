<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\CompanyMetricsResource;
use App\Services\SuperAdmin\SuperAdminAnalyticsService;
use Illuminate\Http\Request;

class CompanyMetricsController extends Controller
{
    public function __construct(
        protected SuperAdminAnalyticsService $analyticsService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|nullable|in:active,blocked,trialing,past_due,canceled',
            'activity' => 'sometimes|nullable|in:active,inactive',
            'sort' => 'sometimes|nullable|string|max:50',
            'per_page' => 'sometimes|nullable|integer|min:1|max:100',
        ]);

        $companies = $this->analyticsService->companyMetricsPage($filters);

        return CompanyMetricsResource::collection($companies);
    }
}
