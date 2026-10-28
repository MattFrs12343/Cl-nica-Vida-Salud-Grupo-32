#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-36 - Hardening de integración.
 *
 * Verifica cabeceras de seguridad, CSRF estricto en mutaciones, la excepción
 * same-origin de la reserva web, guards por rol, expiración de sesión por
 * inactividad y validación de subidas sin archivo.
 */

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\SecurityHeaders;
use App\Core\Session;

require dirname(__DIR__) . '/app/bootstrap.php';

$fallos = 0;

function comprobar(string $etiqueta, bool $condicion): void
{
    global $fallos;

    if ($condicion) {
        echo 'OK   ' . $etiqueta . PHP_EOL;

        return;
    }

    $fallos++;
    echo 'FALLO ' . $etiqueta . PHP_EOL;
}

/** @return array<string, mixed> */
function cuerpo(Response $response): array
{
    $datos = json_decode($response->body(), true);

    return is_array($datos) ? $datos : [];
}

function codigoDeError(callable $operacion): ?string
{
    try {
        $operacion();
    } catch (HttpException $exception) {
        return $exception->errorCode();
    } catch (Throwable $exception) {
        return get_class($exception);
    }

    return null;
}

/** @param array<string, mixed> $usuario */
function cambiarSesion(array $usuario): void
{
    $_SESSION = ['usuario' => $usuario, '_last_activity' => time()];
    DB::setContext($usuario, '127.0.0.1');
}

/** @param array<string, string> $headers */
function peticion(string $metodo, string $ruta, array $body = [], array $headers = []): Request
{
    return Request::create($metodo, $ruta, $body, $headers);
}

function enviar(Router $router, Request $request): Response
{
    try {
        return $router->dispatch($request);
    } catch (HttpException $exception) {
        return json_error(
            $exception->errorCode() ?? 'ERROR',
            $exception->getMessage(),
            $exception->fields(),
            $exception->statusCode()
        );
    }
}

$router = new Router();

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

$admin = [
    'id' => 2,
    'rol' => 'admin',
    'nombre' => 'admin',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];
$medico = [
    'id' => 5,
    'rol' => 'medico',
    'nombre' => 'Dr. Carlos Mendez',
    'medico_id' => 1,
    'medico_ids' => [1],
    'paciente_id' => null,
];
$paciente = [
    'id' => 6,
    'rol' => 'paciente',
    'nombre' => 'Juan Perez',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => 1,
];

