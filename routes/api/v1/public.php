<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Billing\PublicCheckoutSessionController;
use App\Http\Controllers\Api\Billing\PublicPlanController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\Platform\CompanyRegistrationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\InviteController;
use Illuminate\Support\Facades\Route;

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
