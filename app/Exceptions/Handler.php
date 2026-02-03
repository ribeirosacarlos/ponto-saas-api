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

    public function render($request, Throwable $e)
    {
        if ($e instanceof AuthenticationException
            && ($request->expectsJson() || $request->is('api/*') || $request->is('v1/*')))
        {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return parent::render($request, $e);
    }

    protected function formatAuthorizationFailure(Request $request, Throwable $e): JsonResponse
    {
        $context = $this->buildContext($request, $e);

        // Identify the type of authorization failure
        $failureType = $this->identifyAuthorizationFailureType($request, $e);

        Log::warning('authorization failure', array_merge($context, [
            'failure_type' => $failureType,
            'original_message' => $e->getMessage(),
        ]));

        return response()->json([
            'message' => 'Não autorizado para essa ação.',
            'details' => [
                'method' => $context['method'],
                'path' => $context['path'],
                'route_action' => $context['route_action'],
                'route_name' => $context['route_name'],
            ],
        ], 403);
    }

    protected function buildContext(Request $request, Throwable $e): array
    {
        $roles = $request->user()?->roles->pluck('name')->toArray() ?? [];
        $company = $request->user()?->company;

        return [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'user_id' => $request->user()?->id,
            'roles' => $roles,
            'method' => $request->method(),
            'path' => $request->path(),
            'route_action' => Route::currentRouteAction(),
            'route_name' => Route::currentRouteName(),
        ];
    }

    /**
     * Identify the type of authorization failure based on exception message and context
     */
    protected function identifyAuthorizationFailureType(Request $request, Throwable $e): string
    {
        $message = $e->getMessage();
        $user = $request->user();
        $company = $user?->company;

        // Check for subscription-related failures
        if (str_contains($message, 'assinatura ativa') ||
            str_contains($message, 'subscription') ||
            str_contains($message, 'trial') ||
            str_contains($message, 'payment')) {
            return 'subscription_access_denied';
        }

        // Check for role-related failures
        if ($message === 'Forbidden.' || str_contains($message, 'role')) {
            return 'insufficient_role_permissions';
        }

        // Check if user has no company
        if (! $company) {
            return 'no_company_assigned';
        }

        // Check if company is blocked
        if ($company->is_blocked) {
            return 'company_blocked';
        }

        // Check subscription status
        $subscription = $company->subscription;
        if ($subscription) {
            if ($subscription->isPastDue() && $subscription->isBlocked()) {
                return 'subscription_past_due_blocked';
            }
            if ($subscription->isCanceled()) {
                return 'subscription_canceled';
            }
            if (! $subscription->isActive() && ! $subscription->isTrialing()) {
                return 'subscription_inactive';
            }
        }

        // Default fallback
        return 'unknown_authorization_failure';
    }
}
