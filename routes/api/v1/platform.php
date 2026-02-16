<?php

use App\Http\Controllers\Api\Admin\Billing\CompanySubscriptionController;
use App\Http\Controllers\Api\Admin\Billing\PlanController;
use App\Http\Controllers\Api\Platform\CompanyController;
use App\Http\Controllers\Api\Platform\CompanyRegistrationController;
use Illuminate\Support\Facades\Route;

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
