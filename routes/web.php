<?php

use App\Http\Controllers\Web\SuperAdminPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/debug/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    Route::get('/super-admin', [SuperAdminPageController::class, 'index']);
});
