<?php

namespace App\Http\Middleware;

use App\Services\CompanySubscriptionService;
use Closure;
use Illuminate\Http\Request;

class EnsureCompanyHasAccess
{
    public function __construct(protected CompanySubscriptionService $companySubscriptionService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local')) {
            return $next($request);
        }

        if ($request->user()?->hasRole('super_admin')) {
            return $next($request);
        }

        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Usuário sem empresa associada.'], 403);
        }

        if ($this->companySubscriptionService->isAccessExpired($company)) {
            $this->companySubscriptionService->expireAccess($company);
        }

        if (! $this->companySubscriptionService->canAccessSystem($company)) {
            return response()->json([
                'message' => 'Acesso negado: assinatura ativa é necessária para continuar usando o sistema.',
            ], 403);
        }

        return $next($request);
    }
}
