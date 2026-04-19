<?php

namespace App\Http\Controllers\Api\Platform;

use App\Actions\Platform\UpdateCompanyAdminSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlatformCompanySettingsRequest;
use App\Http\Resources\PlatformCompanySettingsResource;
use App\Models\Company;

class CompanySettingsController extends Controller
{
    public function __construct(
        protected UpdateCompanyAdminSettingsAction $updateCompanyAdminSettingsAction
    ) {
    }

    public function show(string $company): PlatformCompanySettingsResource
    {
        return new PlatformCompanySettingsResource($this->resolveCompany($company));
    }

    public function update(
        UpdatePlatformCompanySettingsRequest $request,
        string $company
    ): PlatformCompanySettingsResource {
        $company = $this->resolveCompany($company);
        $company = $this->updateCompanyAdminSettingsAction->execute($company, $request->validated());

        return new PlatformCompanySettingsResource($company);
    }

    private function resolveCompany(string $company): Company
    {
        $company = Company::withTrashed()->findOrFail($company);

        abort_if($company->trashed(), 404);

        return $company;
    }
}
