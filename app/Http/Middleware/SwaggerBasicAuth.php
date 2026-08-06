<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = config('l5-swagger.auth.username');
        $password = config('l5-swagger.auth.password');

        if (blank($username) || blank($password)) {
            abort(503, 'API documentation is not configured.');
        }

        $providedUser = $request->getUser();
        $providedPassword = $request->getPassword();

        $validUser = is_string($providedUser) && hash_equals($username, $providedUser);
        $validPassword = is_string($providedPassword) && hash_equals($password, $providedPassword);

        if (! $validUser || ! $validPassword) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="API Documentation"',
            ]);
        }

        return $next($request);
    }
}
