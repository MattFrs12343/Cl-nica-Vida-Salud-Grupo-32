#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\KioskTokenService;

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

/**
 * @return array{status: int, code: ?string, fields: array<string, string>}
 */
function detalleError(callable $operacion): array
{
    try {
        $operacion();
    } catch (HttpException $exception) {
        return [
            'status' => $exception->statusCode(),
            'code' => $exception->errorCode(),
            'fields' => $exception->fields(),
        ];
    } catch (Throwable $exception) {
        return [
            'status' => 500,
            'code' => get_class($exception),
            'fields' => [],
        ];
    }

    return ['status' => 0, 'code' => null, 'fields' => []];
}

/** @return array<string, mixed> */
function cuerpo(Response $response): array
{
    $datos = json_decode($response->body(), true);

    return is_array($datos) ? $datos : [];
}

function codigoDeRespuesta(Response $response): ?string
{
    $datos = cuerpo($response);
    $codigo = $datos['error']['code'] ?? null;

    return is_string($codigo) ? $codigo : null;
}

function cambiarUltimoCaracter(string $valor): string
{
    if ($valor === '') {
        return 'x';
    }

    $valor[strlen($valor) - 1] = $valor[strlen($valor) - 1] === 'a' ? 'b' : 'a';

    return $valor;
}

$servidor = new Router();
$router = $servidor;

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

// Ruta exclusiva de esta prueba para verificar el alias `kiosk` del Router.
$router->get('/api/kiosco/_test_protegido', static function (Request $request): Response {
    $kiosk = $request->attribute('kiosk');

    return json_ok([
        'jti' => is_array($kiosk) ? (string) ($kiosk['jti'] ?? '') : '',
    ]);
}, ['kiosk']);

$config = (array) config('app.kiosk', []);
$deviceKey = (string) ($config['device_key'] ?? '');
$secret = (string) ($config['hmac_secret'] ?? '');

if ($deviceKey === '' || $secret === '') {
    fwrite(STDERR, 'ERROR: faltan KIOSK_DEVICE_KEY o KIOSK_HMAC_SECRET.' . PHP_EOL);
    exit(1);
}

$servicio = new KioskTokenService();

// Servicio de tokens ---------------------------------------------------------
comprobar('el kiosco tiene device key configurada', $servicio->deviceKeyValid($deviceKey));
comprobar('una device key distinta es rechazada', !$servicio->deviceKeyValid($deviceKey . '-otra'));
comprobar('una device key vacia es rechazada', !$servicio->deviceKeyValid(''));
comprobar('el TTL del token es de 10 minutos', $servicio->ttl() === 600);

$fijo = 1_700_000_000;
$emitido = $servicio->issue($fijo);
$partes = explode('.', $emitido['token']);

comprobar('el token tiene payload y firma', count($partes) === 2
    && preg_match('/^[A-Za-z0-9_-]+$/', $partes[0]) === 1
    && preg_match('/^[A-Za-z0-9_-]+$/', $partes[1]) === 1);
comprobar('la respuesta incluye el TTL en segundos', $emitido['expires_in'] === 600);

$payload = $servicio->validate($emitido['token'], $fijo);
comprobar('el token recien emitido es valido', is_array($payload));
comprobar('el payload identifica la audiencia y version', is_array($payload)
    && ($payload['v'] ?? null) === 1
    && ($payload['aud'] ?? null) === 'kiosco');
comprobar('el payload tiene iat, exp y jti', is_array($payload)
    && ($payload['iat'] ?? null) === $fijo
    && ($payload['exp'] ?? null) === $fijo + 600
    && preg_match('/^[a-f0-9]{32}$/', (string) ($payload['jti'] ?? '')) === 1);
comprobar('dos emisiones generan tokens distintos', $servicio->issue($fijo)['token'] !== $emitido['token']);

comprobar('el token es valido un segundo antes de expirar', $servicio->validate($emitido['token'], $fijo + 599) !== null);
comprobar('el token expira exactamente a los 10 minutos', $servicio->validate($emitido['token'], $fijo + 600) === null);
comprobar('el token emitido en el futuro lejano es rechazado', $servicio->validate($emitido['token'], $fijo - 61) === null);
comprobar('se tolera un desfase de reloj pequeno', $servicio->validate($emitido['token'], $fijo - 30) !== null);

$payloadManipulado = cambiarUltimoCaracter($partes[0]) . '.' . $partes[1];
comprobar('un payload manipulado es rechazado', $servicio->validate($payloadManipulado, $fijo) === null);

$firmaManipulada = $partes[0] . '.' . cambiarUltimoCaracter($partes[1]);
comprobar('una firma manipulada es rechazada', $servicio->validate($firmaManipulada, $fijo) === null);

$otroServicio = new KioskTokenService('otro-secreto-para-pruebas', 'otro-device', 600);
comprobar('un token firmado con otro secreto es rechazado', $servicio->validate($otroServicio->issue($fijo)['token'], $fijo) === null);
comprobar('un token con segmentos extra es rechazado', $servicio->validate($emitido['token'] . '.extra', $fijo) === null);
comprobar('un token vacio es rechazado', $servicio->validate('', $fijo) === null);
comprobar('un token excesivamente largo es rechazado', $servicio->validate(str_repeat('a', 4097), $fijo) === null);
comprobar(
    'no se puede construir el servicio sin secreto',
    codigoDeError(fn () => new KioskTokenService('', 'device', 600)) === RuntimeException::class
);
comprobar(
    'un TTL invalido cae al valor seguro de 10 minutos',
    (new KioskTokenService('secreto-de-prueba', 'device-de-prueba', 0))->ttl() === 600
);
comprobar(
    'una configuracion sin device key no autentica',
    !(new KioskTokenService('secreto-de-prueba', '', 600))->deviceKeyValid('')
);

