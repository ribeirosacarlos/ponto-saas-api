<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'https://api.jornafy.com',
        'https://app.jornafy.com',
        'https://jornafy.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition', 'Content-Type', 'Content-Length'],

    'max_age' => 86400,

    'supports_credentials' => false,
];
