<?php

use Illuminate\Support\Facades\DB;
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

Route::get('/debug/datetime', function () {
    // Hora do servidor (PHP)
    $serverNow = now();

    // Hora vinda do banco
    $dbNow = DB::selectOne('SELECT NOW() as now');

    // Timezone do banco (Postgres / MySQL)
    $dbTimezone = DB::selectOne('SHOW TIMEZONE') ?? null;

    return response()->json([
        'server' => [
            'datetime' => $serverNow->toDateTimeString(),
            'timezone' => config('app.timezone'),
            'iso' => $serverNow->toISOString(),
        ],
        'database' => [
            'datetime' => $dbNow->now ?? null,
            'timezone' => $dbTimezone->TimeZone ?? null,
        ],
    ]);
});
