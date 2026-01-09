<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PortalController extends Controller
{
    public function __construct(protected StripeBillingService $stripeBillingService)
    {
    }

    public function store(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 403);
        }

        if (! $company->stripe_customer_id) {
            return response()->json(['message' => 'Stripe customer não encontrado.'], 422);
        }

        try {
            $session = $this->stripeBillingService->createBillingPortalSession($company);
        } catch (\Throwable $e) {
            Log::error('Erro ao criar sessão do Billing Portal', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao abrir o portal de cobrança.'], 500);
        }

        return response()->json(['url' => $session->url]);
    }
}
