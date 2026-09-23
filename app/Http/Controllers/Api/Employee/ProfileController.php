<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $before = $this->auditLogService->snapshot($user->toArray(), ['name']);

        $user->update($request->validated());

        $this->auditLogService->log(
            action: 'user.profile_updated',
            entityType: User::class,
            entityId: $user->id,
            description: 'Perfil do usuário atualizado.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot($user->fresh()->toArray(), ['name']),
            companyId: $user->company_id,
        );

        return response()->json([
            'user' => $user->fresh()->only(['id', 'name', 'email']),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Senha atualizada com sucesso.',
        ]);
    }
}
