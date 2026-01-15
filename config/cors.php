<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'v1/*',
        'docs',
        'docs/*',
    ],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins - FIXED VERSION
    |--------------------------------------------------------------------------
    | Must be a simple array, NOT a closure/function!
    */
    'allowed_origins' => [
        'https://obsolio.com',
        'https://www.obsolio.com',
        'https://console.obsolio.com',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://console.localhost:5173',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns - For Wildcard Subdomains
    |--------------------------------------------------------------------------
    */
    'allowed_origins_patterns' => [
        '/^https:\/\/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)?obsolio\.com$/',
        '/^http:\/\/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)?localhost(:\d+)?$/',
        '/^http:\/\/([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)?127\.0\.0\.1(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Authorization',
        'X-Tenant-Id',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];
