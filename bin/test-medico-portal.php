#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-35 - Flujo cruzado médico + portal.
 *
 * Recorre una consulta completa: el médico atiende una cita, registra la
 * historia, emite una receta, la envía por WhatsApp y cobra; después el
 * paciente consulta su historial, lee la receta y cancela otra cita propia.
 * También comprueba que los reportes administrativos reflejan el resultado.
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

function crearCita(
    PDO $pdo,
    int $pacienteId,
    int $medicoId,
    int $especialidadId,
    string $fecha,
    string $horaInicio,
    string $horaFin,
    string $estado
): int {
    $statement = $pdo->prepare(
        "INSERT INTO citas (
             paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
             estado, origen, motivo
         ) VALUES (
             :paciente, :medico, :especialidad, CAST(:fecha AS date),
             CAST(:hora_inicio AS time), CAST(:hora_fin AS time),
             CAST(:estado AS estado_cita), 'recepcion', 'Flujo M-35'
         )
         RETURNING id"
    );
    $statement->execute([
        'paciente' => $pacienteId,
        'medico' => $medicoId,
        'especialidad' => $especialidadId,
        'fecha' => $fecha,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin,
        'estado' => $estado,
    ]);

    return (int) $statement->fetchColumn();
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

$medicoDb = fila(
    $pdo,
    "SELECT m.id AS medico_id, e.id AS especialidad_id
     FROM medicos m
     JOIN medico_especialidad me ON me.medico_id = m.id
     JOIN especialidades e ON e.id = me.especialidad_id
     WHERE m.codigo = 'MED-DEMO-001' AND e.nombre = 'Medicina General'
     LIMIT 1"
);
$pacienteDb = fila(
    $pdo,
    "SELECT p.id, p.usuario_id, u.id AS usuario_id
     FROM pacientes p
     JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.codigo = 'PAC-DEMO-001'
     LIMIT 1"
);
$otroPacienteDb = fila($pdo, "SELECT id FROM pacientes WHERE codigo = 'PAC-DEMO-002' LIMIT 1");
$adminDb = fila($pdo, "SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1");
$medicoUsuarioDb = fila($pdo, "SELECT id FROM usuarios WHERE username = 'medico' LIMIT 1");

if (!is_array($medicoDb) || !is_array($pacienteDb) || !is_array($otroPacienteDb)
    || !is_array($adminDb) || !is_array($medicoUsuarioDb)) {
    fwrite(STDERR, 'ERROR: faltan los datos seed para las pruebas médico + portal.' . PHP_EOL);
    exit(1);
}

$medicoId = (int) $medicoDb['medico_id'];
$especialidadId = (int) $medicoDb['especialidad_id'];
$pacienteId = (int) $pacienteDb['id'];
$otroPacienteId = (int) $otroPacienteDb['id'];

$admin = [
    'id' => (int) $adminDb['id'], 'rol' => 'admin', 'nombre' => 'admin',
    'medico_id' => null, 'medico_ids' => [], 'paciente_id' => null,
];
$medico = [
    'id' => (int) $medicoUsuarioDb['id'], 'rol' => 'medico', 'nombre' => 'Dr. Carlos Mendez',
    'medico_id' => $medicoId, 'medico_ids' => [$medicoId], 'paciente_id' => null,
];
$paciente = [
    'id' => (int) $pacienteDb['usuario_id'], 'rol' => 'paciente', 'nombre' => 'Juan Perez',
    'medico_id' => null, 'medico_ids' => [], 'paciente_id' => $pacienteId,
];

$fechaConsulta = date('Y-m-d', strtotime('+30 days'));
$fechaCancelar = date('Y-m-d', strtotime('+31 days'));
$fechaAjena = date('Y-m-d', strtotime('+32 days'));

$pdo->beginTransaction();