try {
    // -- Cabeceras ---------------------------------------------------------
    $respuesta = enviar($router, peticion('GET', '/api/health'));
    $cabeceras = $respuesta->headers();

    comprobar('la API responde', $respuesta->status() === 200);
    comprobar('incluye X-Content-Type-Options', ($cabeceras['X-Content-Type-Options'] ?? '') === 'nosniff');
    comprobar('incluye X-Frame-Options', ($cabeceras['X-Frame-Options'] ?? '') === 'SAMEORIGIN');
    comprobar('incluye Referrer-Policy', ($cabeceras['Referrer-Policy'] ?? '') === 'strict-origin-when-cross-origin');
    comprobar('incluye Permissions-Policy', str_contains($cabeceras['Permissions-Policy'] ?? '', 'camera=()'));
    comprobar('incluye Cross-Origin-Opener-Policy', ($cabeceras['Cross-Origin-Opener-Policy'] ?? '') === 'same-origin');
    comprobar('la API no se cachea', ($cabeceras['Cache-Control'] ?? '') === 'no-store');

    $respuesta = enviar($router, peticion('GET', '/login'));
    comprobar('el login responde', $respuesta->status() === 200);
    comprobar('el HTML también recibe cabeceras de seguridad',
        ($respuesta->headers()['X-Frame-Options'] ?? '') === 'SAMEORIGIN');

    $httpsAnterior = $_SERVER['HTTPS'] ?? null;
    $_SERVER['HTTPS'] = 'on';
    $cabecerasHttps = SecurityHeaders::values('/');

    if ($httpsAnterior === null) {
        unset($_SERVER['HTTPS']);
    } else {
        $_SERVER['HTTPS'] = $httpsAnterior;
    }

    comprobar('HSTS aparece cuando la petición es HTTPS',
        str_contains($cabecerasHttps['Strict-Transport-Security'] ?? '', 'max-age='));

    // -- Guards por rol ----------------------------------------------------
    $_SESSION = [];
    DB::clearContext();
    $respuesta = enviar($router, peticion('GET', '/api/citas'));
    comprobar('sin sesión la API responde 401', $respuesta->status() === 401);
    comprobar('sin sesión devuelve UNAUTHENTICATED',
        (cuerpo($respuesta)['error']['code'] ?? '') === 'UNAUTHENTICATED');

    cambiarSesion($paciente);
    $respuesta = enviar($router, peticion('GET', '/api/usuarios'));
    comprobar('el paciente no accede al listado de usuarios', $respuesta->status() === 403);
    comprobar('el paciente recibe FORBIDDEN',
        (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN');

    cambiarSesion($medico);
    $respuesta = enviar($router, peticion('GET', '/api/usuarios'));
    comprobar('el médico no accede al listado de usuarios', $respuesta->status() === 403);

    // -- CSRF estricto -----------------------------------------------------
    cambiarSesion($admin);
    $token = Session::csrfToken();

    $respuesta = enviar($router, peticion('POST', '/api/citas'));
    comprobar('una mutación sin token responde 403', $respuesta->status() === 403);
    comprobar('una mutación sin token devuelve CSRF_TOKEN_MISMATCH',
        (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH');

    $respuesta = enviar($router, peticion('POST', '/api/citas', [], [
        'Origin' => (string) config('app.url'),
    ]));
    comprobar('same-origin no exime al panel de CSRF', $respuesta->status() === 403);

    $respuesta = enviar($router, peticion('POST', '/api/citas', [], [
        'X-CSRF-Token' => $token,
    ]));
    comprobar('con token la petición llega al controlador', $respuesta->status() === 422);
    comprobar('el controlador devuelve validación, no CSRF',
        (cuerpo($respuesta)['error']['code'] ?? '') === 'VALIDATION_ERROR');

    $respuesta = enviar($router, peticion('POST', '/api/citas', [
        '_token' => $token,
    ]));
    comprobar('el token también se acepta como campo _token', $respuesta->status() === 422);

    // -- Reserva web: same-origin sin token --------------------------------
    cambiarSesion($paciente);

    $respuesta = enviar($router, peticion('POST', '/api/public/reservar', [], [
        'Origin' => (string) config('app.url'),
    ]));
    comprobar('la reserva web same-origin pasa el CSRF', $respuesta->status() !== 403);

    $respuesta = enviar($router, peticion('POST', '/api/public/reservar', [], [
        'Origin' => 'https://evil.example',
    ]));
    comprobar('la reserva web desde otro origen es rechazada', $respuesta->status() === 403);
    comprobar('la reserva web cross-origin devuelve CSRF_TOKEN_MISMATCH',
        (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH');

    $respuesta = enviar($router, peticion('POST', '/api/public/reservar'));
    comprobar('la reserva web sin token ni origen es rechazada', $respuesta->status() === 403);

    $tokenPaciente = Session::csrfToken();
    $respuesta = enviar($router, peticion('POST', '/api/public/reservar', [], [
        'X-CSRF-Token' => $tokenPaciente,
    ]));
    comprobar('la reserva web acepta el token CSRF', $respuesta->status() !== 403);

    // -- Uploads -----------------------------------------------------------
    cambiarSesion($medico);
    $tokenMedico = Session::csrfToken();
    $respuesta = enviar($router, peticion('POST', '/api/medicos/1/qr', [], [
        'X-CSRF-Token' => $tokenMedico,
    ]));
    comprobar('la subida de QR sin archivo devuelve validación', $respuesta->status() === 422);
    comprobar('la subida de QR informa el campo qr',
        isset(cuerpo($respuesta)['error']['fields']['qr']));

    // -- Expiración de sesión ---------------------------------------------
    $sesionAnterior = $_SESSION;
    $_SESSION = [
        'usuario' => $paciente,
        '_last_activity' => time() - 10000,
    ];
    Session::touch();
    comprobar('la sesión inactiva pierde el usuario', !isset($_SESSION['usuario']));
    comprobar('la sesión inactiva conserva un aviso', isset($_SESSION['_flash'][0]['message']));
    comprobar('la marca de actividad se renueva', (int) ($_SESSION['_last_activity'] ?? 0) >= time() - 2);

    $_SESSION = [
        'usuario' => $paciente,
        '_last_activity' => time(),
    ];
    Session::touch();
    comprobar('la sesión activa se mantiene', isset($_SESSION['usuario']));

    $_SESSION = $sesionAnterior;

    // -- CSRF de kiosco ----------------------------------------------------
    $_SESSION = [];
    DB::clearContext();
    $respuesta = enviar($router, peticion('POST', '/api/kiosco/sesion', [
        'device_key' => (string) env('KIOSK_DEVICE_KEY', 'dev-kiosk-key'),
    ]));
    comprobar('el kiosco sigue usando su token HMAC, no CSRF', $respuesta->status() === 200);
    comprobar('el kiosco devuelve un token', is_string(cuerpo($respuesta)['data']['token'] ?? null));
} finally {
    DB::clearContext();
    $_SESSION = [];
}

if ($fallos > 0) {
    echo PHP_EOL . $fallos . ' prueba(s) fallaron.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Todas las pruebas de hardening (M-36) pasaron.' . PHP_EOL;
