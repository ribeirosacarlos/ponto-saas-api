<?php

return [

    'paths' => [
        'api/*',
        'v1/*',
        'sanctum/csrf-cookie',
        'v1/auth/login',
        'v1/auth/logout',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
