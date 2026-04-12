<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyLocationSettingsRequest;
use App\Http\Resources\CompanyLocationSettingsResource;
use Illuminate\Http\Request;

class CompanyLocationSettingsController extends Controller
{
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

        $company->update($request->validated());

        return new CompanyLocationSettingsResource($company->fresh());
    }
}
