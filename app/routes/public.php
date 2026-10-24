<?php

declare(strict_types=1);

use App\Controllers\PublicController;
use App\Core\Response;

$router->get('/', [PublicController::class, 'index']);
$router->get('/reservar', [PublicController::class, 'reservar']);
$router->get('/kiosco', [PublicController::class, 'kiosco']);
$router->get('/kiosco/token', [PublicController::class, 'kioscoToken']);
$router->get('/kiosco-como-funciona', [PublicController::class, 'kioscoInfo']);

$router->get('/health', static function (): Response {
    return json_ok([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => date(DATE_ATOM),
    ]);
});
