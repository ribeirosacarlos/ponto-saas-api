<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $featureKey = $this->extractFeatureKey($feature);
        $plan = $request->user()?->company?->plan;

        if (! $plan || ! $plan->hasFeature($featureKey)) {
            return response()->json(['message' => 'Recurso indisponível para este plano.'], 403);
        }

        return $next($request);
    }

    protected function extractFeatureKey(string $feature): string
    {
        if (! str_contains($feature, ':')) {
            return $feature;
        }

        [, $key] = explode(':', $feature, 2);

        return $key ?: $feature;
    }
}
