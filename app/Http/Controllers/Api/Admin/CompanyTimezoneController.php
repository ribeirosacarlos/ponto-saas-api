<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyTimezoneRequest;
use App\Support\CompanyTime;
use Illuminate\Http\Request;

class CompanyTimezoneController extends Controller
{
    public function show(Request $request)
    {
        $company = $request->user()?->company;

        if (! $company) {
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

        $company->update([
            'timezone' => $request->timezone,
        ]);

        return response()->json([
            'timezone' => $company->timezone,
        ]);
    }
}
