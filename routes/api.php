<?php 
use Illuminate\Support\Facades\Route;
use App\Services\TenantManager;

Route::get('/tenant-check', function (TenantManager $tm) {
    return [
        'tenant' => $tm->tenant() ? $tm->tenant()->slug : null,
        'tenant_name' => $tm->tenant() ? $tm->tenant()->name : null,
    ];
});
