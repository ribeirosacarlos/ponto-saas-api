<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanySignatureSettingsRequest;
use App\Models\Company;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CompanySignatureSettingsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function show(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        return response()->json($this->payload($company));
    }

    public function update(UpdateCompanySignatureSettingsRequest $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('updateSignatureSettings', $company);

        $fields = [
            'enable_native_signatures',
            'require_timesheet_signature',
            'require_password_confirmation_for_signature',
            'allow_geolocation_on_signature',
        ];

        $before = $this->auditLogService->snapshot($company->toArray(), $fields);

        $company->update($request->validated());

        $fresh = $company->fresh();

        $this->auditLogService->log(
            action: 'company.signature_settings_updated',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Configurações de assinatura de folha atualizadas.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot($fresh->toArray(), $fields),
            companyId: $company->id,
        );

        return response()->json($this->payload($fresh));
    }

    private function payload(Company $company): array
    {
        return [
            'enable_native_signatures'                    => (bool) $company->enable_native_signatures,
            'require_timesheet_signature'                 => (bool) $company->require_timesheet_signature,
            'require_password_confirmation_for_signature' => (bool) $company->require_password_confirmation_for_signature,
            'allow_geolocation_on_signature'              => (bool) $company->allow_geolocation_on_signature,
        ];
    }
}
