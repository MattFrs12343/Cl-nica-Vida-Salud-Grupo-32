<?php

declare(strict_types=1);

return [
    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    // Único correo de Google autorizado a entrar como super_admin.
    'superadmin_email' => env('GOOGLE_SUPERADMIN_EMAIL', ''),
];
