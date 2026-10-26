#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-32 — Pagos y QR del médico.
 *
 * El rol `medico` ya estaba habilitado en las rutas de `/api/pagos` (M-28)
 * y `/api/medicos/{id}/qr` (M-20); este M-32 cierra la cobertura que
 * faltaba: que el propio médico registre/lea sus pagos y suba/vea su QR.
 *
 * La subida real de archivo (`move_uploaded_file`/`is_uploaded_file`) no es
 * simulable desde la CLI (PHP solo las activa en una petición HTTP real), así
 * que ese camino se verificó manualmente con `curl` contra el contenedor:
 * login como médico, `POST /api/medicos/1/qr` con un PNG real → 200 y
 * `qr_url`; una segunda subida reemplaza el archivo anterior; `recepcion`
 * ve el `qr_url` resultante. Aquí se prueban los permisos y el resto del
 * flujo (sin archivo, médico ajeno, visibilidad por rol).
 */

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

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

/** @param array<string, mixed> $usuario */
function cambiarSesion(array $usuario): void
{
    $_SESSION['usuario'] = $usuario;
    DB::setContext($usuario, '127.0.0.1');
}

/**
 * @param array<string, mixed> $query
 * @param array<string, mixed>|null $body
 */
function solicitud(
    string $metodo,
    string $ruta,
    array $query = [],
    ?array $body = null,
    bool $conCsrf = true
): Request {
    $getAnterior = $_GET;
    $postAnterior = $_POST;
    $serverAnterior = $_SERVER;

    $_GET = $query;
    $_POST = $body ?? [];
    $_SERVER['REQUEST_METHOD'] = strtoupper($metodo);
    $_SERVER['REQUEST_URI'] = $ruta . ($query === [] ? '' : '?' . http_build_query($query));
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['CONTENT_TYPE'] = $body === null ? '' : 'application/x-www-form-urlencoded';

    unset($_SERVER['HTTP_X_CSRF_TOKEN']);

    if ($conCsrf && in_array(strtoupper($metodo), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $_SERVER['HTTP_X_CSRF_TOKEN'] = csrf_token();
    }

    try {
        return Request::capture();
    } finally {
        $_GET = $getAnterior;
        $_POST = $postAnterior;
        $_SERVER = $serverAnterior;
    }
}

/** @return array<string, mixed>|null */
function fila(PDO $pdo, string $sql, array $parametros = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parametros);
    $resultado = $statement->fetch();

    return is_array($resultado) ? $resultado : null;
}

$pdo = DB::connection();
$router = new Router();

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

$enviar = static function (Request $request) use ($router): Response {
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
};

$medicoDb = fila($pdo, "SELECT id FROM medicos WHERE codigo = 'MED-DEMO-001' LIMIT 1");
$otroMedicoDb = fila(
    $pdo,
    "SELECT id FROM medicos WHERE id <> :medico_id AND estado = 'activo' ORDER BY id LIMIT 1",
    ['medico_id' => (int) ($medicoDb['id'] ?? 0)]
);
$paciente1 = fila($pdo, "SELECT id FROM pacientes WHERE codigo = 'PAC-DEMO-001' LIMIT 1");

$usuarioAdmin = fila($pdo, "SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1");
$usuarioRecepcion = fila($pdo, "SELECT id FROM usuarios WHERE username = 'recepcion' LIMIT 1");
$usuarioRecepcionMedico = fila($pdo, "SELECT id FROM usuarios WHERE username = 'recepcion.medico' LIMIT 1");
$usuarioMedico = fila($pdo, "SELECT id FROM usuarios WHERE username = 'medico' LIMIT 1");

if (
    !is_array($medicoDb) || !is_array($otroMedicoDb) || !is_array($paciente1)
    || !is_array($usuarioAdmin) || !is_array($usuarioRecepcion)
    || !is_array($usuarioRecepcionMedico) || !is_array($usuarioMedico)
) {
    fwrite(STDERR, 'ERROR: faltan los datos seed para las pruebas de pagos/QR del médico.' . PHP_EOL);
    exit(1);
}

$medicoId = (int) $medicoDb['id'];
$otroMedicoId = (int) $otroMedicoDb['id'];
$paciente1Id = (int) $paciente1['id'];

