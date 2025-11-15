<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Services\TenantManager;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost(); // ex: acme.localhost
        $parts = explode('.', $host);

        // Ajuste conforme seu ambiente:
        // Se estiver usando: acme.localhost → subdomínio = acme
        // Se usar: acme.ponto.test → subdomínio = acme
        if (count($parts) < 2) {
            return $next($request); // sem subdomínio
        }

        $sub = $parts[0];  // pega "acme"

        // Buscar empresa
        $company = Company::where('slug', $sub)->first();

        // Registrar no TenantManager
        app(TenantManager::class)->setTenant($company);

        return $next($request);
    }
}
