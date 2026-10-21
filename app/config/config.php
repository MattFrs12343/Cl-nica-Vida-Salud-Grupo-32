<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Clínica Vida Salud'),
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', true), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8080'), '/'),
    'timezone' => env('APP_TIMEZONE', 'America/La_Paz'),

    'session' => [
        'name' => env('SESSION_NAME', 'vidasalud_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure' => filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL),
        'samesite' => 'Lax',
    ],

    'uploads' => [
        'path' => BASE_PATH . '/public/uploads',
        'max_mb' => (int) env('UPLOAD_MAX_MB', 2),
    ],

    'kiosk' => [
        'device_key' => env('KIOSK_DEVICE_KEY', ''),
        'hmac_secret' => env('KIOSK_HMAC_SECRET', ''),
        'token_ttl' => (int) env('KIOSK_TOKEN_TTL', 600),
    ],
];