try {
    DB::clearContext();

    // El envío por WhatsApp exige un teléfono; se fija uno de prueba dentro
    // de la transacción para no depender de los datos demo.
    $pdo->prepare('UPDATE pacientes SET telefono = :telefono WHERE id = :id')
        ->execute(['telefono' => '70000000', 'id' => $pacienteId]);

    $citaConsulta = crearCita($pdo, $pacienteId, $medicoId, $especialidadId, $fechaConsulta, '09:00', '09:30', 'confirmada');
    $citaCancelar = crearCita($pdo, $pacienteId, $medicoId, $especialidadId, $fechaCancelar, '10:00', '10:30', 'programada');
    $citaAjena = crearCita($pdo, $otroPacienteId, $medicoId, $especialidadId, $fechaAjena, '11:00', '11:30', 'programada');

    $codigoConsulta = (string) (fila(
        $pdo,
        'SELECT codigo FROM citas WHERE id = :id',
        ['id' => $citaConsulta]
    )['codigo'] ?? '');

    comprobar(
        'se preparan las citas del flujo médico + portal',
        $citaConsulta > 0 && $citaCancelar > 0 && $citaAjena > 0 && $codigoConsulta !== ''
    );

    // -- Médico: agenda, consulta, historia, receta, WhatsApp y pago --------
    cambiarSesion($medico);

    $respuesta = $enviar(solicitud('GET', '/api/dashboard/medico', ['fecha' => $fechaConsulta]));
    $dashboard = cuerpo($respuesta)['data'] ?? [];
    $agendaIds = array_map(
        static fn (array $cita): int => (int) ($cita['id'] ?? 0),
        $dashboard['agenda'] ?? []
    );

    comprobar('el médico ve la cita en su dashboard', $respuesta->status() === 200
        && (int) ($dashboard['citas_hoy'] ?? 0) >= 1
        && in_array($citaConsulta, $agendaIds, true));

    $respuesta = $enviar(solicitud('GET', "/api/citas/{$citaConsulta}"));
    comprobar('el médico consulta el detalle de la cita', $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['codigo'] ?? '') === $codigoConsulta);

    $payloadConsulta = [
        'motivo' => 'Dolor abdominal',
        'anamnesis' => 'Paciente refiere dolor epigástrico desde hace dos días.',
        'signos_vitales' => [
            'pa' => '118/76',
            'fc' => 74,
            'temp' => 36.6,
            'peso' => 70,
            'spo2' => 98,
            'talla' => 1.70,
        ],
        'examen_fisico' => 'Abdomen blando, dolor a la palpación epigástrica.',
        'diagnostico' => 'Gastritis aguda.',
        'tratamiento' => 'Omeprazol 20 mg cada 24 horas por 14 días.',
        'indicaciones' => 'Dieta blanda, evitar irritantes y control en dos semanas.',
    ];

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaConsulta}/consulta", [], $payloadConsulta));
    $consulta = cuerpo($respuesta)['data'] ?? [];
    $consultaId = (int) ($consulta['id'] ?? 0);

    comprobar(
        'el médico registra la consulta completa y la cita queda atendida',
        $respuesta->status() === 201
        && $consultaId > 0
        && ($consulta['motivo'] ?? '') === 'Dolor abdominal'
        && ($consulta['signos_vitales']['pa'] ?? '') === '118/76'
        && ($consulta['cita']['estado'] ?? '') === 'atendida'
    );

    $respuesta = $enviar(solicitud('GET', "/api/pacientes/{$pacienteId}/historia"));
    $historiaMedico = cuerpo($respuesta)['data'] ?? [];
    $consultasHistoria = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        $historiaMedico['consultas'] ?? []
    );

    comprobar('el médico ve la consulta en la historia del paciente',
        $respuesta->status() === 200 && in_array($consultaId, $consultasHistoria, true));

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/recetas", [], [
        'detalle' => [
            [
                'medicamento' => 'Omeprazol 20 mg',
                'dosis' => '1 cápsula',
                'via' => 'oral',
                'frecuencia' => 'cada 24 horas',
                'duracion' => '14 días',
                'instrucciones' => 'En ayunas',
            ],
            ['medicamento' => 'Paracetamol 500 mg'],
        ],
    ]));
    $receta = cuerpo($respuesta)['data'] ?? [];
    $recetaId = (int) ($receta['id'] ?? 0);

    comprobar(
        'el médico emite la receta con código y acceso',
        $respuesta->status() === 201
        && $recetaId > 0
        && str_starts_with((string) ($receta['codigo'] ?? ''), 'RSV-')
        && preg_match('/^\d{4}$/', (string) ($receta['codigo_acceso'] ?? '')) === 1
        && count($receta['detalle'] ?? []) === 2
    );

    $respuesta = $enviar(solicitud('POST', "/api/pedidos/receta/{$recetaId}/enviar"));
    $envio = cuerpo($respuesta)['data'] ?? [];

    comprobar(
        'el médico envía la receta por WhatsApp',
        $respuesta->status() === 200
        && str_starts_with((string) ($envio['whatsapp_url'] ?? ''), 'https://wa.me/591')
        && str_contains((string) ($envio['mensaje'] ?? ''), (string) ($receta['codigo'] ?? ''))
    );

    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar('la receta queda marcada como enviada',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['enviado_whatsapp'] ?? null) === true);

    $respuesta = $enviar(solicitud('POST', '/api/pagos', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'monto' => 180.50,
        'metodo' => 'efectivo',
        'referencia_paciente' => 'M35-consulta',
    ]));
    $pago = cuerpo($respuesta)['data'] ?? [];
    $pagoId = (int) ($pago['id'] ?? 0);

    comprobar(
        'el médico registra el pago de la consulta',
        $respuesta->status() === 201
        && $pagoId > 0
        && (float) ($pago['monto'] ?? 0) === 180.50
        && ($pago['registrado_por']['username'] ?? '') === 'medico'
    );

    $respuesta = $enviar(solicitud('GET', '/api/pagos', ['per_page' => '100']));
    $pagosIds = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        cuerpo($respuesta)['data'] ?? []
    );

    comprobar('el pago aparece en el listado del médico',
        $respuesta->status() === 200 && in_array($pagoId, $pagosIds, true));

    // -- Paciente: historial, receta, cancelación y aislamiento -------------
    cambiarSesion($paciente);

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', ['per_page' => '100']));
    $misCitas = array_map(
        static fn (array $cita): int => (int) ($cita['id'] ?? 0),
        cuerpo($respuesta)['data'] ?? []
    );

    comprobar(
        'el paciente ve sus citas en el portal',
        $respuesta->status() === 200
        && in_array($citaConsulta, $misCitas, true)
        && in_array($citaCancelar, $misCitas, true)
        && !in_array($citaAjena, $misCitas, true)
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/historial'));
    $historial = cuerpo($respuesta)['data'] ?? [];
    $citasHistorial = array_map(
        static fn (array $cita): int => (int) ($cita['id'] ?? 0),
        $historial['citas'] ?? []
    );
    $consultasPaciente = array_map(
        static fn (array $fila): int => (int) ($fila['id'] ?? 0),
        $historial['consultas'] ?? []
    );

    comprobar(
        'el historial del paciente integra la cita atendida y la consulta',
        $respuesta->status() === 200
        && in_array($citaConsulta, $citasHistorial, true)
        && in_array($consultaId, $consultasPaciente, true)
        && (int) ($historial['total_citas'] ?? 0) === count($citasHistorial)
        && (int) ($historial['total_consultas'] ?? 0) === count($consultasPaciente)
    );

    $respuesta = $enviar(solicitud('GET', "/api/citas/{$citaConsulta}"));
    comprobar('el paciente consulta su cita atendida',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'atendida');

    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar('el paciente lee la receta emitida por su médico',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['codigo'] ?? '') === ($receta['codigo'] ?? ''));

    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}/imprimir"));
    comprobar('el paciente abre la receta imprimible',
        $respuesta->status() === 200
        && str_contains($respuesta->body(), (string) ($receta['codigo'] ?? '')));

    $respuesta = $enviar(solicitud('GET', "/api/pacientes/{$pacienteId}/historial"));
    $historialPortal = cuerpo($respuesta)['data'] ?? [];
    $citasPortal = array_map(
        static fn (array $cita): int => (int) ($cita['id'] ?? 0),
        $historialPortal['citas'] ?? []
    );

    comprobar('el paciente consulta su historial de citas',
        $respuesta->status() === 200 && in_array($citaConsulta, $citasPortal, true));

    $respuesta = $enviar(solicitud('PATCH', "/api/citas/{$citaCancelar}/cancelar", [], [
        'motivo' => 'Viaje imprevisto',
    ]));
    $cancelada = cuerpo($respuesta)['data'] ?? [];

    comprobar(
        'el paciente cancela una cita futura propia',
        $respuesta->status() === 200
        && ($cancelada['estado'] ?? '') === 'cancelada'
        && ($cancelada['motivo_cancelacion'] ?? '') === 'Viaje imprevisto'
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', [
        'estado' => 'cancelada',
        'per_page' => '100',
    ]));
    $canceladas = array_map(
        static fn (array $cita): int => (int) ($cita['id'] ?? 0),
        cuerpo($respuesta)['data'] ?? []
    );

    comprobar('la cita cancelada aparece en mis citas',
        $respuesta->status() === 200 && in_array($citaCancelar, $canceladas, true));

    $respuesta = $enviar(solicitud('GET', '/api/me/historial', ['estado' => 'cancelada']));
    $historialCancelado = cuerpo($respuesta)['data'] ?? [];
    $canceladasHistorial = array_map(
        static fn (array $cita): int => (int) ($cita['id'] ?? 0),
        $historialCancelado['citas'] ?? []
    );

    comprobar('la cita cancelada aparece en el historial filtrado',
        $respuesta->status() === 200 && in_array($citaCancelar, $canceladasHistorial, true));

    $respuesta = $enviar(solicitud('PATCH', "/api/citas/{$citaConsulta}/cancelar", [], [
        'motivo' => 'Intento inválido',
    ]));
    comprobar('no se cancela una cita ya atendida',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_STATE');

    $respuesta = $enviar(solicitud('PATCH', "/api/citas/{$citaAjena}/cancelar", [], [
        'motivo' => 'Intento ajeno',
    ]));
    comprobar('el paciente no cancela una cita ajena', $respuesta->status() === 403);

    $respuesta = $enviar(solicitud('GET', "/api/citas/{$citaAjena}"));
    comprobar('el paciente no consulta una cita ajena', $respuesta->status() === 403);

    // -- Administración: reportes y pago -----------------------------------
    cambiarSesion($admin);

    $respuesta = $enviar(solicitud('GET', '/api/reportes/citas-por-medico', [
        'desde' => $fechaConsulta,
        'hasta' => $fechaConsulta,
    ]));
    $reporteMedicos = cuerpo($respuesta)['data'] ?? [];
    $filaMedico = null;

    foreach ($reporteMedicos as $filaReporte) {
        if ((int) ($filaReporte['medico_id'] ?? 0) === $medicoId) {
            $filaMedico = $filaReporte;
            break;
        }
    }

    comprobar(
        'el reporte administrativo refleja la consulta atendida',
        $respuesta->status() === 200
        && is_array($filaMedico)
        && (int) ($filaMedico['total'] ?? 0) >= 1
        && (int) ($filaMedico['atendidas'] ?? 0) >= 1
    );

    $respuesta = $enviar(solicitud('GET', '/api/reportes/asistencia', [
        'desde' => $fechaConsulta,
        'hasta' => $fechaConsulta,
    ]));
    comprobar('el reporte de asistencia cuenta la cita atendida',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['atendidas'] ?? 0) >= 1);

    $respuesta = $enviar(solicitud('GET', "/api/pagos/{$pagoId}"));
    comprobar('el administrador consulta el pago registrado por el médico',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['id'] ?? 0) === $pagoId);

    $respuesta = $enviar(solicitud('GET', "/api/citas/{$citaConsulta}"));
    comprobar('el administrador ve la cita atendida',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'atendida');

    $respuesta = $enviar(solicitud('GET', "/api/citas/{$citaCancelar}"));
    comprobar('el administrador ve la cita cancelada',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'cancelada');
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

echo PHP_EOL . 'Todas las pruebas de médico + portal (M-35) pasaron.' . PHP_EOL;
