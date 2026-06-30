<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Resources\Commercial\CommercialAffiliateResource;
use App\Jobs\SendAffiliatePasswordResetJob;
use App\Models\CommercialAffiliate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

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

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $affiliate = CommercialAffiliate::where('email', $request->email)->first();

        if ($affiliate) {
            $code = $this->generateResetCode();

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $affiliate->email],
                ['token' => hash('sha256', $code), 'created_at' => now()],
            );

            SendAffiliatePasswordResetJob::dispatch($affiliate->id, ['resetCode' => $code]);
        }

        return response()->json(['message' => 'Se o e-mail existir, um código de recuperação foi enviado.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'code'     => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (! $record || hash('sha256', $request->code) !== $record->token) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }

        $expireMinutes = config('auth.passwords.affiliates.expire', 60);

        if (Carbon::parse($record->created_at)->addMinutes($expireMinutes)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }

        $affiliate = CommercialAffiliate::where('email', $request->email)->first();

        if (! $affiliate) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }

        $affiliate->forceFill(['password' => Hash::make($request->password)])->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }

    private function generateResetCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = strlen($chars);
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }

        return $code;
    }
}
