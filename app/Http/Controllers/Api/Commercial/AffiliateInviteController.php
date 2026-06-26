<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialAffiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AffiliateInviteController extends Controller
{
    public function accept(Request $request)
    {
        $request->validate([
            'invite_code' => ['required', 'string'],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $affiliate = CommercialAffiliate::where(
            'invite_code_hash',
            hash('sha256', $request->invite_code)
        )->first();

        if (! $affiliate) {
            return response()->json(['message' => 'Código de convite inválido.'], 422);
        }

        if ($affiliate->invite_expires_at && now()->greaterThan($affiliate->invite_expires_at)) {
            return response()->json(['message' => 'Convite expirado. Solicite um novo ao administrador.'], 422);
        }

        $affiliate->forceFill([
            'password'          => Hash::make($request->password),
            'invite_code_hash'  => null,
            'invite_expires_at' => null,
        ])->save();

        return response()->json(['message' => 'Senha criada com sucesso. Você já pode fazer login.']);
    }
}
