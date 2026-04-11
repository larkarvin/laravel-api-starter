<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Local:      CORS_ALLOWED_ORIGINS=*  (or leave unset)
    | Staging:    CORS_ALLOWED_ORIGINS=https://staging.example.com
    | Production: CORS_ALLOWED_ORIGINS=https://app.example.com,https://www.example.com
    |
    | For SPA cookie-based auth (Sanctum), set CORS_SUPPORTS_CREDENTIALS=true
    | and list exact origins (wildcard '*' does not work with credentials).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => env('CORS_ALLOWED_ORIGINS')
        ? array_map(trim(...), explode(',', (string)env('CORS_ALLOWED_ORIGINS')))
        : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Request-ID', 'Accept'],

    'exposed_headers' => ['X-Request-ID'],

    'max_age' => 86400,

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),

];
