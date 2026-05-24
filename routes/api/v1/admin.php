<?php

use App\Http\Controllers\Api\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AreaController;
use App\Http\Controllers\Api\Admin\MonthlyClosureController;
use App\Http\Controllers\Api\Admin\TimesheetAdminController;
use App\Http\Controllers\Api\Admin\TimesheetPdfAdminController;
use App\Http\Controllers\Api\Admin\Billing\ExtraEmployeeCheckoutSessionController;
use App\Http\Controllers\Api\Admin\CompanyDeviceSettingsController;
use App\Http\Controllers\Api\Admin\CompanyGeolocationController;
use App\Http\Controllers\Api\Admin\CompanyInfoController;
use App\Http\Controllers\Api\Admin\CompanyLocaleController;
use App\Http\Controllers\Api\Admin\CompanySignatureSettingsController;
use App\Http\Controllers\Api\Admin\CompanyTimezoneController;
use App\Http\Controllers\Api\Admin\DocumentReviewController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\Admin\AbsenceController as AdminAbsenceController;
use App\Http\Controllers\Api\Admin\Billing\ExtraEmployeeSyncController;
use App\Http\Controllers\Api\Admin\HolidayController;
use App\Http\Controllers\Api\Admin\LeavePolicyController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ShiftController;
use App\Http\Controllers\Api\Admin\TimeEntryController as AdminTimeEntryController;
use App\Http\Controllers\Api\Admin\TimeEntryAdjustmentController as AdminTimeEntryAdjustmentController;
use App\Http\Controllers\Api\Admin\VacationController as AdminVacationController;
use App\Http\Controllers\Api\Employee\EmployeeOvertimeController;
use App\Http\Controllers\Api\Settings\CompanyLocationSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(function () {

    Route::middleware(['role:admin|super_admin', 'subscription.access'])->group(function () {
        Route::get('/company/timezone', [CompanyTimezoneController::class, 'show']);
        Route::put('/company/timezone', [CompanyTimezoneController::class, 'update']);
        Route::get('/company/geolocation', [CompanyGeolocationController::class, 'show']);
        Route::put('/company/geolocation', [CompanyGeolocationController::class, 'update']);
        Route::get('/company/device-settings', [CompanyDeviceSettingsController::class, 'show']);
        Route::put('/company/device-settings', [CompanyDeviceSettingsController::class, 'update']);
        Route::get('/company/signatures', [CompanySignatureSettingsController::class, 'show']);
        Route::put('/company/signatures', [CompanySignatureSettingsController::class, 'update']);
        Route::patch('/company/signatures', [CompanySignatureSettingsController::class, 'update']);
        Route::get('/company/info', [CompanyInfoController::class, 'show']);
        Route::patch('/company/info', [CompanyInfoController::class, 'update']);
        Route::get('/company/locale', [CompanyLocaleController::class, 'show']);
        Route::put('/company/locale', [CompanyLocaleController::class, 'update']);
        Route::patch('/company/locale', [CompanyLocaleController::class, 'update']);
        Route::put('/settings/location', [CompanyLocationSettingsController::class, 'update']);
        Route::patch('/settings/location', [CompanyLocationSettingsController::class, 'update']);
        Route::post('/billing/extra-employees/sync', [ExtraEmployeeSyncController::class, 'store']);
        Route::post('/billing/extra-employees/checkout-session', [ExtraEmployeeCheckoutSessionController::class, 'store']);
        Route::get('/audit-logs', [AuditLogController::class, 'companyIndex'])
            ->middleware('company.audit_logs_enabled');
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'companyShow'])
            ->middleware('company.audit_logs_enabled');
    });

    Route::middleware(['role:admin|super_admin|area_manager|manager', 'subscription.access'])->group(function () {
        Route::get('/settings/location', [CompanyLocationSettingsController::class, 'show']);
    });

    Route::middleware(['role:admin', 'subscription.access'])->group(function () {
        Route::post('/monthly-closures', [MonthlyClosureController::class, 'store']);
    });

    Route::middleware(['role:admin|manager|area_manager', 'subscription.access'])->group(function () {
        Route::get('/monthly-closures', [MonthlyClosureController::class, 'index']);
        Route::get('/monthly-closures/{closure}', [MonthlyClosureController::class, 'show']);
        Route::get('/monthly-closures/{closure}/timesheets', [TimesheetAdminController::class, 'index']);
        Route::get('/timesheets/{timesheet}', [TimesheetAdminController::class, 'show']);
        Route::post('/timesheets/{timesheet}/sign', [TimesheetAdminController::class, 'sign']);
        Route::post('/timesheets/{timesheet}/disputes/{dispute}/resolve', [TimesheetAdminController::class, 'resolveDispute']);
        Route::get('/timesheets/{timesheet}/pdf', [TimesheetPdfAdminController::class, 'download']);
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
        Route::post('/employees/{employee}/resend-invite', [EmployeeController::class, 'resendInvite']);
        Route::get('/employees/{employee}/overtime', [EmployeeOvertimeController::class, 'show']);

        Route::get('/reports/time', [ReportController::class, 'timeReport'])
            ->middleware('plan.feature:reports');

        Route::post('/time-entries/{timeEntry}/adjustment/approve', [AdminTimeEntryAdjustmentController::class, 'approve']);
        Route::post('/time-entries/{timeEntry}/adjustment/reject', [AdminTimeEntryAdjustmentController::class, 'reject']);
        Route::delete('/time-entries/{timeEntry}', [AdminTimeEntryController::class, 'destroy']);

        Route::get('/areas', [AreaController::class, 'index']);
        Route::get('/areas/{area}', [AreaController::class, 'show']);
    });

    Route::middleware(['role:area_manager|manager|admin', 'subscription.access'])->group(function () {

        Route::apiResource('shifts', ShiftController::class);
        Route::get('/users/{user}/shifts', [ShiftController::class, 'byUser']);
        Route::post('/employees/{employee}/shift', [EmployeeController::class, 'assignShift']);

        Route::apiResource('holidays', HolidayController::class)->except(['create', 'edit', 'index', 'show']);

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

    Route::middleware(['role:admin', 'subscription.access'])->group(function () {
        Route::post('/areas', [AreaController::class, 'store']);
        Route::put('/areas/{area}', [AreaController::class, 'update']);
        Route::patch('/areas/{area}', [AreaController::class, 'update']);
        Route::delete('/areas/{area}', [AreaController::class, 'destroy']);
    });
});
