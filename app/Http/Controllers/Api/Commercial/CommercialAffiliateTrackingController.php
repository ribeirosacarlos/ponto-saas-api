<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialAffiliate;
use App\Models\CommercialAffiliateClick;
use Illuminate\Http\Request;

class CommercialAffiliateTrackingController extends Controller
{
    /**
     * GET /r/{slug} — registra o clique e redireciona para a landing page do Jornafy.
     */
    public function redirect(Request $request, string $slug)
    {
        $affiliate = CommercialAffiliate::where('slug', $slug)->where('status', 'active')->first();

        if (! $affiliate) {
            return redirect()->away(config('app.frontend_url', config('app.url')));
        }

        $this->registerClick($affiliate, $request);

        return redirect()->away(config('app.frontend_url', config('app.url')).'?ref='.$affiliate->slug);
    }

    /**
     * POST /commercial/track-affiliate-click — alternativa para frontends que não seguem redirect HTTP.
     */
    public function track(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
            'landing_page' => ['nullable', 'string'],
            'utm_source' => ['nullable', 'string'],
            'utm_medium' => ['nullable', 'string'],
            'utm_campaign' => ['nullable', 'string'],
        ]);

        $affiliate = CommercialAffiliate::where('slug', $data['slug'])->where('status', 'active')->first();

        if (! $affiliate) {
            return response()->json(['message' => 'Afiliado não encontrado.'], 404);
        }

        $click = $this->registerClick($affiliate, $request, $data);

        return response()->json(['affiliate_id' => $affiliate->id, 'click_id' => $click->id], 201);
    }

    private function registerClick(CommercialAffiliate $affiliate, Request $request, array $extra = []): CommercialAffiliateClick
    {
        return CommercialAffiliateClick::create([
            'affiliate_id' => $affiliate->id,
            'ip_hash' => $this->hashIp($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'referer' => $request->header('referer'),
            'landing_page' => $extra['landing_page'] ?? $request->query('landing_page'),
            'utm_source' => $extra['utm_source'] ?? $request->query('utm_source'),
            'utm_medium' => $extra['utm_medium'] ?? $request->query('utm_medium'),
            'utm_campaign' => $extra['utm_campaign'] ?? $request->query('utm_campaign'),
            'clicked_at' => now(),
        ]);
    }

    private function hashIp(Request $request): ?string
    {
        $ip = $request->ip();

        if (! $ip) {
            return null;
        }

        return hash('sha256', $ip.config('app.key'));
    }
}
