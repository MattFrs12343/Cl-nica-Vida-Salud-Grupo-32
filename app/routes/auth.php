<?php

declare(strict_types=1);

/**
 * Rutas de autenticación (Fase 1).
 *
 * Los endpoints definitivos están documentados en docs/api/contract.md.
 * El login todavía no está implementado; esta ruta existe para que el
 * frontend tenga un destino mientras se construye la pantalla real.
 */

use App\Core\Response;

$notImplemented = static function (): Response {
    return json_error(
        'NOT_IMPLEMENTED',
        'El login se implementa en la Fase 1.',
        [],
        501
    );
};

$router->get('/login', $notImplemented);

// Ruta provisional con CSRF para verificar el middleware en Fase 0.
$router->post('/auth/login', $notImplemented, ['csrf']);
