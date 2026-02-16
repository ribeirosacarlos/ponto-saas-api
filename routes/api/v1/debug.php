<?php

use Illuminate\Support\Facades\Route;

// DEBUG / TESTE DO TENANT
Route::get('/tenant-check', function (\App\Services\TenantManager $tm) {
    return [
        'tenant' => $tm->tenant()?->slug,
        'tenant_name' => $tm->tenant()?->name,
    ];
});
