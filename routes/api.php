<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/v1/public.php';
    require __DIR__.'/api/v1/blog.php';

    Route::middleware(['auth:sanctum', 'company.timezone'])->group(function () {
        require __DIR__.'/api/v1/auth.php';
        require __DIR__.'/api/v1/documents.php';
        require __DIR__.'/api/v1/employee.php';
        require __DIR__.'/api/v1/area-manager.php';
        require __DIR__.'/api/v1/admin.php';
        require __DIR__.'/api/v1/platform.php';
        require __DIR__.'/api/v1/commercial.php';
    });
});
