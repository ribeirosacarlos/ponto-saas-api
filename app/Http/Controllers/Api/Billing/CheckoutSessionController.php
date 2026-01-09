<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutSessionRequest;
use App\Models\Plan;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;

class CheckoutSessionController extends Controller
{
    public function __construct(protected StripeBillingService $stripeBillingService)
    {
    }

    public function store(CreateCheckoutSessionRequest $request)
    {
        $user = $request->user();
        $company = $user?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 403);
        }

        $plan = Plan::find($request->plan_id);

        if (! $plan) {
            return response()->json(['message' => 'Plano não encontrado.'], 404);
        }

        if (! $plan->stripe_price_id) {
            return response()->json(['message' => 'Plano sem preço configurado no Stripe.'], 422);
        }

        try {
            $session = $this->stripeBillingService->createCheckoutSession($company, $plan, $user);
        } catch (\Throwable $e) {
            Log::error('Erro ao criar sessão de checkout Stripe', [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao criar sessão de checkout.'], 500);
        }

        return response()->json(['url' => $session->url]);
    }
}
