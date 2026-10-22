<?php

declare(strict_types=1);

/**
 * Rutas de autenticación (Fase 1).
 *
 * Contrato: docs/api/contract.md §2.
 * Las vistas (`app/views/auth/*`) son responsabilidad de Lucas; si todavía
 * no existen, los GET devuelven 501 VIEW_NOT_AVAILABLE en JSON.
 */

use App\Controllers\AuthController;

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/auth/login', [AuthController::class, 'login'], ['csrf']);

$router->post('/auth/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

$router->get('/recuperar', [AuthController::class, 'showRecuperar']);
$router->post('/auth/recuperar', [AuthController::class, 'recuperar'], ['csrf']);

$router->get('/reset', [AuthController::class, 'showReset']);
$router->post('/auth/reset', [AuthController::class, 'reset'], ['csrf']);

$router->get('/activar', [AuthController::class, 'showActivar']);
$router->post('/auth/activar', [AuthController::class, 'activar'], ['csrf']);
