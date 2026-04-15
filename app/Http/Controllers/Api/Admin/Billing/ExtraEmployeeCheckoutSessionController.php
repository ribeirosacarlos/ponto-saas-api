<?php

namespace App\Http\Controllers\Api\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Services\ExtraEmployeeChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ExtraEmployeeCheckoutSessionController extends Controller
{
    public function __construct(protected ExtraEmployeeChargeService $extraEmployeeChargeService)
    {
    }

    public function store(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        try {
            $session = $this->extraEmployeeChargeService->createCheckoutSessionForPendingCharge($company, $request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erro ao criar checkout de colaboradores extras', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Erro ao criar sessão de checkout para colaboradores extras.'], 500);
        }

        return response()->json(['url' => $session->url]);
    }
}
