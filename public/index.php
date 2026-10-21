<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();

foreach (glob(base_path('app/routes/*.php')) ?: [] as $routeFile) {
    require $routeFile;
}

$response = $router->dispatch(Request::capture());
$response->send();
