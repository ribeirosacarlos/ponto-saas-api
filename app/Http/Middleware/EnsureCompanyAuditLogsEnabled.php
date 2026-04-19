<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCompanyAuditLogsEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        if (! $user->hasRole('admin')) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        if (! $company->audit_logs_enabled) {
            return response()->json([
                'message' => 'A visualização de auditoria não está habilitada para esta empresa.',
            ], 403);
        }

        return $next($request);
    }
}
