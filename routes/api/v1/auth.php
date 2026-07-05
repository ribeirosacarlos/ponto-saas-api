<?php

use App\Http\Controllers\Api\Admin\HolidayController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Billing\CheckoutSessionController;
use App\Http\Controllers\Api\Billing\PortalController;
use App\Http\Controllers\Api\Settings\CompanySettingsController;
use App\Http\Controllers\Api\Settings\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::get('/settings/overview', [CompanySettingsController::class, 'overview']);
Route::get('/settings/subscription', [SubscriptionController::class, 'show']);
Route::post('/settings/subscription/cancel', [SubscriptionController::class, 'cancel'])
    ->middleware('throttle:sensitive-admin');

Route::prefix('billing')->group(function () {
    Route::post('checkout-session', [CheckoutSessionController::class, 'store'])->middleware('throttle:sensitive-admin');
    Route::post('portal', [PortalController::class, 'store'])->middleware('throttle:sensitive-admin');
});

// Compatibilidade com clientes anteriores à reorganização das rotas administrativas.
Route::middleware(['role:admin|super_admin', 'subscription.access'])->group(function () {
    Route::get('/settings/location', [\App\Http\Controllers\Api\Settings\CompanyLocationSettingsController::class, 'show']);
    Route::put('/settings/location', [\App\Http\Controllers\Api\Settings\CompanyLocationSettingsController::class, 'update']);
    Route::patch('/settings/location', [\App\Http\Controllers\Api\Settings\CompanyLocationSettingsController::class, 'update']);
});

Route::get('/holidays', [HolidayController::class, 'index']);
Route::get('/holidays/{holiday}', [HolidayController::class, 'show']);
