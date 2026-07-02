<?php

$configuredOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

$localOrigins = [
    'http://localhost:8000',
    'http://127.0.0.1:8000',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $configuredOrigins !== []
        ? $configuredOrigins
        : (env('APP_ENV', 'production') === 'production' ? [] : $localOrigins),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With', 'X-XSRF-TOKEN'],

    'exposed_headers' => ['Content-Disposition', 'Content-Type', 'Content-Length'],

    'max_age' => 0,

    'supports_credentials' => false,
];
