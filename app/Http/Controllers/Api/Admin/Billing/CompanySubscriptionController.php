<?php

namespace App\Http\Controllers\Api\Admin\Billing;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Company;
use App\Services\StripeBillingService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;

class CompanySubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected StripeBillingService $stripeBillingService
    ) {
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

        $validated = $request->validated();

        $shouldCancelOnStripe = ($validated['status'] ?? null) === SubscriptionStatus::CANCELED->value
            && ! $subscription->isCanceled()
            && $subscription->stripe_subscription_id;

        if ($shouldCancelOnStripe) {
            try {
                $subscription = $this->stripeBillingService->cancelImmediately($company);
            } catch (\Throwable $e) {
                Log::error('Erro ao cancelar assinatura na Stripe', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json(['message' => 'Erro ao cancelar assinatura na Stripe.'], 500);
            }
        }

        $subscription->update($validated);

        return new SubscriptionResource($subscription->fresh('plan'));
    }
}
