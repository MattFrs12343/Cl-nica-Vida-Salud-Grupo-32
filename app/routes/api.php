<?php

declare(strict_types=1);

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

