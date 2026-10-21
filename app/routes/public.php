<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\View;

$router->get('/', static function (): Response {
    return Response::html(View::render('system/welcome', [
        'title' => 'Clínica Vida Salud',
    ]));
});

$router->get('/health', static function (): Response {
    return json_ok([
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => date(DATE_ATOM),
    ]);
});

