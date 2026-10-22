<?php

declare(strict_types=1);

use App\Controllers\Api\PublicApiController;
use App\Core\Response;

$router->get('/api/health', static function (): Response {
    return json_ok([
        'status' => 'ok',
        'time' => date(DATE_ATOM),
    ]);
});

$router->get('/api/csrf', static function (): Response {
    return json_ok([
        'token' => csrf_token(),
    ]);
});

$router->get('/api/me', static function (): Response {
    $user = auth();

    if ($user === null) {
        abort(401, 'No autenticado.');
    }

    return json_ok($user);
});

// Landing / reserva web (M-15, M-16)
$router->get('/api/public/especialidades', [PublicApiController::class, 'especialidades']);
$router->get('/api/public/medicos', [PublicApiController::class, 'medicos']);
$router->get('/api/public/disponibilidad', [PublicApiController::class, 'disponibilidad']);
$router->post('/api/public/reservar', [PublicApiController::class, 'reservar'], ['role:paciente']);

