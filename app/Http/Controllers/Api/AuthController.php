<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use App\Support\CompanyTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$18pb3gxLga6ZoWmC0Fo.XeBNu/o77g6f.eLXTWh3CWfRlK9wvz3TW';

    public function login(LoginRequest $request)
    {
        $user = User::with(['roles', 'company'])
            ->where('email', strtolower($request->string('email')->toString()))
            ->first();

        $passwordMatches = Hash::check(
            $request->string('password')->toString(),
            $user?->password ?? self::DUMMY_PASSWORD_HASH
        );

        if (! $user || ! $passwordMatches) {
            return response()->json(['message' => 'Credenciais inválidas'], 401);
        }

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'user' => (new AuthenticatedUserResource($user))->resolve($request),
            'roles' => $user->roles->pluck('name'),
            'token' => $token,
            'expires_in' => config('sanctum.expiration') * 60,
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
        $userPayload = (new AuthenticatedUserResource($user))->resolve($request);

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
}
