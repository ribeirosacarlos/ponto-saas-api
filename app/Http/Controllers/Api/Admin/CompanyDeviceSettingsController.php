<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyDeviceSettingsRequest;
use App\Models\Company;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CompanyDeviceSettingsController extends Controller
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

    public function update(UpdateCompanyDeviceSettingsRequest $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('updateDeviceSettings', $company);

        $allowMobile  = (bool) $request->input('allow_mobile_clock');
        $allowDesktop = (bool) $request->input('allow_desktop_clock');

        if (! $allowMobile && ! $allowDesktop) {
            return response()->json([
                'message' => 'É necessário permitir ao menos um tipo de dispositivo para registro de ponto.',
            ], 422);
        }

        $before = $this->auditLogService->snapshot([
            'allow_mobile_clock'  => (bool) $company->allow_mobile_clock,
            'allow_desktop_clock' => (bool) $company->allow_desktop_clock,
        ]);

        $company->update([
            'allow_mobile_clock'  => $allowMobile,
            'allow_desktop_clock' => $allowDesktop,
        ]);

        $fresh = $company->fresh();

        $this->auditLogService->log(
            action: 'company.device_settings_updated',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Configuração de restrição de dispositivos atualizada.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot([
                'allow_mobile_clock'  => (bool) $fresh->allow_mobile_clock,
                'allow_desktop_clock' => (bool) $fresh->allow_desktop_clock,
            ]),
            companyId: $company->id,
        );

        return response()->json($this->payload($fresh));
    }

    private function payload(Company $company): array
    {
        return [
            'allow_mobile_clock'  => (bool) $company->allow_mobile_clock,
            'allow_desktop_clock' => (bool) $company->allow_desktop_clock,
        ];
    }
}
