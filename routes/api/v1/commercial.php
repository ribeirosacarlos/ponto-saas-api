<?php

use App\Http\Controllers\Api\Commercial\CommercialAffiliateController;
use App\Http\Controllers\Api\Commercial\CommercialCommissionController;
use App\Http\Controllers\Api\Commercial\CommercialDashboardController;
use App\Http\Controllers\Api\Commercial\CommercialLeadController;
use App\Http\Controllers\Api\Commercial\CommercialLeadStepController;
use Illuminate\Support\Facades\Route;

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

            Route::get('commissions', [CommercialCommissionController::class, 'index']);
            Route::post('commissions/{id}/approve', [CommercialCommissionController::class, 'approve']);
            Route::post('commissions/{id}/mark-paid', [CommercialCommissionController::class, 'markPaid']);
            Route::get('affiliate-bonuses', [CommercialCommissionController::class, 'bonuses']);
        });
    });
