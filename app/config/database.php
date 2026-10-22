<?php

declare(strict_types=1);

return [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 5432),
    'database' => env('DB_DATABASE', 'clinica'),
    'username' => env('DB_USERNAME', 'clinica'),
    'password' => env('DB_PASSWORD', 'clinica'),
    'sslmode' => env('DB_SSLMODE', 'prefer'),
    'app_role' => env('DB_APP_ROLE', 'clinica_app'),
];
