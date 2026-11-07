#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cobros del nuevo modelo de negocio: adelanto del 50 %, saldo al terminar,
 * reprogramación con transferencia del adelanto, reembolso con comprobante y
 * catálogo de laboratorio con precios de recepción.
 */

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Models\ConfiguracionModel;
use App\Models\OrdenModel;
use App\Models\PagoModel;
use App\Services\CobroService;
use App\Services\DisponibilidadService;
use App\Services\KioscoService;

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
 * Construye una petición como si viniera del navegador.
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

/**
 * Busca un horario libre para el médico en los próximos 60 días.
 *
 * @return array{fecha: string, hora: string}|null
 */
function buscarSlot(DisponibilidadService $disponibilidad, int $medicoId, string $canal = 'web'): ?array
{
    for ($dia = 1; $dia <= 60; $dia++) {
        $fecha = (new DateTimeImmutable('today'))->modify('+' . $dia . ' days')->format('Y-m-d');

        foreach ($disponibilidad->slots($medicoId, $fecha, $canal) as $slot) {
            if (($slot['disponible'] ?? false) === true) {
                return ['fecha' => $fecha, 'hora' => (string) $slot['hora']];
            }
        }
    }

    return null;
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
$medico = [
    'id' => 5,
    'rol' => 'medico',
    'nombre' => 'Dr. Carlos Méndez',
    'medico_id' => 1,
    'medico_ids' => [1],
    'paciente_id' => null,
];

$medicos = $pdo->query(
    "SELECT m.id, m.nombres, m.apellidos,
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

foreach ($medicos as $fila) {
    if ((int) $fila['id'] === 1) {
        $medico1 = $fila;
    }
}

$pacientes = $pdo->query('SELECT id, ci FROM pacientes ORDER BY id LIMIT 2')->fetchAll();

if (
    !is_array($medico1)
    || (int) ($medico1['especialidad_id'] ?? 0) <= 0
    || count($pacientes) < 2
) {
    fwrite(STDERR, 'ERROR: faltan datos seed para las pruebas de cobros.' . PHP_EOL);
    exit(1);
}

$pacienteId = (int) $pacientes[0]['id'];
$pacienteCi = (string) $pacientes[0]['ci'];
$pacienteAjenoId = (int) $pacientes[1]['id'];
$medicoId = (int) $medico1['id'];
$especialidadId = (int) $medico1['especialidad_id'];

$paciente = [
    'id' => 6,
    'rol' => 'paciente',
    'nombre' => 'Paciente de prueba',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => $pacienteId,
];

$crearCita = static function (
    PDO $pdo,
    int $pacienteId,
    int $medicoId,
    int $especialidadId,
    string $fecha,
    string $horaInicio,
    string $horaFin
): int {
    $statement = $pdo->prepare(
        "INSERT INTO citas (
             paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
             estado, origen, motivo
         ) VALUES (
             :paciente, :medico, :especialidad, CAST(:fecha AS date),
             CAST(:hora_inicio AS time), CAST(:hora_fin AS time),
             'programada', 'recepcion', 'Cita de prueba de cobros'
         ) RETURNING id"
    );
    $statement->execute([
        'paciente' => $pacienteId,
        'medico' => $medicoId,
        'especialidad' => $especialidadId,
        'fecha' => $fecha,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin,
    ]);

    return (int) $statement->fetchColumn();
};

$crearConsulta = static function (
    PDO $pdo,
    int $citaId,
    int $pacienteId,
    int $medicoId
): int {
    $statement = $pdo->prepare(
        "INSERT INTO consultas (
             cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales,
             examen_fisico, diagnostico, tratamiento, indicaciones, fecha
         ) VALUES (
             :cita, :paciente, :medico, 'Control de prueba', 'Evolución favorable',
             CAST('{\"pa\":\"120/80\",\"fc\":72}' AS jsonb),
             'Sin signos de alarma', 'Diagnóstico de prueba', 'Continuar control',
             'Control en 30 días', now()
         ) RETURNING id"
    );
    $statement->execute([
        'cita' => $citaId,
        'paciente' => $pacienteId,
        'medico' => $medicoId,
    ]);

    return (int) $statement->fetchColumn();
};

$configuracion = new ConfiguracionModel();
$cobros = new CobroService();
$pagos = new PagoModel();
$ordenes = new OrdenModel();
$disponibilidad = new DisponibilidadService();
$kiosco = new KioscoService();

