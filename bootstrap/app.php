<?php

use App\Console\Commands\BillingMarkPastDue;
use App\Console\Commands\BillingSyncSubscriptions;
use App\Console\Commands\NormalizeDocumentPaths;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\SetCompanyTimezone;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
        ->withMiddleware(function (Middleware $middleware) {
            $middleware->appendToGroup('api', [
                \App\Http\Middleware\IdentifyTenant::class,
            ]);
            $middleware->alias([
                'role' => \App\Http\Middleware\RoleMiddleware::class,
                'subscription.active' => \App\Http\Middleware\EnsureSubscriptionTrialOrActive::class,
                'subscription.access' => \App\Http\Middleware\EnsureCompanyHasAccess::class,
                'plan.feature' => \App\Http\Middleware\EnsurePlanFeature::class,
                'company.timezone' => SetCompanyTimezone::class,
            ]);
            $middleware->prepend(HandleCors::class);
        })
    ->withCommands([
        BillingSyncSubscriptions::class,
        BillingMarkPastDue::class,
        NormalizeDocumentPaths::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
