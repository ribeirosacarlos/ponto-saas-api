<?php

use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\CompanyTimezoneController;
use App\Http\Controllers\Api\Admin\DocumentReviewController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\AbsenceController as AdminAbsenceController;
use App\Http\Controllers\Api\Admin\Billing\ExtraEmployeeSyncController;
use App\Http\Controllers\Api\Admin\HolidayController;
use App\Http\Controllers\Api\Admin\LeavePolicyController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\TimeEntryAdjustmentController as AdminTimeEntryAdjustmentController;
use App\Http\Controllers\Api\Admin\VacationController as AdminVacationController;
use App\Http\Controllers\Api\Employee\EmployeeOvertimeController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {

    Route::middleware(['role:admin|super_admin', 'subscription.access'])->group(function () {
        Route::get('/company/timezone', [CompanyTimezoneController::class, 'show']);
        Route::put('/company/timezone', [CompanyTimezoneController::class, 'update']);
        Route::post('/billing/extra-employees/sync', [ExtraEmployeeSyncController::class, 'store']);
    });

    Route::middleware(['role:admin|manager|area_manager', 'subscription.access'])->group(function () {

        Route::get('/documents/pending', [DocumentReviewController::class, 'pending'])
            ->name('admin.documents.pending');
        Route::get('/documents/review', [DocumentReviewController::class, 'review'])
            ->name('admin.documents.review');
        Route::get('/documents/{document}', [DocumentReviewController::class, 'show'])
            ->name('admin.documents.show');
        Route::patch('/documents/{document}/approve', [DocumentReviewController::class, 'approve'])
            ->name('admin.documents.approve');
        Route::patch('/documents/{document}/reject', [DocumentReviewController::class, 'reject'])
            ->name('admin.documents.reject');

        Route::post('/documents/upload-for-employee', [DocumentReviewController::class, 'uploadForEmployee'])
            ->name('admin.documents.upload_for_employee');

        Route::apiResource('employees', EmployeeController::class);
        Route::get('/employees/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);

        Route::get('/reports/time', [ReportController::class, 'timeReport'])
            ->middleware('plan.feature:reports');

        Route::post('/time-entries/{timeEntry}/adjustment/approve', [AdminTimeEntryAdjustmentController::class, 'approve']);
        Route::post('/time-entries/{timeEntry}/adjustment/reject', [AdminTimeEntryAdjustmentController::class, 'reject']);
    });

    Route::middleware(['role:area_manager|manager|admin', 'subscription.access'])->group(function () {

        Route::apiResource('shifts', ShiftController::class);
        Route::get('/users/{user}/shifts', [ShiftController::class, 'byUser']);
        Route::post('/employees/{employee}/shift', [EmployeeController::class, 'assignShift']);

        Route::apiResource('holidays', HolidayController::class)->except(['create', 'edit']);

        Route::get('/vacations', [AdminVacationController::class, 'index']);
        Route::post('/vacations', [AdminVacationController::class, 'store']);
        Route::post('/vacations/{vacation}/approve', [AdminVacationController::class, 'approve']);
        Route::post('/vacations/{vacation}/reject', [AdminVacationController::class, 'reject']);
        Route::delete('/vacations/{vacation}', [AdminVacationController::class, 'destroy']);
        Route::get('/vacations/balance/{employee}', [AdminVacationController::class, 'balance']);

        Route::apiResource('leave-policies', LeavePolicyController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/absences', [AdminAbsenceController::class, 'index']);
        Route::post('/absences', [AdminAbsenceController::class, 'store']);

        Route::apiResource('announcements', AdminAnnouncementController::class);
    });
});
