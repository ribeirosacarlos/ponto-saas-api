<?php

use App\Console\Commands\BillingMarkPastDue;
use App\Console\Commands\BillingSyncSubscriptions;
use App\Console\Commands\Commercial\DispatchDueCommercialSequenceEmails;
use App\Console\Commands\Commercial\ReportCommercialEmailEnrollments;
use App\Console\Commands\Commercial\ReportCommercialEmailSends;
use App\Console\Commands\Commercial\SendTestCommercialEmail;
use App\Console\Commands\NormalizeDocumentPaths;
use App\Console\Commands\SyncExpiredCompanyAccess;
use App\Http\Middleware\SetCompanyTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\IdentifyTenant::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'super_admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'subscription.active' => \App\Http\Middleware\EnsureSubscriptionTrialOrActive::class,
            'subscription.access' => \App\Http\Middleware\EnsureCompanyHasAccess::class,
            'plan.feature' => \App\Http\Middleware\EnsurePlanFeature::class,
            'company.timezone' => SetCompanyTimezone::class,
            'company.audit_logs_enabled' => \App\Http\Middleware\EnsureCompanyAuditLogsEnabled::class,
            'affiliate' => \App\Http\Middleware\EnsureIsAffiliate::class,
            'swagger.auth' => \App\Http\Middleware\SwaggerBasicAuth::class,
        ]);
        $middleware->prepend(HandleCors::class);
    })
    ->withCommands([
        BillingSyncSubscriptions::class,
        BillingMarkPastDue::class,
        SyncExpiredCompanyAccess::class,
        NormalizeDocumentPaths::class,
        DispatchDueCommercialSequenceEmails::class,
        SendTestCommercialEmail::class,
        ReportCommercialEmailSends::class,
        ReportCommercialEmailEnrollments::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
