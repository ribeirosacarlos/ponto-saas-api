<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyGeolocationRequest;
use App\Models\Plan;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class CompanyGeolocationController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function show(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        return response()->json($this->payload($company));
    }

    public function update(UpdateCompanyGeolocationRequest $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('updateGeolocation', $company);

        $company->loadMissing(['currentPlan', 'subscription.plan']);

        $requiredOnClock = (bool) ($request->input('required_on_clock', $request->input('enabled')));

        if ($requiredOnClock && ! $this->hasGeolocationFeature($company->currentPlan ?? $company->subscription?->plan)) {
            return response()->json([
                'message' => 'O plano atual da empresa não possui suporte a geolocalização.',
            ], 422);
        }

        $before = $this->auditLogService->snapshot([
            'geolocation_required' => (bool) $company->geolocation_required,
        ]);

        $company->update([
            'geolocation_required' => $requiredOnClock,
        ]);

        $this->auditLogService->log(
            action: 'company.geolocation_requirement_updated',
            entityType: \App\Models\Company::class,
            entityId: $company->id,
            description: 'Regra de obrigatoriedade de geolocalização atualizada.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot([
                'geolocation_required' => (bool) $company->fresh()->geolocation_required,
            ]),
            companyId: $company->id,
        );

        return response()->json($this->payload($company->fresh(['currentPlan', 'subscription.plan'])));
    }

    private function payload($company): array
    {
        /** @var Plan|null $plan */
        $plan = $company->currentPlan ?? $company->subscription?->plan;

        return [
            'feature_available' => $this->hasGeolocationFeature($plan),
            'required_on_clock' => (bool) $company->geolocation_required,
        ];
    }

    private function hasGeolocationFeature(?Plan $plan): bool
    {
        return (bool) ($plan?->hasFeature('geolocation') ?? false);
    }
}
