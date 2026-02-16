<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/v1/public.php';

    Route::middleware(['auth:sanctum', 'company.timezone'])->group(function () {
        require __DIR__.'/api/v1/auth.php';
        require __DIR__.'/api/v1/documents.php';
        require __DIR__.'/api/v1/employee.php';
        require __DIR__.'/api/v1/area-manager.php';
        require __DIR__.'/api/v1/admin.php';
        require __DIR__.'/api/v1/platform.php';
        require __DIR__.'/api/v1/debug.php';
    });
});

Route::get('/db-test', function () {
    try {
        \DB::connection()->getPdo();
        return ['status' => 'ok', 'message' => 'Conexão com banco funcionando!'];
    } catch (\Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
});

Route::get('/__crash', function () {
    logger()->error('LOG ANTES DO CRASH');
    throw new Exception('CRASH TEST - CloudWatch');
});
