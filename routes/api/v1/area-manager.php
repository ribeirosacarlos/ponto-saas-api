<?php

use App\Http\Controllers\Api\AreaManager\AdjustmentController as AreaManagerAdjustmentController;
use App\Http\Controllers\Api\AreaManager\TimeEntryController as AreaManagerTimeEntryController;
use App\Http\Controllers\Api\Employee\EmployeeOvertimeController;
use Illuminate\Support\Facades\Route;

Route::prefix('area-manager')
    ->middleware(['role:area_manager|manager|admin|super_admin', 'subscription.access'])
    ->group(function () {

        Route::get('/adjustments', [AreaManagerAdjustmentController::class, 'index']);
        Route::get('/team/entries', [AreaManagerTimeEntryController::class, 'teamEntries']);
        Route::get('/team/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);
    });
