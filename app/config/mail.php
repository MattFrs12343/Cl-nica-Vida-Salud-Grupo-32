<?php

declare(strict_types=1);

return [
    'driver' => env('MAIL_DRIVER', 'smtp'),
    'host' => env('MAIL_HOST', '127.0.0.1'),
    'port' => (int) env('MAIL_PORT', 1025),
    'username' => env('MAIL_USERNAME', ''),
    'password' => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', ''),
    'from' => env('MAIL_FROM', 'no-reply@vidasalud.test'),
    'from_name' => env('MAIL_FROM_NAME', 'Clínica Vida Salud'),
    'smtp_auth' => filter_var(env('MAIL_SMTP_AUTH', false), FILTER_VALIDATE_BOOL),
    'timeout' => (int) env('MAIL_TIMEOUT', 10),
];
