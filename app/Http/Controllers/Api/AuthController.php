<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Support\CompanyTime;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $user = User::with('roles')->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'roles' => $user->roles->pluck('name'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout feito com sucesso']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['roles', 'company']);
        $timezone = CompanyTime::resolveTimezone($user->company);
        $userPayload = $this->buildUserPayload($user, $timezone);

        return response()->json([
            'timezone' => $timezone,
            'timeZone' => $timezone,
            'data' => [
                'user' => $userPayload,
                'roles' => $user->roles->pluck('name'),
                'timezone' => $timezone,
                'timeZone' => $timezone,
            ],
        ]);
    }

    private function buildUserPayload(User $user, string $timezone): array
    {
        $payload = $user->toArray();
        $payload['timezone'] = $timezone;
        $payload['timeZone'] = $timezone;

        if (isset($payload['company']) && is_array($payload['company'])) {
            $companyTimezone = $payload['company']['timezone'] ?? $timezone;
            $payload['company']['timezone'] = $companyTimezone;
            $payload['company']['timeZone'] = $companyTimezone;
        }

        return $payload;
    }
}
