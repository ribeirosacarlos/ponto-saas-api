<?php

namespace App\Http\Controllers\Api\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Services\CompanySubscriptionBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExtraEmployeeSyncController extends Controller
{
    public function __construct(protected CompanySubscriptionBillingService $billingService)
    {
    }

    public function store(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        try {
            $summary = $this->billingService->syncExtraEmployeesAfterAdminConfirmation(
                $company->fresh(['subscription.plan', 'currentPlan'])
            );
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Erro ao sincronizar colaboradores extras da assinatura', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao sincronizar colaboradores extras.'], 500);
        }

        return response()->json([
            'message' => 'Colaboradores extras sincronizados para a próxima cobrança.',
            'summary' => $summary,
        ]);
    }
}
