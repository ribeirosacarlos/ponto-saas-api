<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicCheckoutSessionRequest;
use App\Models\Company;
use App\Models\Plan;
use App\Services\StripeBillingService;
use Illuminate\Support\Facades\Log;

class PublicCheckoutSessionController extends Controller
{
    public function __construct(protected StripeBillingService $stripeBillingService)
    {
    }

    public function store(PublicCheckoutSessionRequest $request)
    {
        $payload = $request->validated();
        $company = Company::find($payload['company_id']);

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        if ($company->is_blocked) {
            return response()->json(['message' => 'Empresa bloqueada.'], 403);
        }

        $plan = Plan::find($payload['plan_id']);

        if (! $plan) {
            return response()->json(['message' => 'Plano não encontrado.'], 404);
        }

        if (! $plan->is_active) {
            return response()->json(['message' => 'Plano indisponível.'], 422);
        }

        if (! $plan->stripe_price_id) {
            return response()->json(['message' => 'Plano sem preço configurado no Stripe.'], 422);
        }

        if (($plan->price_cents ?? 0) === 0) {
            return response()->json(['message' => 'Plano gratuito não pode ser adquirido via checkout.'], 422);
        }

        try {
            $session = $this->stripeBillingService->createCheckoutSession(
                $company,
                $plan,
                $company->users()->orderBy('created_at')->first()
            );
        } catch (\Throwable $e) {
            Log::error('Erro ao criar sessão de checkout Stripe (pública)', [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao criar sessão de checkout.'], 500);
        }

        return response()->json(['url' => $session->url]);
    }
}
