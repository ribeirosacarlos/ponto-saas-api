<?php

use App\Http\Controllers\Api\Documents\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:employee|area_manager|manager|admin', 'subscription.access'])->group(function () {
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/view', [DocumentController::class, 'view'])->name('documents.view');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/resend', [DocumentController::class, 'resend'])->name('documents.resend');
    Route::patch('/documents/{document}', [DocumentController::class, 'update'])
        ->name('documents.update')
        ->middleware('role:admin|manager|area_manager');
    Route::patch('/documents/{document}/approve', [DocumentController::class, 'approve'])
        ->name('documents.approve')
        ->middleware('role:admin|manager|area_manager');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});
