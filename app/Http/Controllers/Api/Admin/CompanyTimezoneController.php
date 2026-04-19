<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyTimezoneRequest;
use App\Services\AuditLogService;
use App\Support\CompanyTime;
use Illuminate\Http\Request;

class CompanyTimezoneController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function show(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            if ($request->user()?->hasRole('super_admin')) {
                return response()->json([
                    'timezone' => config('app.timezone'),
                    'available_timezones' => CompanyTime::availableTimezones(),
                ]);
            }

            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        return response()->json([
            'timezone' => $company->timezone ?? config('app.timezone'),
            'available_timezones' => CompanyTime::availableTimezones(),
        ]);
    }

    public function update(UpdateCompanyTimezoneRequest $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Empresa não encontrada.'], 404);
        }

        $this->authorize('updateTimezone', $company);
        $before = $this->auditLogService->snapshot([
            'timezone' => $company->timezone ?? config('app.timezone'),
        ]);

        $company->update([
            'timezone' => $request->timezone,
        ]);

        $this->auditLogService->log(
            action: 'company.timezone_updated',
            entityType: \App\Models\Company::class,
            entityId: $company->id,
            description: 'Timezone da empresa atualizada.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot([
                'timezone' => $company->fresh()->timezone,
            ]),
            companyId: $company->id,
        );

        return response()->json([
            'timezone' => $company->timezone,
        ]);
    }
}
