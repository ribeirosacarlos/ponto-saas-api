<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * $roles: pipe-separated roles passed in middleware e.g. role:admin|manager
     */
    public function handle(Request $request, Closure $next, $roles)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user instanceof User) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        $roles = explode('|', $roles);

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
