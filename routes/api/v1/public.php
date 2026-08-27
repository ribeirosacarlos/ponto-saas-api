<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Billing\PublicCheckoutSessionController;
use App\Http\Controllers\Api\Billing\PublicPlanController;
use App\Http\Controllers\Api\Billing\StripeWebhookController;
use App\Http\Controllers\Api\Commercial\CommercialAffiliateTrackingController;
use App\Http\Controllers\Api\Commercial\CommercialEmailUnsubscribeController;
use App\Http\Controllers\Api\Commercial\CommercialEmailWebhookController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\Platform\CompanyRegistrationController;
use App\Http\Controllers\Api\V1\Public\PublicBlogController;
use App\Http\Controllers\Api\V1\Public\PublicLeadController;
use App\Http\Controllers\InviteController;
use Illuminate\Support\Facades\Route;

Route::prefix('public/blog')->name('public.blog.')->middleware('throttle:public-read')->group(function () {
    Route::get('posts', [PublicBlogController::class, 'index'])->name('posts.index');
    Route::get('posts/{slug}', [PublicBlogController::class, 'show'])->name('posts.show');
    Route::get('categories', [PublicBlogController::class, 'categories'])->name('categories');
});

Route::post('/billing/stripe/webhook', [StripeWebhookController::class, 'handle']);

Route::get('/public/plans', [PublicPlanController::class, 'index'])->middleware('throttle:public-read');

Route::post('/public/companies/register', [CompanyRegistrationController::class, 'store'])
    ->middleware(['throttle:public-company-registration']);

Route::post('/public/billing/checkout-session', [PublicCheckoutSessionController::class, 'store'])
    ->middleware(['throttle:public-billing-checkout-session']);

Route::post('/public/leads', [PublicLeadController::class, 'store'])
    ->middleware(['throttle:public-leads']);

Route::post('/public/leads/optout', [PublicLeadController::class, 'optOut'])
    ->middleware(['throttle:public-leads-optout']);

Route::post('/invites/accept', [InviteController::class, 'accept'])->middleware('throttle:invite-accept');
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware(['throttle:auth-login']);

Route::post('/commercial/track-affiliate-click', [CommercialAffiliateTrackingController::class, 'track'])
    ->middleware(['throttle:public-affiliate-click']);

Route::post('/commercial/email/webhook', [CommercialEmailWebhookController::class, 'handle']);

Route::match(['get', 'post'], '/commercial/email/unsubscribe/{token}', [CommercialEmailUnsubscribeController::class, 'handle'])
    ->middleware(['throttle:public-read'])
    ->name('public.commercial-email.unsubscribe');

Route::post('/auth/affiliate/login', [\App\Http\Controllers\Api\Commercial\CommercialAffiliateAuthController::class, 'login'])
    ->middleware(['throttle:auth-login']);

Route::post('/invites/affiliate/accept', [\App\Http\Controllers\Api\Commercial\AffiliateInviteController::class, 'accept'])
    ->middleware(['throttle:invite-accept']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
    ->middleware('throttle:auth-recovery');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:auth-recovery');

Route::post('/auth/affiliate/forgot-password', [\App\Http\Controllers\Api\Commercial\CommercialAffiliateAuthController::class, 'forgotPassword'])
    ->middleware('throttle:auth-recovery');
Route::post('/auth/affiliate/reset-password', [\App\Http\Controllers\Api\Commercial\CommercialAffiliateAuthController::class, 'resetPassword'])
    ->middleware('throttle:auth-recovery');