$pdo->beginTransaction();

try {
    // Configuración de negocio --------------------------------------------
    $config = $configuracion->pago();

    comprobar(
        'la consulta cuesta 300 Bs para todas las especialidades',
        abs($config['precio_consulta'] - 300.0) < 0.001
    );
    comprobar(
        'el adelanto configurado es del 50 %',
        abs($config['adelanto_porcentaje'] - 50.0) < 0.001
    );
    comprobar(
        'el adelanto de la consulta es de 150 Bs',
        abs(round($config['precio_consulta'] * $config['adelanto_porcentaje'] / 100, 2) - 150.0) < 0.001
    );
    comprobar(
        'la vigencia del adelanto es de 120 minutos',
        $config['pago_expira_minutos'] === 120
    );

    // Adelanto y saldo de una cita ---------------------------------------
    $citaAId = $crearCita($pdo, $pacienteId, $medicoId, $especialidadId, '2099-12-20', '09:00', '09:30');
    $citaA = $pdo->query('SELECT codigo FROM citas WHERE id = ' . $citaAId)->fetch();
    $citaACodigo = (string) ($citaA['codigo'] ?? '');

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto', [], [], false));
    comprobar(
        'iniciar el adelanto exige CSRF',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH'
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto'));
    $resumenA = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el paciente inicia el adelanto de su cita',
        $respuesta->status() === 200
        && ($resumenA['estado_pago'] ?? '') === 'pendiente'
        && abs((float) ($resumenA['precio_total'] ?? 0) - 300.0) < 0.001
        && abs((float) ($resumenA['adelanto'] ?? 0) - 150.0) < 0.001
        && abs((float) ($resumenA['saldo'] ?? 0) - 150.0) < 0.001
        && ($resumenA['requiere_adelanto'] ?? false) === true
    );
    comprobar(
        'el adelanto devuelve QR, referencia y vencimiento',
        ($resumenA['qr']['contenido'] ?? '') !== ''
        && ($resumenA['qr']['titular'] ?? '') !== ''
        && ($resumenA['qr']['referencia'] ?? '') === 'PAGO-' . $citaACodigo
        && ($resumenA['expira_en'] ?? null) !== null
    );

    $expiraInicial = (string) ($resumenA['expira_en'] ?? '');
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto'));
    comprobar(
        'iniciar el adelanto es idempotente y no renueva el plazo',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['expira_en'] ?? '') === $expiraInicial
    );

    $kioscoPendiente = $kiosco->estadoPago($citaAId);
    comprobar(
        'el kiosco no emite ficha mientras el adelanto está pendiente',
        ($kioscoPendiente['estado_pago'] ?? '') === 'pendiente'
        && ($kioscoPendiente['puede_emitir_ficha'] ?? true) === false
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto/confirmar', [], [
        'metodo' => 'qr',
        'monto' => 150,
    ]));
    comprobar(
        'el paciente no puede confirmar el adelanto',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN'
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto/confirmar', [], [
        'metodo' => 'qr',
        'monto' => 100,
    ]));
    comprobar(
        'recepción rechaza un monto distinto al adelanto',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['monto'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto/confirmar', [], [
        'metodo' => 'qr',
        'monto' => 150,
        'referencia' => 'TRX-001',
    ]));
    $resumenA = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'recepción confirma el adelanto y la cita pasa a confirmada',
        $respuesta->status() === 200
        && ($resumenA['estado_pago'] ?? '') === 'adelanto_pagado'
        && abs((float) ($resumenA['pagado'] ?? 0) - 150.0) < 0.001
        && abs((float) ($resumenA['saldo'] ?? 0) - 150.0) < 0.001
    );

    $citaAAdmin = $pdo->query('SELECT estado, estado_pago FROM citas WHERE id = ' . $citaAId)->fetch();
    comprobar(
        'la cita confirmada queda lista para la atención',
        ($citaAAdmin['estado'] ?? '') === 'confirmada'
        && ($citaAAdmin['estado_pago'] ?? '') === 'adelanto_pagado'
    );

    $kioscoPagado = $kiosco->estadoPago($citaAId);
    comprobar(
        'el kiosco habilita la ficha al confirmar el adelanto',
        ($kioscoPagado['puede_emitir_ficha'] ?? false) === true
    );

    $adelantosA = array_values(array_filter(
        $pagos->porCita($citaAId),
        static fn (array $pago): bool => ($pago['tipo'] ?? '') === 'adelanto'
    ));
    comprobar(
        'el adelanto queda registrado una sola vez y ligado a la cita',
        count($adelantosA) === 1
        && ($adelantosA[0]['estado'] ?? '') === 'confirmado'
        && abs((float) ($adelantosA[0]['monto'] ?? 0) - 150.0) < 0.001
        && (int) ($adelantosA[0]['cita_id'] ?? 0) === $citaAId
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/adelanto/confirmar', [], [
        'metodo' => 'qr',
        'monto' => 150,
    ]));
    comprobar(
        'confirmar dos veces no duplica el adelanto',
        $respuesta->status() === 200
        && count(array_filter(
            $pagos->porCita($citaAId),
            static fn (array $pago): bool => ($pago['tipo'] ?? '') === 'adelanto'
        )) === 1
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/saldo', [], [
        'metodo' => 'efectivo',
        'monto' => 200,
    ]));
    comprobar(
        'recepción rechaza un saldo mayor al pendiente',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['monto'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAId . '/saldo', [], [
        'metodo' => 'efectivo',
        'monto' => 150,
    ]));
    $resumenA = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'recepción cobra el saldo al terminar la consulta',
        $respuesta->status() === 200
        && ($resumenA['estado_pago'] ?? '') === 'pagado'
        && abs((float) ($resumenA['pagado'] ?? 0) - 300.0) < 0.001
        && abs((float) ($resumenA['saldo'] ?? 1) - 0.0) < 0.001
    );

    $respuesta = $enviar(solicitud('GET', '/api/cobros/pendientes'));
    $pendientes = cuerpo($respuesta)['data'] ?? [];
    $idsSaldos = array_map(
        static fn (array $fila): int => (int) ($fila['cita_id'] ?? 0),
        $pendientes['saldos'] ?? []
    );
    comprobar(
        'la caja deja de mostrar la cita ya pagada',
        $respuesta->status() === 200 && !in_array($citaAId, $idsSaldos, true)
    );

    // Catálogo de exámenes con precios de recepción ------------------------
    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/examenes'));
    comprobar(
        'el paciente no administra el catálogo de exámenes',
        $respuesta->status() === 403
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('POST', '/api/examenes', [], [
        'nombre' => 'Examen de prueba de cobros',
        'tipo' => 'invalido',
        'precio' => 50,
    ]));
    comprobar(
        'recepción valida el tipo de examen',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['tipo'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/examenes', [], [
        'codigo' => 't-cob',
        'nombre' => 'Examen de prueba de cobros',
        'tipo' => 'laboratorio',
        'precio' => 75.50,
    ]));
    $examenCreado = cuerpo($respuesta)['data'] ?? [];
    $examenId = (int) ($examenCreado['id'] ?? 0);
    comprobar(
        'recepción registra un examen con su precio',
        $respuesta->status() === 201
        && $examenId > 0
        && ($examenCreado['codigo'] ?? '') === 'T-COB'
        && abs((float) ($examenCreado['precio'] ?? 0) - 75.50) < 0.001
        && ($examenCreado['activo'] ?? false) === true
    );

    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('GET', '/api/examenes', ['solo_activos' => '1']));
    $catalogo = cuerpo($respuesta)['data'] ?? [];
    $idsCatalogo = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        $catalogo
    );
    comprobar(
        'el médico lee el catálogo activo con precios',
        $respuesta->status() === 200
        && in_array($examenId, $idsCatalogo, true)
        && abs((float) (($catalogo[array_search($examenId, $idsCatalogo, true)]['precio'] ?? 0)) - 75.50) < 0.001
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('PUT', '/api/examenes/' . $examenId, [], [
        'nombre' => 'Examen de prueba de cobros',
        'tipo' => 'laboratorio',
        'precio' => 95,
        'activo' => true,
    ]));
    comprobar(
        'recepción actualiza el precio del catálogo',
        $respuesta->status() === 200
        && abs((float) (cuerpo($respuesta)['data']['precio'] ?? 0) - 95.0) < 0.001
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/examenes/' . $examenId . '/estado', [], [
        'activo' => false,
    ]));
    comprobar(
        'recepción desactiva un examen del catálogo',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['activo'] ?? true) === false
    );

    $respuesta = $enviar(solicitud('GET', '/api/examenes', ['solo_activos' => '1']));
    $idsActivos = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        cuerpo($respuesta)['data'] ?? []
    );
    comprobar(
        'el catálogo activo excluye los exámenes desactivados',
        !in_array($examenId, $idsActivos, true)
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/examenes/' . $examenId . '/estado', [], [
        'activo' => true,
    ]));
    comprobar(
        'recepción reactiva el examen',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['activo'] ?? false) === true
    );

    // Orden de laboratorio cobrada desde el catálogo ----------------------
    $citaFId = $crearCita($pdo, $pacienteId, $medicoId, $especialidadId, '2099-12-22', '10:00', '10:30');
    $consultaId = $crearConsulta($pdo, $citaFId, $pacienteId, $medicoId);

    $ordenId = $ordenes->crear($consultaId, $pacienteId, $medicoId, 'laboratorio', [
        ['examen_id' => $examenId],
    ]);
    $orden = $ordenes->porId($ordenId) ?? [];
    comprobar(
        'la orden copia el precio del catálogo al emitirse',
        $ordenId > 0
        && abs((float) ($orden['total'] ?? 0) - 95.0) < 0.001
        && ($orden['estado_pago'] ?? '') === 'pendiente'
        && (int) ($orden['detalle'][0]['examen_id'] ?? 0) === $examenId
        && abs((float) ($orden['detalle'][0]['precio'] ?? 0) - 95.0) < 0.001
    );

    $respuesta = $enviar(solicitud('PUT', '/api/examenes/' . $examenId, [], [
        'nombre' => 'Examen de prueba de cobros',
        'tipo' => 'laboratorio',
        'precio' => 500,
        'activo' => true,
    ]));
    comprobar(
        'recepción cambia el precio después de emitir la orden',
        $respuesta->status() === 200
        && abs((float) (cuerpo($respuesta)['data']['precio'] ?? 0) - 500.0) < 0.001
    );

    $orden = $ordenes->porId($ordenId) ?? [];
    comprobar(
        'un cambio de precio no altera una orden ya emitida',
        abs((float) ($orden['total'] ?? 0) - 95.0) < 0.001
        && abs((float) ($orden['detalle'][0]['precio'] ?? 0) - 95.0) < 0.001
    );

    $pendientesCobro = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        $ordenes->pendientesCobro()
    );
    comprobar(
        'la orden aparece en la cola de cobro de recepción',
        in_array($ordenId, $pendientesCobro, true)
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('POST', '/api/ordenes/' . $ordenId . '/pago', [], [
        'metodo' => 'efectivo',
        'monto' => 200,
    ]));
    comprobar(
        'recepción rechaza cobrar más que el saldo de la orden',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['monto'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/ordenes/' . $ordenId . '/pago', [], [
        'metodo' => 'efectivo',
    ]));
    $ordenPagada = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'recepción cobra la orden completa',
        $respuesta->status() === 200
        && ($ordenPagada['estado_pago'] ?? '') === 'pagado'
        && abs((float) ($ordenPagada['pagado'] ?? 0) - 95.0) < 0.001
        && abs((float) ($ordenPagada['saldo'] ?? 1) - 0.0) < 0.001
    );

    $pendientesCobro = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        $ordenes->pendientesCobro()
    );
    comprobar(
        'la orden pagada sale de la cola de cobro',
        !in_array($ordenId, $pendientesCobro, true)
    );

    // Cancelación definitiva con reembolso --------------------------------
    $citaBId = $crearCita($pdo, $pacienteId, $medicoId, $especialidadId, '2099-12-23', '11:00', '11:30');
    $cobros->iniciarAdelanto($citaBId);
    $cobros->confirmarAdelanto($citaBId, ['metodo' => 'qr', 'monto' => 150], $recepcionista);

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaBId . '/cancelacion/definitiva', [], [
        'motivo' => '',
    ]));
    comprobar(
        'la cancelación definitiva exige motivo',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['motivo'])
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaBId . '/cancelacion/definitiva', [], [
        'motivo' => 'Viaje imprevisto',
    ]));
    $cancelada = cuerpo($respuesta)['data'] ?? [];
    $comprobante = $cancelada['comprobante'] ?? [];
    comprobar(
        'la cancelación con adelanto genera comprobante de reembolso',
        $respuesta->status() === 200
        && ($cancelada['estado'] ?? '') === 'cancelada'
        && ($cancelada['estado_pago'] ?? '') === 'reembolso_pendiente'
        && str_starts_with((string) ($comprobante['codigo'] ?? ''), 'REE-')
        && abs((float) ($comprobante['monto'] ?? 0) - 150.0) < 0.001
        && ($comprobante['cita_codigo'] ?? '') === (string) ($cancelada['codigo'] ?? '')
        && ($comprobante['instrucciones'] ?? '') !== ''
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('GET', '/api/cobros/pendientes'));
    $reembolsos = array_map(
        static fn (array $fila): int => (int) ($fila['cita_id'] ?? 0),
        cuerpo($respuesta)['data']['reembolsos'] ?? []
    );
    comprobar(
        'la caja muestra el reembolso pendiente de entrega',
        in_array($citaBId, $reembolsos, true)
    );

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaBId . '/reembolso/entregar', [], [
        'metodo' => 'efectivo',
    ]));
    comprobar(
        'el paciente no puede marcar el reembolso como entregado',
        $respuesta->status() === 403
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaBId . '/reembolso/entregar', [], [
        'metodo' => 'efectivo',
    ]));
    $reembolsada = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'recepción entrega el reembolso y registra el movimiento',
        $respuesta->status() === 200
        && ($reembolsada['estado_pago'] ?? '') === 'reembolsado'
        && ($reembolsada['reembolso']['entregado_en'] ?? null) !== null
    );

    $pagosB = $pagos->porCita($citaBId);
    $reembolsoPago = null;
    $adelantoPago = null;

    foreach ($pagosB as $pago) {
        if (($pago['tipo'] ?? '') === 'reembolso') {
            $reembolsoPago = $pago;
        }

        if (($pago['tipo'] ?? '') === 'adelanto') {
            $adelantoPago = $pago;
        }
    }

    comprobar(
        'el reembolso se registra como pago ligado al adelanto original',
        $reembolsoPago !== null
        && $adelantoPago !== null
        && abs((float) $reembolsoPago['monto'] - 150.0) < 0.001
        && (int) $reembolsoPago['reembolso_de'] === (int) $adelantoPago['id']
        && (int) $reembolsoPago['cita_id'] === $citaBId
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaBId . '/reembolso/entregar', [], [
        'metodo' => 'efectivo',
    ]));
    comprobar(
        'entregar dos veces el reembolso devuelve INVALID_STATE',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_STATE'
    );

    // Reprogramación con transferencia del adelanto -----------------------
    $citaCId = $crearCita($pdo, $pacienteId, $medicoId, $especialidadId, '2099-12-24', '12:00', '12:30');
    $cobros->iniciarAdelanto($citaCId);
    $cobros->confirmarAdelanto($citaCId, ['metodo' => 'qr', 'monto' => 150], $recepcionista);

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/citas/' . $citaCId . '/cancelacion/opciones'));
    $opciones = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la cancelación sugiere el mismo día y hora de la semana siguiente',
        $respuesta->status() === 200
        && ($opciones['sugerencia']['fecha'] ?? '') === '2099-12-31'
        && ($opciones['sugerencia']['hora'] ?? '') === '12:00'
        && ($opciones['puede_reembolsar'] ?? false) === true
        && abs((float) ($opciones['reembolso']['monto'] ?? 0) - 150.0) < 0.001
    );

    $slot = buscarSlot($disponibilidad, $medicoId, 'web');

    if ($slot === null) {
        comprobar('hay un horario libre para reprogramar', false);
    } else {
        $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaCId . '/cancelacion/reprogramar', [], [
            'fecha' => $slot['fecha'],
            'hora' => $slot['hora'],
            'motivo' => 'Cambio de fecha solicitado por el paciente',
        ]));
        $reprogramada = cuerpo($respuesta)['data'] ?? [];
        $nueva = $reprogramada['reconsulta'] ?? [];
        $nuevaId = (int) ($nueva['id'] ?? 0);

        comprobar(
            'la reprogramación crea una reconsulta ligada a la cita origen',
            $respuesta->status() === 201
            && $nuevaId > 0
            && ($nueva['fecha'] ?? '') === $slot['fecha']
            && ($nueva['hora_inicio'] ?? '') === $slot['hora']
            && ($nueva['es_reconsulta'] ?? false) === true
            && (int) ($nueva['cita_origen_id'] ?? 0) === $citaCId
        );

        comprobar(
            'la reprogramación transfiere el adelanto sin pedirlo de nuevo',
            ($reprogramada['pago']['estado_pago'] ?? '') === 'adelanto_pagado'
            && abs((float) ($reprogramada['pago']['adelanto'] ?? 0) - 150.0) < 0.001
            && abs((float) ($reprogramada['pago']['saldo'] ?? 0) - 150.0) < 0.001
            && abs((float) ($reprogramada['pago']['pagado'] ?? 0) - 150.0) < 0.001
        );

        $origenAdmin = $pdo->query(
            'SELECT estado, estado_pago, reembolso_codigo FROM citas WHERE id = ' . $citaCId
        )->fetch();
        comprobar(
            'la cita origen queda cancelada sin generar reembolso',
            ($origenAdmin['estado'] ?? '') === 'cancelada'
            && ($origenAdmin['estado_pago'] ?? '') === 'transferido'
            && ($origenAdmin['reembolso_codigo'] ?? null) === null
        );

        $pagosNueva = $pagos->porCita($nuevaId);
        $adelantoNueva = null;

        foreach ($pagosNueva as $pago) {
            if (($pago['tipo'] ?? '') === 'adelanto') {
                $adelantoNueva = $pago;
            }
        }

        comprobar(
            'el adelanto transferido queda auditado en la nueva cita',
            $adelantoNueva !== null
            && abs((float) $adelantoNueva['monto'] - 150.0) < 0.001
            && str_contains((string) ($adelantoNueva['referencia'] ?? ''), 'reprogramado')
        );
    }

    // Expiración del adelanto no pagado -----------------------------------
    cambiarSesion($recepcionista);
    $citaDId = $crearCita($pdo, $pacienteId, $medicoId, $especialidadId, '2099-12-25', '13:00', '13:30');
    $cobros->iniciarAdelanto($citaDId);
    $pdo->prepare(
        "UPDATE citas SET pago_expira_en = now() - INTERVAL '1 minute' WHERE id = :id"
    )->execute(['id' => $citaDId]);

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('POST', '/api/cobros/expirar'));
    comprobar(
        'recepción libera los adelantos vencidos',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['expiradas'] ?? 0) >= 1
    );

    $citaD = $pdo->query(
        'SELECT estado, estado_pago FROM citas WHERE id = ' . $citaDId
    )->fetch();
    comprobar(
        'la cita sin adelanto vence y libera el cupo',
        ($citaD['estado'] ?? '') === 'cancelada'
        && ($citaD['estado_pago'] ?? '') === 'expirado'
    );

    // Inasistencia: se retiene el adelanto --------------------------------
    $citaEId = $crearCita($pdo, $pacienteId, $medicoId, $especialidadId, '2099-12-26', '14:00', '14:30');
    $cobros->iniciarAdelanto($citaEId);
    $cobros->confirmarAdelanto($citaEId, ['metodo' => 'qr', 'monto' => 150], $recepcionista);

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaEId . '/inasistencia'));
    $citaE = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la inasistencia retiene el adelanto',
        $respuesta->status() === 200
        && ($citaE['estado'] ?? '') === 'inasistencia'
        && ($citaE['estado_pago'] ?? '') === 'perdido'
    );

    // Alcance: el paciente no reprograma citas ajenas ----------------------
    cambiarSesion($recepcionista);
    $citaAjenaId = $crearCita($pdo, $pacienteAjenoId, $medicoId, $especialidadId, '2099-12-27', '15:00', '15:30');

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/citas/' . $citaAjenaId . '/cancelacion/opciones'));
    comprobar(
        'el paciente no accede a la cancelación de citas ajenas',
        $respuesta->status() === 403
    );
} catch (Throwable $exception) {
    $fallos++;
    echo 'FALLO excepción inesperada: ' . $exception->getMessage() . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    DB::clearContext();
}

echo PHP_EOL
    . ($fallos === 0 ? 'Todas las pruebas de cobros pasaron.' : $fallos . ' prueba(s) fallaron.')
    . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
