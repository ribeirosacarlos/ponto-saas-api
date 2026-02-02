<?php

use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\Api\Billing\CheckoutSessionController;
use App\Http\Controllers\Api\Billing\PortalController;
use App\Http\Controllers\Api\Billing\PublicPlanController;
use App\Http\Controllers\Api\Billing\PublicCheckoutSessionController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\Documents\DocumentController;
use App\Http\Controllers\Api\Employee\EmployeeWorkedTodayController;
use App\Http\Controllers\Api\Employee\TimeEntryController as EmployeeTimeEntryController;
use App\Http\Controllers\Api\Employee\AdjustmentController as EmployeeAdjustmentController;
use App\Http\Controllers\Api\Employee\VacationController as EmployeeVacationController;
use App\Http\Controllers\Api\Employee\AnnouncementController as EmployeeAnnouncementController;

use App\Http\Controllers\Api\AreaManager\TimeEntryController as AreaManagerTimeEntryController;
use App\Http\Controllers\Api\AreaManager\AdjustmentController as AreaManagerAdjustmentController;

use App\Http\Controllers\Api\Admin\Billing\CompanySubscriptionController;
use App\Http\Controllers\Api\Admin\Billing\PlanController;
use App\Http\Controllers\Api\Admin\CompanyTimezoneController;
use App\Http\Controllers\Api\Admin\DocumentReviewController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\EmployeeOvertimeController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\HolidayController;
use App\Http\Controllers\Api\Admin\VacationController as AdminVacationController;
use App\Http\Controllers\Api\Admin\LeavePolicyController;
use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Platform\CompanyController;
use App\Http\Controllers\Api\Platform\CompanyRegistrationController;
use App\Http\Controllers\Api\Settings\CompanySettingsController;

