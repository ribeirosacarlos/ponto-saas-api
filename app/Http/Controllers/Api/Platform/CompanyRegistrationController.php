<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformCompanyRegistrationRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanySlugService;
use App\Services\AuditLogService;
use App\Jobs\SendCompanyAdminInviteJob;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyRegistrationController extends Controller
{
    public function __construct(
        protected CompanySlugService $slugService,
        protected AuditLogService $auditLogService
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

        $adminPassword = Str::password(12);
        $adminInviteCode = $this->generateInviteCode();

        $admin = User::create([
            'company_id' => $company->id,
            'name' => $payload['admin_name'],
            'email' => $payload['admin_email'],
            'password' => Hash::make($adminPassword),
            'password_set_at' => now(),
            'invited_at' => now(),
            'invite_code_hash' => hash('sha256', $adminInviteCode),
            'invite_expires_at' => now()->addDays(7),
            'must_change_password' => true,
        ]);

        $admin->assignRole('admin');

        $supportEmail = config('app.support_email');

        SendCompanyAdminInviteJob::dispatch($admin->id, [
            'companyName' => $company->name,
            'inviteCode' => $adminInviteCode,
            'supportEmail' => $supportEmail,
        ]);

        $this->auditLogService->log(
            action: 'platform.company_registered',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Empresa criada por super admin com administrador inicial.',
            newValues: $this->auditLogService->snapshot([
                'company' => [
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'document' => $company->document,
                    'email' => $company->email,
                ],
                'admin_user' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => 'admin',
                ],
            ]),
            targetCompanyId: $company->id,
        );

        return response()->json([
            'company' => new CompanyResource($company),
            'admin_user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ], 201);
    }

    protected function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($characters);
        $inviteCode = '';

        for ($i = 0; $i < 8; $i++) {
            $inviteCode .= $characters[random_int(0, $length - 1)];
        }

        return $inviteCode;
    }
}
