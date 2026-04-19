<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInviteRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;

class InviteController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function accept(AcceptInviteRequest $request)
    {
        $codeHash = hash('sha256', $request->validated('invite_code'));

        $user = User::query()
            ->where('invite_code_hash', $codeHash)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Código inválido.'], 422);
        }

        if ($user->invite_expires_at && now()->greaterThan($user->invite_expires_at)) {
            return response()->json(['message' => 'Código expirado.'], 422);
        }

        $before = $this->auditLogService->snapshot([
            'invite_expires_at' => optional($user->invite_expires_at)->toIso8601String(),
            'must_change_password' => (bool) $user->must_change_password,
            'password_set_at' => optional($user->password_set_at)->toIso8601String(),
        ]);

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'password_set_at' => now(),
            'invite_code_hash' => null,
            'invite_expires_at' => null,
            'must_change_password' => false,
        ])->save();

        $this->auditLogService->log(
            action: 'invite.accepted',
            entityType: User::class,
            entityId: $user->id,
            description: 'Convite aceito pelo usuário.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot([
                'invite_expires_at' => optional($user->invite_expires_at)->toIso8601String(),
                'must_change_password' => (bool) $user->must_change_password,
                'password_set_at' => optional($user->password_set_at)->toIso8601String(),
            ]),
            companyId: $user->company_id,
            userId: $user->id,
        );

        return response()->json(['message' => 'Senha definida com sucesso.'], 200);
    }
}
