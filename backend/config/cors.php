<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://192.168.1.61:5173',
    ]),
    'allowed_origins_patterns' => [
        '#^https?://(localhost|127\.0\.0\.1|192\.168\.\d+\.\d+|100\.\d+\.\d+\.\d+)(:\d+)?$#',
        '#^https?://.*\.cursor\.sh$#',
        '#^https?://.*\.github\.dev$#',
        '#^https?://.*\.devtunnels\.ms$#',
    ],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
