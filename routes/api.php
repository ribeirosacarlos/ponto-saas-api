<?php 
use Illuminate\Support\Facades\Route;
use App\Services\TenantManager;

Route::get('/tenant-check', function (TenantManager $tm) {
    return [
        'tenant' => $tm->tenant() ? $tm->tenant()->slug : null,
        'tenant_name' => $tm->tenant() ? $tm->tenant()->name : null,
    ];
});

Route::middleware(['auth:sanctum','role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', fn() => ['message' => 'Admin OK']);
    });

// Employee
Route::middleware(['auth:sanctum','role:employee|manager|area_manager|admin'])
    ->prefix('employee')
    ->group(function () {
        Route::get('/test', fn() => ['message' => 'Employee OK']);
    });