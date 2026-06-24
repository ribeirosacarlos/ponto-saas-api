<?php

use App\Http\Controllers\Api\Commercial\CommercialAffiliateTrackingController;
use App\Http\Controllers\Web\BlogSitemapController;
use App\Http\Controllers\Web\SuperAdminPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sitemap-blog.xml', [BlogSitemapController::class, 'index']);

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

Route::get('/r/{slug}', [CommercialAffiliateTrackingController::class, 'redirect'])
    ->middleware(['throttle:public-affiliate-click']);

Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    Route::get('/super-admin', [SuperAdminPageController::class, 'index']);
});
