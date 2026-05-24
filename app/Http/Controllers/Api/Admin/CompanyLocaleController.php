<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyLocaleRequest;
use App\Models\Company;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class CompanyLocaleController extends Controller
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

    public function update(UpdateCompanyLocaleRequest $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('updateLocale', $company);

        $fields = ['country', 'locale'];

        $before = $this->auditLogService->snapshot($company->toArray(), $fields);

        $company->update($request->validated());

        $fresh = $company->fresh();

        $this->auditLogService->log(
            action: 'company.locale_updated',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Localização e idioma da empresa atualizados.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot($fresh->toArray(), $fields),
            companyId: $company->id,
        );

        return response()->json($this->payload($fresh));
    }

    private function payload(Company $company): array
    {
        return [
            'country' => $company->country,
            'locale'  => $company->locale,
        ];
    }
}
