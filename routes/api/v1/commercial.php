<?php

use App\Http\Controllers\Api\AffiliatePortal\AffiliatePortalCommissionController;
use App\Http\Controllers\Api\AffiliatePortal\AffiliatePortalController;
use App\Http\Controllers\Api\AffiliatePortal\AffiliatePortalLeadController;
use App\Http\Controllers\Api\Commercial\CommercialAffiliateController;
use App\Http\Controllers\Api\Commercial\CommercialCommissionController;
use App\Http\Controllers\Api\Commercial\CommercialDashboardController;
use App\Http\Controllers\Api\Commercial\CommercialLeadController;
use App\Http\Controllers\Api\Commercial\CommercialLeadStepController;
use Illuminate\Support\Facades\Route;

Route::prefix('affiliate')
    ->middleware(['auth:sanctum', 'affiliate'])
    ->group(function () {
        Route::get('me', [\App\Http\Controllers\Api\Commercial\CommercialAffiliateAuthController::class, 'me']);
        Route::post('logout', [\App\Http\Controllers\Api\Commercial\CommercialAffiliateAuthController::class, 'logout']);
    });

Route::prefix('affiliate-portal')
    ->middleware(['auth:sanctum', 'affiliate'])
    ->group(function () {
        Route::get('me', [AffiliatePortalController::class, 'me']);
        Route::get('dashboard', [AffiliatePortalController::class, 'dashboard']);

        Route::get('leads', [AffiliatePortalLeadController::class, 'index']);
        Route::post('leads', [AffiliatePortalLeadController::class, 'store']);
        Route::get('leads/{id}', [AffiliatePortalLeadController::class, 'show']);
        Route::put('leads/{id}', [AffiliatePortalLeadController::class, 'update']);
        Route::post('leads/{id}/notes', [AffiliatePortalLeadController::class, 'addNote']);
        Route::post('leads/{id}/next-action', [AffiliatePortalLeadController::class, 'nextAction']);
        Route::post('leads/{id}/move-step', [AffiliatePortalLeadController::class, 'moveStep']);
        Route::post('leads/{id}/mark-won', [AffiliatePortalLeadController::class, 'markWon']);
        Route::post('leads/{id}/mark-lost', [AffiliatePortalLeadController::class, 'markLost']);

        Route::get('commissions', [AffiliatePortalCommissionController::class, 'commissions']);
        Route::get('bonuses', [AffiliatePortalCommissionController::class, 'bonuses']);

        Route::get('steps', [CommercialLeadStepController::class, 'index']);
    });

Route::prefix('admin/commercial')
    ->middleware(['role:super_admin|commercial_manager|commercial_agent'])
    ->group(function () {
        Route::get('dashboard', [CommercialDashboardController::class, 'show']);

        Route::get('leads', [CommercialLeadController::class, 'index']);
        Route::post('leads', [CommercialLeadController::class, 'store']);
        Route::get('leads/{id}', [CommercialLeadController::class, 'show']);
        Route::put('leads/{id}', [CommercialLeadController::class, 'update']);
        Route::delete('leads/{id}', [CommercialLeadController::class, 'destroy']);
        Route::post('leads/{id}/assign', [CommercialLeadController::class, 'assign']);
        Route::post('leads/{id}/move-step', [CommercialLeadController::class, 'moveStep']);
        Route::post('leads/{id}/notes', [CommercialLeadController::class, 'addNote']);
        Route::post('leads/{id}/next-action', [CommercialLeadController::class, 'nextAction']);
        Route::post('leads/{id}/mark-won', [CommercialLeadController::class, 'markWon']);
        Route::post('leads/{id}/mark-lost', [CommercialLeadController::class, 'markLost']);

        Route::get('steps', [CommercialLeadStepController::class, 'index']);

        Route::middleware(['role:super_admin|commercial_manager'])->group(function () {
            Route::post('steps', [CommercialLeadStepController::class, 'store']);
            Route::put('steps/{id}', [CommercialLeadStepController::class, 'update']);
            Route::delete('steps/{id}', [CommercialLeadStepController::class, 'destroy']);
            Route::post('steps/reorder', [CommercialLeadStepController::class, 'reorder']);

            Route::apiResource('affiliates', CommercialAffiliateController::class);
            Route::get('affiliates/{id}/metrics', [CommercialAffiliateController::class, 'metrics']);
            Route::post('affiliates/{id}/resend-invite', [CommercialAffiliateController::class, 'resendInvite'])
                ->middleware('throttle:email-actions');

            Route::get('commissions', [CommercialCommissionController::class, 'index']);
            Route::post('commissions/{id}/approve', [CommercialCommissionController::class, 'approve']);
            Route::post('commissions/{id}/mark-paid', [CommercialCommissionController::class, 'markPaid']);
            Route::get('affiliate-bonuses', [CommercialCommissionController::class, 'bonuses']);
        });
    });
