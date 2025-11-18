<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'app' => config('app.name'),
        'version' => config('app.version', '1.0.0'),
        'timestamp' => now()->toISOString(),
    ]);
});
