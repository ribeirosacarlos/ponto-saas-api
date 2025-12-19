<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInviteRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class InviteController extends Controller
{
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

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'password_set_at' => now(),
            'invite_code_hash' => null,
            'invite_expires_at' => null,
            'must_change_password' => false,
        ])->save();

        return response()->json(['message' => 'Senha definida com sucesso.'], 200);
    }
}
