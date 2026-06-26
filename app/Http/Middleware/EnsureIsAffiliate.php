<?php

namespace App\Http\Middleware;

use App\Models\CommercialAffiliate;
use Closure;
use Illuminate\Http\Request;

class EnsureIsAffiliate
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user() instanceof CommercialAffiliate) {
            return response()->json(['message' => 'Acesso não autorizado'], 403);
        }

        return $next($request);
    }
}
