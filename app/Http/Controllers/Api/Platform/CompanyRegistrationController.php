<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformCompanyRegistrationRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanySlugService;
use Illuminate\Support\Facades\Hash;

class CompanyRegistrationController extends Controller
{
    public function __construct(
        protected CompanySlugService $slugService
    ) {
    }

    public function store(PlatformCompanyRegistrationRequest $request)
    {
        $payload = $request->validated();

        $company = Company::create([
            'name' => $payload['company_name'],
            'slug' => $this->slugService->generate($payload['company_name']),
            'document' => $payload['company_document'] ?? null,
            'email' => $payload['company_email'] ?? null,
            'phone' => $payload['company_phone'] ?? null,
            'address' => $payload['company_address'] ?? null,
            'city' => $payload['company_city'] ?? null,
            'state' => $payload['company_state'] ?? null,
        ]);

        $admin = User::create([
            'company_id' => $company->id,
            'name' => $payload['admin_name'],
            'email' => $payload['admin_email'],
            'password' => Hash::make($payload['admin_password']),
            'password_set_at' => now(),
        ]);

        $admin->assignRole('admin');

        return response()->json([
            'company' => new CompanyResource($company),
            'admin_user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ], 201);
    }
}
