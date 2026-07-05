<?php

use App\Http\Controllers\Api\Employee\AbsenceController as EmployeeAbsenceController;
use App\Http\Controllers\Api\Employee\AdjustmentController as EmployeeAdjustmentController;
use App\Http\Controllers\Api\Employee\AnnouncementController as EmployeeAnnouncementController;
use App\Http\Controllers\Api\Employee\EmployeeOvertimeController;
use App\Http\Controllers\Api\Employee\EmployeeWorkedTodayController;
use App\Http\Controllers\Api\Employee\MedicalCertificateController as EmployeeMedicalCertificateController;
use App\Http\Controllers\Api\Employee\ProfileController as EmployeeProfileController;
use App\Http\Controllers\Api\Employee\TimeEntryAdjustmentController as EmployeeTimeEntryAdjustmentController;
use App\Http\Controllers\Api\Employee\TimeEntryController as EmployeeTimeEntryController;
use App\Http\Controllers\Api\Employee\TimesheetController as EmployeeTimesheetController;
use App\Http\Controllers\Api\Employee\TimesheetPdfController as EmployeeTimesheetPdfController;
use App\Http\Controllers\Api\Employee\VacationController as EmployeeVacationController;
use Illuminate\Support\Facades\Route;

Route::prefix('employee')
    ->middleware(['role:employee|area_manager|manager|admin', 'subscription.access'])
    ->group(function () {

        // Perfil do usuário autenticado
        Route::patch('/profile', [EmployeeProfileController::class, 'update']);
        Route::put('/password', [EmployeeProfileController::class, 'updatePassword']);

        // Registrar batida
        Route::post('/clock', [EmployeeTimeEntryController::class, 'clock'])->middleware('throttle:clock');

        // Listar batidas do próprio usuário
        Route::get('/entries', [EmployeeTimeEntryController::class, 'myEntries']);
        Route::get('/entries/history', [EmployeeTimeEntryController::class, 'history']);

        Route::get('/time-entries/open-status', [EmployeeTimeEntryController::class, 'openStatus']);
        Route::get('/shift', [EmployeeTimeEntryController::class, 'shift']);
        Route::get('/worked-today', [EmployeeWorkedTodayController::class, 'show']);

        // Ajustes do próprio usuário
        Route::get('/adjustments', [EmployeeAdjustmentController::class, 'index']);

        // Solicitar ajuste
        Route::post('/time-entries/{timeEntry}/adjustment', [EmployeeTimeEntryAdjustmentController::class, 'store'])
            ->name('employee.time_entries.adjustment.store');

        // Férias
        Route::get('/vacations', [EmployeeVacationController::class, 'index']);
        Route::post('/vacations', [EmployeeVacationController::class, 'store']);
        Route::get('/vacations/balance', [EmployeeVacationController::class, 'balance']);
        Route::delete('/vacations/{vacation}', [EmployeeVacationController::class, 'destroy']);
        Route::get('/absences', [EmployeeAbsenceController::class, 'index']);
        Route::get('/medical-certificates', [EmployeeMedicalCertificateController::class, 'index']);
        Route::post('/medical-certificates', [EmployeeMedicalCertificateController::class, 'store']);
        Route::get('/medical-certificates/{absence}', [EmployeeMedicalCertificateController::class, 'show']);
        Route::delete('/medical-certificates/{absence}', [EmployeeMedicalCertificateController::class, 'destroy']);

        Route::get('/announcements', [EmployeeAnnouncementController::class, 'index']);
        Route::get('/announcements/pending-count', [EmployeeAnnouncementController::class, 'pendingCount']);
        Route::get('/announcements/{announcement}', [EmployeeAnnouncementController::class, 'show']);
        Route::post('/announcements/{announcement}/seen', [EmployeeAnnouncementController::class, 'markAsSeen']);

        Route::get('/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);

        Route::get('/timesheets', [EmployeeTimesheetController::class, 'index']);
        Route::get('/timesheets/{timesheet}', [EmployeeTimesheetController::class, 'show']);
        Route::post('/timesheets/{timesheet}/sign', [EmployeeTimesheetController::class, 'sign']);
        Route::post('/timesheets/{timesheet}/dispute', [EmployeeTimesheetController::class, 'dispute']);
        Route::get('/timesheets/{timesheet}/pdf', [EmployeeTimesheetPdfController::class, 'download'])->middleware('throttle:exports');
    });
