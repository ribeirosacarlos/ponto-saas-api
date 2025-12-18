<?php

namespace App\Http\Controllers\Api\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 20), 100));

        $plans = Plan::orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        return PlanResource::collection($plans);
    }

    public function store(StorePlanRequest $request)
    {
        $plan = Plan::create($request->validated());

        return (new PlanResource($plan->fresh()))->response()->setStatusCode(201);
    }

    public function show(Plan $plan)
    {
        return new PlanResource($plan);
    }

    public function update(UpdatePlanRequest $request, Plan $plan)
    {
        $plan->update($request->validated());

        return new PlanResource($plan->fresh());
    }
}
