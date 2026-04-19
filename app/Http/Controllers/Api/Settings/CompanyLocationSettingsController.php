<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyLocationSettingsRequest;
use App\Http\Resources\CompanyLocationSettingsResource;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class CompanyLocationSettingsController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function show(Request $request): CompanyLocationSettingsResource|\Illuminate\Http\JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('viewLocationSettings', $company);

        return new CompanyLocationSettingsResource($company);
    }

    public function update(UpdateCompanyLocationSettingsRequest $request): CompanyLocationSettingsResource|\Illuminate\Http\JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('updateLocationSettings', $company);
        $before = $this->locationSnapshot($company);

        $company->update($request->validated());

        [$oldValues, $newValues] = $this->auditLogService->diff($before, $this->locationSnapshot($company->fresh()));

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditLogService->log(
                action: 'location_settings.updated',
                entityType: \App\Models\Company::class,
                entityId: $company->id,
                description: 'Configurações de validação de localização atualizadas.',
                oldValues: $oldValues,
                newValues: $newValues,
                companyId: $company->id,
            );
        }

        return new CompanyLocationSettingsResource($company->fresh());
    }

    protected function locationSnapshot($company): array
    {
        return $this->auditLogService->snapshot([
            'company_latitude' => $company->company_latitude,
            'company_longitude' => $company->company_longitude,
            'allowed_radius_meters' => $company->allowed_radius_meters,
            'location_validation_enabled' => (bool) $company->location_validation_enabled,
        ]);
    }
}
