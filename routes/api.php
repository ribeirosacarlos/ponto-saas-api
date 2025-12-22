<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\Api\Employee\EmployeeWorkedTodayController;
use App\Http\Controllers\Api\Employee\TimeEntryController as EmployeeTimeEntryController;
use App\Http\Controllers\Api\Employee\AdjustmentController as EmployeeAdjustmentController;
use App\Http\Controllers\Api\Employee\VacationController as EmployeeVacationController;

use App\Http\Controllers\Api\AreaManager\TimeEntryController as AreaManagerTimeEntryController;
use App\Http\Controllers\Api\AreaManager\AdjustmentController as AreaManagerAdjustmentController;

use App\Http\Controllers\Api\Admin\Billing\CompanySubscriptionController;
use App\Http\Controllers\Api\Admin\Billing\PlanController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\HolidayController;
use App\Http\Controllers\Api\Admin\VacationController as AdminVacationController;
use App\Http\Controllers\Api\Admin\LeavePolicyController;
use App\Http\Controllers\Api\Platform\CompanyController;
use App\Http\Controllers\Api\Platform\CompanyRegistrationController;

Route::prefix('v1')->group(function () {

    Route::post('/invites/accept', [InviteController::class, 'accept']);

    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // EMPLOYEE
        Route::prefix('employee')
            ->middleware(['role:employee|area_manager|manager|admin', 'subscription.active'])
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
            });


        // AREA MANAGER
        Route::prefix('area-manager')
            ->middleware(['role:area_manager|manager|admin', 'subscription.active'])
            ->group(function () {

                // Ver batidas da equipe
                Route::get('/team/entries', [AreaManagerTimeEntryController::class, 'teamEntries']);

                // Aprovar ajustes
                Route::post('/adjustments/{id}/approve', [AreaManagerAdjustmentController::class, 'approve']);
                Route::post('/adjustments/{id}/reject',  [AreaManagerAdjustmentController::class, 'reject']);
            });


        // ADMIN AREA
        Route::prefix('admin')->group(function () {

            Route::middleware(['role:admin', 'subscription.active'])->group(function () {

                // Funcionários
                Route::apiResource('employees', EmployeeController::class);

                // Relatórios
                Route::get('/reports/time', [ReportController::class, 'timeReport'])
                    ->middleware('plan.feature:reports');
            });

            Route::middleware(['role:area_manager|manager|admin', 'subscription.active'])->group(function () {

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
            });

            Route::middleware(['role:super_admin'])->group(function () {
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

        Route::prefix('platform')->group(function () {

            Route::middleware(['role:super_admin'])->group(function () {
                Route::post('/companies/register', [CompanyRegistrationController::class, 'store']);
            });

            Route::middleware(['role:super_admin'])->group(function () {
                Route::apiResource('companies', CompanyController::class);
                Route::post('/companies/{company}/restore', [CompanyController::class, 'restore']);
                Route::post('/companies/{company}/block', [CompanyController::class, 'block']);
                Route::post('/companies/{company}/unblock', [CompanyController::class, 'unblock']);
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
