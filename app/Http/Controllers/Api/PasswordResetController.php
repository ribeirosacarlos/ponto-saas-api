<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\SendPasswordResetAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    public function __construct(
        protected SendPasswordResetAction $sendPasswordResetAction
    ) {}

    public function forgot(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $this->sendPasswordResetAction->execute($request->email);

        return response()->json([
           'message' => 'Se o e-mail existir, um link de recuperação foi enviado.',
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers(),
            ]
        ]);

        // Check if the token exists and is valid for the email
        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $resetToken) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 422);
        }

        // Verify the token hash
        if (hash('sha256', $request->token) !== $resetToken->token) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 422);
        }

        // Check if token has expired (using the same expiration as in config/auth.php)
        $expiresInMinutes = config('auth.passwords.users.expire', 60);
        $createdAt = Carbon::parse($resetToken->created_at);
        $now = Carbon::now();
        
        if ($createdAt->addMinutes($expiresInMinutes)->lt($now)) {
            // Delete expired token
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 422);
        }

        // Find user and update password
        $user = User::where('email', $request->email)->first();
        
        if (! $user) {
            return response()->json([
                'message' => 'Token inválido ou expirado.',
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Senha redefinida com sucesso.'
        ]);
    }
}