$requestOriginal = Request::create('GET', '/x');
$requestConKiosk = $requestOriginal->withAttribute('kiosk', ['jti' => 'abc']);
comprobar('los atributos del request no mutan la instancia original', $requestOriginal->attribute('kiosk') === null
    && is_array($requestConKiosk->attribute('kiosk')));

// POST /api/kiosco/sesion ----------------------------------------------------
$respuesta = $servidor->dispatch(Request::create('POST', '/api/kiosco/sesion', [
    'device_key' => $deviceKey,
]));
$datos = cuerpo($respuesta);
$token = (string) ($datos['data']['token'] ?? '');

comprobar('POST /api/kiosco/sesion responde 200', $respuesta->status() === 200);
comprobar('la sesion devuelve token y expiracion', $token !== ''
    && (int) ($datos['data']['expires_in'] ?? 0) === $servicio->ttl());
comprobar('la respuesta de sesion no se cachea', ($respuesta->headers()['Cache-Control'] ?? '') === 'no-store');
comprobar('la sesion de kiosco no crea sesion PHP', auth() === null);

$validado = $servicio->validate($token);
comprobar('el token devuelto por la ruta es valido', is_array($validado)
    && preg_match('/^[a-f0-9]{32}$/', (string) ($validado['jti'] ?? '')) === 1);

$segundaRespuesta = $servidor->dispatch(Request::create('POST', '/api/kiosco/sesion', [
    'device_key' => $deviceKey,
]));
$segundoToken = (string) (cuerpo($segundaRespuesta)['data']['token'] ?? '');
comprobar('cada sesion de kiosco emite un token nuevo', $segundoToken !== '' && $segundoToken !== $token);

$error = detalleError(fn () => $servidor->dispatch(Request::create('POST', '/api/kiosco/sesion')));
comprobar('la sesion sin device_key devuelve 422', $error['status'] === 422
    && $error['code'] === 'VALIDATION_ERROR'
    && isset($error['fields']['device_key']));

$error = detalleError(fn () => $servidor->dispatch(Request::create('POST', '/api/kiosco/sesion', [
    'device_key' => '   ',
])));
comprobar('la sesion con device_key vacia devuelve 422', $error['status'] === 422
    && $error['code'] === 'VALIDATION_ERROR');

$error = detalleError(fn () => $servidor->dispatch(Request::create('POST', '/api/kiosco/sesion', [
    'device_key' => ['no-es-texto'],
])));
comprobar('la sesion con device_key no textual devuelve 422', $error['status'] === 422
    && $error['code'] === 'VALIDATION_ERROR');

$error = detalleError(fn () => $servidor->dispatch(Request::create('POST', '/api/kiosco/sesion', [
    'device_key' => $deviceKey . '-incorrecta',
])));
comprobar('la sesion con device_key incorrecta devuelve 401', $error['status'] === 401
    && $error['code'] === 'UNAUTHENTICATED');

// Middleware kiosk -----------------------------------------------------------
$respuesta = $servidor->dispatch(Request::create('GET', '/api/kiosco/_test_protegido', [], [
    'X-Kiosk-Token' => $token,
]));
$datos = cuerpo($respuesta);
comprobar('el middleware acepta un token valido', $respuesta->status() === 200
    && ($datos['data']['jti'] ?? '') === ($validado['jti'] ?? null));
comprobar('el middleware expone el payload en el request', is_array($validado));

$respuesta = $servidor->dispatch(Request::create('GET', '/api/kiosco/_test_protegido'));
comprobar('el middleware exige el header X-Kiosk-Token', $respuesta->status() === 401
    && codigoDeRespuesta($respuesta) === 'UNAUTHENTICATED');

$respuesta = $servidor->dispatch(Request::create('GET', '/api/kiosco/_test_protegido', [], [
    'X-Kiosk-Token' => 'token-invalido',
]));
comprobar('el middleware rechaza un token malformado', $respuesta->status() === 401
    && codigoDeRespuesta($respuesta) === 'UNAUTHENTICATED');

$expirado = $servicio->issue(time() - $servicio->ttl() - 1)['token'];
$respuesta = $servidor->dispatch(Request::create('GET', '/api/kiosco/_test_protegido', [], [
    'X-Kiosk-Token' => $expirado,
]));
comprobar('el middleware rechaza un token expirado', $respuesta->status() === 401
    && codigoDeRespuesta($respuesta) === 'UNAUTHENTICATED');

$firmadoConOtroSecreto = (new KioskTokenService('secreto-distinto', $deviceKey, 600))->issue()['token'];
$respuesta = $servidor->dispatch(Request::create('GET', '/api/kiosco/_test_protegido', [], [
    'X-Kiosk-Token' => $firmadoConOtroSecreto,
]));
comprobar('el middleware rechaza un token de otro secreto', $respuesta->status() === 401
    && codigoDeRespuesta($respuesta) === 'UNAUTHENTICATED');

$respuesta = $servidor->dispatch(Request::create('GET', '/api/kiosco/_test_protegido', [], [
    'X-Kiosk-Token' => $partes[0] . '.' . cambiarUltimoCaracter($partes[1]),
]));
comprobar('el middleware rechaza una firma manipulada', $respuesta->status() === 401
    && codigoDeRespuesta($respuesta) === 'UNAUTHENTICATED');

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
