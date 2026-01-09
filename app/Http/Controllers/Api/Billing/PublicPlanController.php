<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;

class PublicPlanController extends Controller
{
    public function index()
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return PlanResource::collection($plans);
    }
}
