#!/usr/bin/env php
<?php

declare(strict_types=1);

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
 * Construye una peticiA3n como si viniera del navegador.
 *
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

$pdo = DB::connection();
$router = new Router();
$servidor = $router;

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

$enviar = static function (Request $request) use ($servidor): Response {
    try {
        return $servidor->dispatch($request);
    } catch (HttpException $exception) {
        return json_error(
            $exception->errorCode() ?? 'ERROR',
            $exception->getMessage(),
            $exception->fields(),
            $exception->statusCode()
        );
    }
};

$admin = [
    'id' => 2,
    'rol' => 'admin',
    'nombre' => 'admin',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];
$recepcionista = [
    'id' => 3,
    'rol' => 'recepcionista',
    'nombre' => 'recepcion',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];
$recepcionistaMedico = [
    'id' => 4,
    'rol' => 'recepcionista_medico',
    'nombre' => 'recepcion.medico',
    'medico_id' => 1,
    'medico_ids' => [1, 2],
    'paciente_id' => null,
];
$medico = [
    'id' => 5,
    'rol' => 'medico',
    'nombre' => 'Dr. Carlos MAcndez',
    'medico_id' => 1,
    'medico_ids' => [1],
    'paciente_id' => null,
];
$paciente = [
    'id' => 6,
    'rol' => 'paciente',
    'nombre' => 'Juan PAcrez',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => 1,
];

$medicos = $pdo->query(
    "SELECT m.id, m.codigo, m.nombres, m.apellidos,
            (SELECT me.especialidad_id
             FROM medico_especialidad me
             WHERE me.medico_id = m.id
             ORDER BY me.especialidad_id
             LIMIT 1) AS especialidad_id
     FROM medicos m
     WHERE m.estado = 'activo'
     ORDER BY m.id"
)->fetchAll();

$medico1 = null;
$medicoOtro = null;

foreach ($medicos as $fila) {
    if ((int) $fila['id'] === 1) {
        $medico1 = $fila;
    }

    if ($medicoOtro === null && !in_array((int) $fila['id'], [1, 2], true)) {
        $medicoOtro = $fila;
    }
}

$pacientes = $pdo->query('SELECT id, ci FROM pacientes ORDER BY id LIMIT 2')->fetchAll();

if (
    !is_array($medico1)
    || (int) ($medico1['especialidad_id'] ?? 0) <= 0
    || !is_array($medicoOtro)
    || (int) ($medicoOtro['especialidad_id'] ?? 0) <= 0
    || count($pacientes) < 2
) {
    fwrite(STDERR, 'ERROR: faltan datos seed para las pruebas de fichas y pagos.' . PHP_EOL);
    exit(1);
}

$paciente1Id = (int) $pacientes[0]['id'];
$paciente2Id = (int) $pacientes[1]['id'];
$medico1Id = (int) $medico1['id'];
$medicoOtroId = (int) $medicoOtro['id'];
$hoy = date('Y-m-d');

$pdo->beginTransaction();

