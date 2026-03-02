<?php

use App\Http\Controllers\Api\Employee\AnnouncementController as EmployeeAnnouncementController;
use App\Http\Controllers\Api\Employee\EmployeeOvertimeController;
use App\Http\Controllers\Api\Employee\EmployeeWorkedTodayController;
use App\Http\Controllers\Api\Employee\AbsenceController as EmployeeAbsenceController;
use App\Http\Controllers\Api\Employee\TimeEntryAdjustmentController as EmployeeTimeEntryAdjustmentController;
use App\Http\Controllers\Api\Employee\TimeEntryController as EmployeeTimeEntryController;
use App\Http\Controllers\Api\Employee\VacationController as EmployeeVacationController;
use Illuminate\Support\Facades\Route;

Route::prefix('employee')
    ->middleware(['role:employee|area_manager|manager|admin', 'subscription.access'])
    ->group(function () {

        // Registrar batida
        Route::post('/clock', [EmployeeTimeEntryController::class, 'clock']);

        // Listar batidas do próprio usuário
        Route::get('/entries', [EmployeeTimeEntryController::class, 'myEntries']);
        Route::get('/entries/history', [EmployeeTimeEntryController::class, 'history']);

        Route::get('/time-entries/open-status', [EmployeeTimeEntryController::class, 'openStatus']);
        Route::get('/shift', [EmployeeTimeEntryController::class, 'shift']);
        Route::get('/worked-today', [EmployeeWorkedTodayController::class, 'show']);

        // Solicitar ajuste
        Route::post('/time-entries/{timeEntry}/adjustment', [EmployeeTimeEntryAdjustmentController::class, 'store'])
            ->name('employee.time_entries.adjustment.store');

        // Férias
        Route::get('/vacations', [EmployeeVacationController::class, 'index']);
        Route::post('/vacations', [EmployeeVacationController::class, 'store']);
        Route::get('/vacations/balance', [EmployeeVacationController::class, 'balance']);
        Route::delete('/vacations/{vacation}', [EmployeeVacationController::class, 'destroy']);
        Route::get('/absences', [EmployeeAbsenceController::class, 'index']);

        Route::get('/announcements', [EmployeeAnnouncementController::class, 'index']);
        Route::get('/announcements/pending-count', [EmployeeAnnouncementController::class, 'pendingCount']);
        Route::get('/announcements/{announcement}', [EmployeeAnnouncementController::class, 'show']);
        Route::post('/announcements/{announcement}/seen', [EmployeeAnnouncementController::class, 'markAsSeen']);

        Route::get('/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);
    });
