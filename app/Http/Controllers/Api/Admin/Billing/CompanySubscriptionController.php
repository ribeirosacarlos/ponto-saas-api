<?php

namespace App\Http\Controllers\Api\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use App\Services\SubscriptionService;

class CompanySubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptionService)
    {
    }

    public function show(string $company)
    {
        $company = Company::with('subscription.plan')->findOrFail($company);
        $subscription = $company->subscription;

        if (! $subscription) {
            abort(404, 'Assinatura não encontrada.');
        }

        return new SubscriptionResource($subscription);
    }

    public function update(UpdateSubscriptionRequest $request, string $company)
    {
        $company = Company::with('subscription.plan')->findOrFail($company);
        $subscription = $company->subscription;

        if (! $subscription) {
            $subscription = $this->subscriptionService->ensureDefaultTrial($company);
        }

        $subscription->update($request->validated());

        return new SubscriptionResource($subscription->fresh('plan'));
    }
}