try {
    // Fichas de prueba (se revierten al terminar) --------------------------
    DB::clearContext();

    $crearCita = $pdo->prepare(
        "INSERT INTO citas (
             paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
             estado, origen
         ) VALUES (
             :paciente, :medico, :especialidad, CAST(:fecha AS date),
             CAST(:hora_inicio AS time), CAST(:hora_fin AS time),
             'confirmada', 'recepcion'
         )
         RETURNING id"
    );
    $crearFicha = $pdo->prepare(
        "INSERT INTO fichas (cita_id, canal, codigo_acceso)
         VALUES (:cita, 'kiosco', :codigo_acceso)
         RETURNING id, codigo"
    );

    $crearCita->execute([
        'paciente' => $paciente1Id,
        'medico' => $medico1Id,
        'especialidad' => (int) $medico1['especialidad_id'],
        'fecha' => '2099-12-31',
        'hora_inicio' => '23:00',
        'hora_fin' => '23:30',
    ]);
    $cita1Id = (int) $crearCita->fetchColumn();
    $crearFicha->execute(['cita' => $cita1Id, 'codigo_acceso' => '1234']);
    $ficha1 = $crearFicha->fetch();
    $ficha1Codigo = (string) ($ficha1['codigo'] ?? '');

    $crearCita->execute([
        'paciente' => $paciente2Id,
        'medico' => $medicoOtroId,
        'especialidad' => (int) $medicoOtro['especialidad_id'],
        'fecha' => '2099-12-31',
        'hora_inicio' => '23:00',
        'hora_fin' => '23:30',
    ]);
    $cita2Id = (int) $crearCita->fetchColumn();
    $crearFicha->execute(['cita' => $cita2Id, 'codigo_acceso' => '5678']);
    $ficha2 = $crearFicha->fetch();
    $ficha2Codigo = (string) ($ficha2['codigo'] ?? '');

    comprobar(
        'se preparan fichas de prueba para dos mAcdicos',
        $cita1Id > 0 && $cita2Id > 0 && $ficha1Codigo !== '' && $ficha2Codigo !== ''
    );

    // Permisos y listado de fichas ----------------------------------------
    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/fichas'));
    comprobar(
        'el paciente no puede listar fichas',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN'
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('GET', '/api/fichas', ['q' => $ficha1Codigo]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    $primera = $datos[0] ?? [];

    comprobar(
        'recepciA3n busca una ficha por cA3digo',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['meta']['total'] ?? 0) === 1
        && ($primera['codigo'] ?? '') === $ficha1Codigo
        && ($primera['cita_codigo'] ?? '') !== ''
        && ($primera['paciente']['codigo'] ?? '') !== ''
        && ($primera['medico']['id'] ?? 0) === $medico1Id
        && ($primera['especialidad']['nombre'] ?? '') !== ''
        && ($primera['fecha'] ?? '') === '2099-12-31'
        && ($primera['hora_inicio'] ?? '') === '23:00'
        && (int) ($primera['reimpresiones'] ?? -1) === 0
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas', [
        'q' => (string) $pacientes[0]['ci'],
        'fecha' => '2099-12-31',
    ]));
    $encontrados = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la bA-squeda de fichas combina CI y fecha',
        $respuesta->status() === 200
        && array_reduce(
            $encontrados,
            static fn (bool $ok, array $fila): bool => $ok || ($fila['codigo'] ?? '') === $ficha1Codigo,
            false
        )
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas', ['fecha' => 'fecha-invalida']));
    comprobar(
        'una fecha invA-lida devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['fecha'])
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas/FIC-NO-EXISTE'));
    comprobar(
        'una ficha inexistente devuelve FICHA_NOT_FOUND',
        $respuesta->status() === 404
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FICHA_NOT_FOUND'
    );

    // Detalle y reimpresiA3n ------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/fichas/' . $ficha1Codigo));
    $detalle = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el detalle devuelve la ficha completa',
        $respuesta->status() === 200
        && ($detalle['codigo'] ?? '') === $ficha1Codigo
        && ($detalle['codigo_acceso'] ?? '') === '1234'
        && ($detalle['cita_estado'] ?? '') === 'confirmada'
        && ($detalle['paciente']['id'] ?? 0) === $paciente1Id
    );

    $respuesta = $enviar(solicitud('POST', '/api/fichas/' . $ficha1Codigo . '/reimprimir', [], [], false));
    comprobar(
        'reimprimir sin CSRF se rechaza',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH'
    );

    $respuesta = $enviar(solicitud('POST', '/api/fichas/' . $ficha1Codigo . '/reimprimir'));
    $reimpresa = cuerpo($respuesta)['data']['ficha'] ?? [];
    comprobar(
        'reimprimir registra el contador y la fecha',
        $respuesta->status() === 200
        && (int) ($reimpresa['reimpresiones'] ?? 0) === 1
        && ($reimpresa['ultima_reimpresion_at'] ?? null) !== null
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas/' . $ficha1Codigo));
    $detalle = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el contador de reimpresiones se persiste',
        (int) ($detalle['reimpresiones'] ?? 0) === 1
    );

    // Alcance de recepciA3n mAcdica y mAcdico -------------------------------
    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('GET', '/api/fichas/' . $ficha1Codigo));
    comprobar(
        'recepciA3n mAcdica consulta fichas de sus mAcdicos',
        $respuesta->status() === 200
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas/' . $ficha2Codigo));
    comprobar(
        'recepciA3n mAcdica no consulta fichas de otro mAcdico',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas', ['q' => $ficha2Codigo]));
    comprobar(
        'el listado de recepciA3n mAcdica excluye fichas fuera de alcance',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['meta']['total'] ?? -1) === 0
    );

    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('GET', '/api/fichas/' . $ficha1Codigo));
    comprobar(
        'el mAcdico consulta sus propias fichas',
        $respuesta->status() === 200
    );

    $respuesta = $enviar(solicitud('GET', '/api/fichas/' . $ficha2Codigo));
    comprobar(
        'el mAcdico no consulta fichas de otro profesional',
        $respuesta->status() === 403
    );

    // Pagos: permisos y validaciones ---------------------------------------
    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/pagos'));
    comprobar(
        'el paciente no puede listar pagos',
        $respuesta->status() === 403
    );

    cambiarSesion($recepcionista);
    $payloadA = [
        'paciente_id' => $paciente1Id,
        'medico_id' => $medico1Id,
        'monto' => 120.50,
        'metodo' => 'efectivo',
        'referencia_paciente' => 'M28-A',
    ];

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], $payloadA, false));
    comprobar(
        'registrar pago sin CSRF se rechaza',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH'
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadA, [
        'metodo' => 'bitcoin',
    ])));
    comprobar(
        'un mActodo de pago invA-lido devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['metodo'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadA, [
        'monto' => 0,
    ])));
    comprobar(
        'un monto en cero devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['monto'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadA, [
        'monto' => 10.999,
    ])));
    comprobar(
        'un monto con mAis de dos decimales se rechaza',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['monto'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadA, [
        'paciente_id' => 999999,
    ])));
    comprobar(
        'un paciente inexistente devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['paciente_id'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadA, [
        'medico_id' => 999999,
    ])));
    comprobar(
        'un mAcdico inexistente devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['medico_id'])
    );

    // Pagos: alta, filtros y alcance ---------------------------------------
    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], $payloadA));
    $pagoA = cuerpo($respuesta)['data'] ?? [];
    $pagoAId = (int) ($pagoA['id'] ?? 0);
    comprobar(
        'recepciA3n registra un pago en efectivo',
        $respuesta->status() === 201
        && $pagoAId > 0
        && ($pagoA['metodo'] ?? '') === 'efectivo'
        && abs((float) ($pagoA['monto'] ?? 0) - 120.50) < 0.001
        && ($pagoA['fecha'] ?? '') === $hoy
        && ($pagoA['paciente']['nombre_completo'] ?? '') !== ''
        && ($pagoA['registrado_por']['username'] ?? '') === 'recepcion'
    );

    $payloadB = [
        'paciente_id' => $paciente2Id,
        'medico_id' => $medicoOtroId,
        'monto' => 80,
        'metodo' => 'visa',
        'referencia_paciente' => 'M28-B',
    ];
    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], $payloadB));
    $pagoB = cuerpo($respuesta)['data'] ?? [];
    $pagoBId = (int) ($pagoB['id'] ?? 0);
    comprobar(
        'recepciA3n registra un pago con tarjeta',
        $respuesta->status() === 201
        && $pagoBId > 0
        && ($pagoB['metodo'] ?? '') === 'visa'
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos', [
        'medico' => $medico1Id,
        'desde' => $hoy,
        'hasta' => $hoy,
        'per_page' => 100,
    ]));
    $filas = cuerpo($respuesta)['data'] ?? [];
    $ids = array_map(static fn (array $fila): int => (int) ($fila['id'] ?? 0), $filas);
    $medicosFiltrados = array_map(
        static fn (array $fila): int => (int) ($fila['medico']['id'] ?? 0),
        $filas
    );
    comprobar(
        'el listado de pagos filtra por mAcdico y fecha',
        $respuesta->status() === 200
        && in_array($pagoAId, $ids, true)
        && !in_array($pagoBId, $ids, true)
        && array_unique($medicosFiltrados) === [$medico1Id]
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos/' . $pagoAId));
    comprobar(
        'el detalle del pago devuelve el movimiento',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['id'] ?? 0) === $pagoAId
        && (cuerpo($respuesta)['data']['referencia_paciente'] ?? '') === 'M28-A'
    );

    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadB, [
        'referencia_paciente' => 'M28-OUT',
    ])));
    comprobar(
        'recepciA3n mAcdica no registra pagos de otro mAcdico',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], array_merge($payloadA, [
        'referencia_paciente' => 'M28-IN',
    ])));
    comprobar(
        'recepciA3n mAcdica registra pagos de sus mAcdicos',
        $respuesta->status() === 201
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos', ['desde' => $hoy, 'hasta' => $hoy]));
    $filas = cuerpo($respuesta)['data'] ?? [];
    $ids = array_map(static fn (array $fila): int => (int) ($fila['id'] ?? 0), $filas);
    comprobar(
        'el listado de recepciA3n mAcdica respeta su alcance',
        in_array($pagoAId, $ids, true) && !in_array($pagoBId, $ids, true)
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos/' . $pagoBId));
    comprobar(
        'recepciA3n mAcdica no consulta pagos de otro mAcdico',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos', [
        'medico' => $medicoOtroId,
        'desde' => $hoy,
        'hasta' => $hoy,
    ]));
    comprobar(
        'recepciA3n mAcdica no puede filtrar por un mAcdico ajeno',
        $respuesta->status() === 403
    );

    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('GET', '/api/pagos/' . $pagoAId));
    comprobar(
        'el mAcdico consulta sus propios pagos',
        $respuesta->status() === 200
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos/' . $pagoBId));
    comprobar(
        'el mAcdico no consulta pagos ajenos',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos', ['desde' => '2026-10-20', 'hasta' => '2026-10-01']));
    comprobar(
        'un rango de fechas invertido devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['hasta'])
    );
} catch (Throwable $exception) {
    $fallos++;
    echo 'FALLO excepciA3n inesperada: ' . $exception->getMessage() . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    DB::clearContext();
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas de fichas y pagos pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
