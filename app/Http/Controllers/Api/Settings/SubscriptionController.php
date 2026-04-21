<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelSubscriptionRequest;
use App\Http\Resources\SettingsSubscriptionResource;
use App\Services\CompanySubscriptionService;
use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        protected StripeBillingService $stripeBillingService,
        protected CompanySubscriptionService $companySubscriptionService
    ) {
    }

    public function show(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        return new SettingsSubscriptionResource(
            $this->companySubscriptionService->buildSubscriptionPayload($company->fresh(['subscription.plan']))
        );
    }

    public function cancel(CancelSubscriptionRequest $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        try {
            $subscription = $this->stripeBillingService->scheduleCancellationAtPeriodEnd($company->fresh(['subscription.plan']));
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Erro ao agendar cancelamento da assinatura', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao cancelar assinatura.'], 500);
        }

        $company = $subscription->company->fresh(['subscription.plan']);

        return response()->json([
            'message' => 'Assinatura agendada para cancelamento no fim do período atual.',
            'data' => (new SettingsSubscriptionResource(
                $this->companySubscriptionService->buildSubscriptionPayload($company)
            ))->resolve(),
        ]);
    }
}