$admin = [
    'id' => (int) $usuarioAdmin['id'], 'rol' => 'admin', 'nombre' => 'admin',
    'medico_id' => null, 'medico_ids' => [], 'paciente_id' => null,
];
$recepcion = [
    'id' => (int) $usuarioRecepcion['id'], 'rol' => 'recepcionista', 'nombre' => 'recepcion',
    'medico_id' => null, 'medico_ids' => [], 'paciente_id' => null,
];
$recepcionistaMedico = [
    'id' => (int) $usuarioRecepcionMedico['id'], 'rol' => 'recepcionista_medico', 'nombre' => 'recepcion.medico',
    'medico_id' => $medicoId, 'medico_ids' => [$medicoId, $otroMedicoId], 'paciente_id' => null,
];
$medico = [
    'id' => (int) $usuarioMedico['id'], 'rol' => 'medico', 'nombre' => 'Dr. Carlos Méndez',
    'medico_id' => $medicoId, 'medico_ids' => [$medicoId], 'paciente_id' => null,
];

$pdo->beginTransaction();

try {
    // -- Pagos propios del médico ---------------------------------------------
    cambiarSesion($medico);

    $payloadPropio = [
        'paciente_id' => $paciente1Id,
        'medico_id' => $medicoId,
        'monto' => 150,
        'metodo' => 'efectivo',
        'referencia_paciente' => 'M32-propio',
    ];
    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], $payloadPropio));
    $pago = cuerpo($respuesta)['data'] ?? [];
    $pagoId = (int) ($pago['id'] ?? 0);
    comprobar(
        'el médico registra un pago propio',
        $respuesta->status() === 201
        && $pagoId > 0
        && ($pago['registrado_por']['username'] ?? '') === 'medico'
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadPropio, [
        'medico_id' => $otroMedicoId,
        'referencia_paciente' => 'M32-ajeno',
    ])));
    comprobar(
        'el médico no registra pagos de otro médico',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos', ['per_page' => '100']));
    $ids = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        cuerpo($respuesta)['data'] ?? []
    );
    comprobar(
        'el listado del médico incluye el pago recién registrado',
        $respuesta->status() === 200 && in_array($pagoId, $ids, true)
    );

    $respuesta = $enviar(solicitud('GET', "/api/pagos/{$pagoId}"));
    comprobar(
        'el médico consulta el detalle de su pago',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['referencia_paciente'] ?? '') === 'M32-propio'
    );

    // -- QR del médico: permisos ----------------------------------------------
    $respuesta = $enviar(solicitud('POST', "/api/medicos/{$medicoId}/qr"));
    comprobar(
        'subir QR sin archivo devuelve VALIDATION_ERROR',
        $respuesta->status() === 422 && isset(cuerpo($respuesta)['error']['fields']['qr'])
    );

    $respuesta = $enviar(solicitud('POST', "/api/medicos/{$otroMedicoId}/qr"));
    comprobar(
        'el médico no sube el QR de otro médico',
        $respuesta->status() === 403
    );

    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('POST', "/api/medicos/{$medicoId}/qr"));
    comprobar(
        'recepción médica no sube el QR (solo el propio médico puede)',
        $respuesta->status() === 403
    );

    // -- Visibilidad del QR ya emitido ----------------------------------------
    // La subida real de archivo no es simulable en CLI; se sembra el campo
    // directamente (bypasseando RLS) para probar que cada rol lo puede ver.
    DB::clearContext();
    $pdo->prepare('UPDATE medicos SET qr_imagen = :ruta WHERE id = :id')
        ->execute(['ruta' => 'uploads/qr/medico-test.png', 'id' => $medicoId]);

    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('GET', "/api/medicos/{$medicoId}"));
    comprobar(
        'el médico ve su propio QR',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['qr_url'] ?? '') === '/uploads/qr/medico-test.png'
    );

    cambiarSesion($recepcion);
    $respuesta = $enviar(solicitud('GET', "/api/medicos/{$medicoId}"));
    comprobar(
        'recepción ve el QR del médico',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['qr_url'] ?? '') === '/uploads/qr/medico-test.png'
    );

    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('GET', "/api/medicos/{$medicoId}"));
    comprobar(
        'recepción médica ve el QR de su médico asignado',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['qr_url'] ?? '') === '/uploads/qr/medico-test.png'
    );

    cambiarSesion($admin);
    $respuesta = $enviar(solicitud('GET', "/api/medicos/{$medicoId}"));
    comprobar(
        'admin ve el QR de cualquier médico',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['qr_url'] ?? '') === '/uploads/qr/medico-test.png'
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    DB::clearContext();
    $_SESSION = [];
}

if ($fallos > 0) {
    echo PHP_EOL . $fallos . ' prueba(s) fallaron.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Todas las pruebas de pagos y QR del médico (M-32) pasaron.' . PHP_EOL;
