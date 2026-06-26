<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Resources\Commercial\CommercialAffiliateResource;
use App\Models\CommercialAffiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CommercialAffiliateAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $affiliate = CommercialAffiliate::where('email', $request->email)->first();

        if (! $affiliate || ! $affiliate->password || ! Hash::check($request->password, $affiliate->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        if ($affiliate->status !== 'active') {
            return response()->json(['message' => 'Conta inativa'], 403);
        }

        $token = $affiliate->createToken('affiliate-auth')->plainTextToken;

        return response()->json([
            'affiliate' => new CommercialAffiliateResource($affiliate->load('commissionPlan')),
            'token'     => $token,
        ]);
    }

    public function me(Request $request)
    {
        return new CommercialAffiliateResource(
            $request->user()->load('commissionPlan')
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout feito com sucesso']);
    }
}