Route::prefix('v1')->group(function () {

    Route::post('/billing/stripe/webhook', [StripeWebhookController::class, 'handle']);

    Route::get('/public/plans', [PublicPlanController::class, 'index']);

    Route::post('/public/companies/register', [CompanyRegistrationController::class, 'store'])
        ->middleware(['throttle:public-company-registration']);

    Route::post('/public/billing/checkout-session', [PublicCheckoutSessionController::class, 'store'])
        ->middleware(['throttle:public-billing-checkout-session']);

    Route::post('/invites/accept', [InviteController::class, 'accept']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:5,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/settings/overview', [CompanySettingsController::class, 'overview']);

        Route::middleware(['role:employee|area_manager|manager|admin', 'subscription.access'])->group(function () {
            Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::get('/documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');
            Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
            Route::post('/documents/{document}/resend', [DocumentController::class, 'resend'])->name('documents.resend');
            Route::patch('/documents/{document}', [DocumentController::class, 'update'])
                ->name('documents.update')
                ->middleware('role:admin|manager|area_manager');
            Route::patch('/documents/{document}/approve', [DocumentController::class, 'approve'])
                ->name('documents.approve')
                ->middleware('role:admin|manager|area_manager');
            Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        });

        Route::prefix('billing')->group(function () {
            Route::post('checkout-session', [CheckoutSessionController::class, 'store']);
            Route::post('portal', [PortalController::class, 'store']);
        });

        // EMPLOYEE
        Route::prefix('employee')
            ->middleware(['role:employee|area_manager|manager|admin', 'subscription.access'])
            ->group(function () {

                // Registrar batida
                Route::post('/clock', [EmployeeTimeEntryController::class, 'clock']);

                // Listar batidas do próprio usuário
                Route::get('/entries', [EmployeeTimeEntryController::class, 'myEntries']);

                Route::get('/time-entries/open-status', [EmployeeTimeEntryController::class, 'openStatus']);
                Route::get('/shift', [EmployeeTimeEntryController::class, 'shift']);
                Route::get('/worked-today', [EmployeeWorkedTodayController::class, 'show']);

                // Solicitar ajuste
                Route::post('/adjustments', [EmployeeAdjustmentController::class, 'request']);

                // Férias
                Route::get('/vacations', [EmployeeVacationController::class, 'index']);
                Route::post('/vacations', [EmployeeVacationController::class, 'store']);
                Route::get('/vacations/balance', [EmployeeVacationController::class, 'balance']);
                Route::delete('/vacations/{vacation}', [EmployeeVacationController::class, 'destroy']);

                Route::get('/announcements', [EmployeeAnnouncementController::class, 'index']);
                Route::get('/announcements/pending-count', [EmployeeAnnouncementController::class, 'pendingCount']);
                Route::get('/announcements/{announcement}', [EmployeeAnnouncementController::class, 'show']);
                Route::post('/announcements/{announcement}/seen', [EmployeeAnnouncementController::class, 'markAsSeen']);
            });


        // AREA MANAGER
        Route::prefix('area-manager')
            ->middleware(['role:area_manager|manager|admin', 'subscription.access'])
            ->group(function () {

                // Listar solicitações de ajuste
                Route::get('/adjustments', [AreaManagerAdjustmentController::class, 'index']);

                // Aprovar ajustes
                Route::post('/adjustments/{id}/approve', [AreaManagerAdjustmentController::class, 'approve']);
                Route::post('/adjustments/{id}/reject',  [AreaManagerAdjustmentController::class, 'reject']);
                
                // Ver batidas da equipe
                Route::get('/team/entries', [AreaManagerTimeEntryController::class, 'teamEntries']);
                Route::get('/team/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);
            });


        // ADMIN AREA
        Route::prefix('admin')->group(function () {

            Route::middleware(['role:admin|super_admin', 'subscription.access'])->group(function () {
                Route::get('/company/timezone', [CompanyTimezoneController::class, 'show']);
                Route::put('/company/timezone', [CompanyTimezoneController::class, 'update']);
            });

            Route::middleware(['role:admin|manager|area_manager', 'subscription.access'])->group(function () {

                // Documentos pendentes / revisão
                Route::get('/documents/pending', [DocumentReviewController::class, 'pending'])
                    ->name('admin.documents.pending');
                Route::get('/documents/review', [DocumentReviewController::class, 'review'])
                    ->name('admin.documents.review');
                Route::get('/documents/{document}', [DocumentReviewController::class, 'show'])
                    ->name('admin.documents.show');
                Route::patch('/documents/{document}/approve', [DocumentReviewController::class, 'approve'])
                    ->name('admin.documents.approve');
                Route::patch('/documents/{document}/reject', [DocumentReviewController::class, 'reject'])
                    ->name('admin.documents.reject');

                // Funcionários
                Route::apiResource('employees', EmployeeController::class);
                Route::get('/employees/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);

                // Relatórios
                Route::get('/reports/time', [ReportController::class, 'timeReport'])
                    ->middleware('plan.feature:reports');
            });

            Route::middleware(['role:area_manager|manager|admin', 'subscription.access'])->group(function () {

                // Jornadas
                Route::apiResource('shifts', ShiftController::class);
                Route::get('/users/{user}/shifts', [ShiftController::class, 'byUser']);
                Route::post('/employees/{employee}/shift', [EmployeeController::class, 'assignShift']);

                // Feriados
                Route::apiResource('holidays', HolidayController::class)->except(['create', 'edit']);

                // Férias
                Route::get('/vacations', [AdminVacationController::class, 'index']);
                Route::post('/vacations', [AdminVacationController::class, 'store']);
                Route::post('/vacations/{vacation}/approve', [AdminVacationController::class, 'approve']);
                Route::post('/vacations/{vacation}/reject', [AdminVacationController::class, 'reject']);
                Route::delete('/vacations/{vacation}', [AdminVacationController::class, 'destroy']);
                Route::get('/vacations/balance/{employee}', [AdminVacationController::class, 'balance']);

                // Políticas de férias
                Route::apiResource('leave-policies', LeavePolicyController::class)->only(['index', 'store', 'update', 'destroy']);

                Route::apiResource('announcements', AdminAnnouncementController::class);
            });

        });

        Route::prefix('platform')->group(function () {

            Route::middleware(['role:super_admin'])->group(function () {
                Route::post('/companies/register', [CompanyRegistrationController::class, 'store']);

                Route::apiResource('companies', CompanyController::class);
                Route::post('/companies/{company}/restore', [CompanyController::class, 'restore']);
                Route::post('/companies/{company}/block', [CompanyController::class, 'block']);
                Route::post('/companies/{company}/unblock', [CompanyController::class, 'unblock']);

                Route::prefix('billing')->group(function () {
                    Route::get('plans', [PlanController::class, 'index']);
                    Route::post('plans', [PlanController::class, 'store']);
                    Route::get('plans/{plan}', [PlanController::class, 'show']);
                    Route::patch('plans/{plan}', [PlanController::class, 'update']);

                    Route::get('companies/{company}/subscription', [CompanySubscriptionController::class, 'show']);
                    Route::patch('companies/{company}/subscription', [CompanySubscriptionController::class, 'update']);
                });
            });
        });


        // DEBUG / TESTE DO TENANT
        Route::get('/tenant-check', function (\App\Services\TenantManager $tm) {
            return [
                'tenant' => $tm->tenant()?->slug,
                'tenant_name' => $tm->tenant()?->name,
            ];
        });

    });
});

Route::get('/db-test', function () {
    try {
        \DB::connection()->getPdo();
        return ['status' => 'ok', 'message' => 'Conexão com banco funcionando!'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
});
