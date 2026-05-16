<?php

use App\Http\Controllers\Api\Admin\Billing\CompanySubscriptionController;
use App\Http\Controllers\Api\Admin\Billing\PlanController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Platform\CompanyController;
use App\Http\Controllers\Api\Platform\CompanyRegistrationController;
use App\Http\Controllers\Api\Platform\CompanySettingsController;
use App\Http\Controllers\Api\SuperAdmin\CompanyMetricsController as SuperAdminCompanyMetricsController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminBlogPostController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform')->group(function () {

    Route::middleware(['super_admin'])->group(function () {
        Route::prefix('super-admin')->group(function () {
            Route::get('/dashboard', [SuperAdminDashboardController::class, 'show']);
            Route::get('/companies', [SuperAdminCompanyMetricsController::class, 'index']);
        });

        Route::post('/companies/register', [CompanyRegistrationController::class, 'store']);

        Route::get('/companies/{company}/settings', [CompanySettingsController::class, 'show']);
        Route::put('/companies/{company}/settings', [CompanySettingsController::class, 'update']);
        Route::patch('/companies/{company}/settings', [CompanySettingsController::class, 'update']);
        Route::apiResource('companies', CompanyController::class);
        Route::post('/companies/{company}/restore', [CompanyController::class, 'restore']);
        Route::post('/companies/{company}/block', [CompanyController::class, 'block']);
        Route::post('/companies/{company}/unblock', [CompanyController::class, 'unblock']);
        Route::get('/audit-logs', [AuditLogController::class, 'platformIndex']);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'platformShow']);

        Route::prefix('billing')->group(function () {
            Route::get('plans', [PlanController::class, 'index']);
            Route::post('plans', [PlanController::class, 'store']);
            Route::get('plans/{plan}', [PlanController::class, 'show']);
            Route::patch('plans/{plan}', [PlanController::class, 'update']);

            Route::get('companies/{company}/subscription', [CompanySubscriptionController::class, 'show']);
            Route::patch('companies/{company}/subscription', [CompanySubscriptionController::class, 'update']);
        });

        Route::prefix('blog')->name('admin.blog.')->group(function () {
            Route::get('posts',                [AdminBlogPostController::class, 'index'])->name('posts.index');
            Route::post('posts',               [AdminBlogPostController::class, 'store'])->name('posts.store');
            Route::get('posts/{id}',           [AdminBlogPostController::class, 'show'])->name('posts.show');
            Route::put('posts/{id}',           [AdminBlogPostController::class, 'update'])->name('posts.update');
            Route::delete('posts/{id}',        [AdminBlogPostController::class, 'destroy'])->name('posts.destroy');
            Route::patch('posts/{id}/publish',   [AdminBlogPostController::class, 'publish'])->name('posts.publish');
            Route::patch('posts/{id}/unpublish', [AdminBlogPostController::class, 'unpublish'])->name('posts.unpublish');
        });
    });
});
