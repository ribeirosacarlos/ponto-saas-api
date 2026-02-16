<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Billing\CheckoutSessionController;
use App\Http\Controllers\Api\Billing\PortalController;
use App\Http\Controllers\Api\Settings\CompanySettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::get('/settings/overview', [CompanySettingsController::class, 'overview']);

Route::prefix('billing')->group(function () {
    Route::post('checkout-session', [CheckoutSessionController::class, 'store']);
    Route::post('portal', [PortalController::class, 'store']);
});
