<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        AuthorizationException::class,
        AccessDeniedHttpException::class,
    ];

    /**
     * The inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (AuthorizationException $exception, Request $request): JsonResponse {
            return $this->formatAuthorizationFailure($request, $exception);
        });

        $this->renderable(function (AccessDeniedHttpException $exception, Request $request): JsonResponse {
            return $this->formatAuthorizationFailure($request, $exception);
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException
            && ($request->expectsJson() || $request->is('api/*') || $request->is('v1/*')))
        {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return parent::render($request, $exception);
    }

    protected function formatAuthorizationFailure(Request $request, Throwable $exception): JsonResponse
    {
        $context = $this->buildContext($request, $exception);

        Log::warning('authorization failure', $context);

        return response()->json([
            'message' => 'Não autorizado para essa ação.',
        ], 403);
    }

    protected function buildContext(Request $request, Throwable $exception): array
    {
        $roles = $request->user()?->roles->pluck('name')->toArray() ?? [];

        return [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'user_id' => $request->user()?->id,
            'roles' => $roles,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_action' => Route::currentRouteAction(),
            'route_name' => Route::currentRouteName(),
        ];
    }
}
