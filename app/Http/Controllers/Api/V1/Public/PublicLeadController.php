<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\OptOutLeadRequest;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicLeadController extends Controller
{
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $payload = $request->validated();

        try {
            Lead::create([
                'email' => Str::lower(trim($payload['email'])),
                'lead_magnet_type' => $payload['lead_magnet_type'],
                'page_slug' => $payload['page_slug'] ?? null,
                'consented_at' => $payload['consented_at'],
                'ip_hash' => $this->hashIp($request),
            ]);
        } catch (UniqueConstraintViolationException) {
            // E-mail já cadastrado para este material: sucesso silencioso (LGPD).
        }

        return response()->json(['success' => true]);
    }

    public function optOut(OptOutLeadRequest $request): JsonResponse
    {
        $email = Str::lower(trim($request->validated('email')));

        Lead::where('email', $email)->delete();

        return response()->json(['success' => true]);
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
