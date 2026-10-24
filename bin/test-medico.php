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
 * Construye una petición como si viniera del navegador.
 *
 * @param array<string, mixed> $query
 */
function solicitud(string $metodo, string $ruta, array $query = []): Request
{
    $getAnterior = $_GET;
    $serverAnterior = $_SERVER;

    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = strtoupper($metodo);
    $_SERVER['REQUEST_URI'] = $ruta . ($query === [] ? '' : '?' . http_build_query($query));
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['CONTENT_TYPE'] = '';

    try {
        return Request::capture();
    } finally {
        $_GET = $getAnterior;
        $_SERVER = $serverAnterior;
    }
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
             estado, origen
         ) VALUES (
             :paciente, :medico, :especialidad, CAST(:fecha AS date),
             CAST(:hora_inicio AS time), CAST(:hora_fin AS time),
             CAST(:estado AS estado_cita), 'recepcion'
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

/** @param array<string, mixed> $signos */
function crearConsulta(
    PDO $pdo,
    int $citaId,
    int $pacienteId,
    int $medicoId,
    string $motivo,
    array $signos,
    string $fecha
): int {
    $statement = $pdo->prepare(
        "INSERT INTO consultas (
             cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales,
             examen_fisico, diagnostico, tratamiento, indicaciones, fecha
         ) VALUES (
             :cita, :paciente, :medico, :motivo, :anamnesis, CAST(:signos AS jsonb),
             :examen, :diagnostico, :tratamiento, :indicaciones, CAST(:fecha AS timestamptz)
         )
         RETURNING id"
    );
    $statement->execute([
        'cita' => $citaId,
        'paciente' => $pacienteId,
        'medico' => $medicoId,
        'motivo' => $motivo,
        'anamnesis' => 'Paciente refiere evolución favorable.',
        'signos' => json_encode($signos, JSON_UNESCAPED_UNICODE),
        'examen' => 'Sin signos de alarma.',
        'diagnostico' => 'Control favorable.',
        'tratamiento' => 'Continuar indicaciones.',
        'indicaciones' => 'Control en 30 días.',
        'fecha' => $fecha,
    ]);

    return (int) $statement->fetchColumn();
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

/** @return array<string, mixed>|null */
function fila(PDO $pdo, string $sql, array $parametros = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parametros);
    $resultado = $statement->fetch();

    return is_array($resultado) ? $resultado : null;
}

$medicoDb = fila(
    $pdo,
    "SELECT m.id,
            (SELECT me.especialidad_id
             FROM medico_especialidad me
             WHERE me.medico_id = m.id
             ORDER BY me.especialidad_id
             LIMIT 1) AS especialidad_id
     FROM medicos m
     WHERE m.codigo = 'MED-DEMO-001'
     LIMIT 1"
);
$otroMedicoDb = fila(
    $pdo,
    "SELECT m.id,
            (SELECT me.especialidad_id
             FROM medico_especialidad me
             WHERE me.medico_id = m.id
             ORDER BY me.especialidad_id
             LIMIT 1) AS especialidad_id
     FROM medicos m
     WHERE m.id <> :medico_id
       AND m.estado = 'activo'
     ORDER BY m.id
     LIMIT 1",
    ['medico_id' => (int) ($medicoDb['id'] ?? 0)]
);

$paciente1 = fila($pdo, "SELECT id FROM pacientes WHERE codigo = 'PAC-DEMO-001' LIMIT 1");
$paciente2 = fila($pdo, "SELECT id FROM pacientes WHERE codigo = 'PAC-DEMO-002' LIMIT 1");
$paciente3 = fila($pdo, "SELECT id FROM pacientes WHERE codigo = 'PAC-DEMO-003' LIMIT 1");

$usuarioAdmin = fila($pdo, "SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1");
$usuarioRecepcionMedico = fila($pdo, "SELECT id FROM usuarios WHERE username = 'recepcion.medico' LIMIT 1");
$usuarioMedico = fila($pdo, "SELECT id FROM usuarios WHERE username = 'medico' LIMIT 1");
$usuarioPaciente = fila($pdo, "SELECT id FROM usuarios WHERE username = '12345678' LIMIT 1");

if (
    !is_array($medicoDb)
    || (int) ($medicoDb['especialidad_id'] ?? 0) <= 0
    || !is_array($otroMedicoDb)
    || (int) ($otroMedicoDb['especialidad_id'] ?? 0) <= 0
    || !is_array($paciente1)
    || !is_array($paciente2)
    || !is_array($paciente3)
    || !is_array($usuarioAdmin)
    || !is_array($usuarioRecepcionMedico)
    || !is_array($usuarioMedico)
    || !is_array($usuarioPaciente)
) {
    fwrite(STDERR, 'ERROR: faltan los datos seed para las pruebas del médico.' . PHP_EOL);
    exit(1);
}

$medicoId = (int) $medicoDb['id'];
$especialidadId = (int) $medicoDb['especialidad_id'];
$otroMedicoId = (int) $otroMedicoDb['id'];
$otraEspecialidadId = (int) $otroMedicoDb['especialidad_id'];
$paciente1Id = (int) $paciente1['id'];
$paciente2Id = (int) $paciente2['id'];
$paciente3Id = (int) $paciente3['id'];

$admin = [
    'id' => (int) $usuarioAdmin['id'],
    'rol' => 'admin',
    'nombre' => 'admin',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];
$recepcionistaMedico = [
    'id' => (int) $usuarioRecepcionMedico['id'],
    'rol' => 'recepcionista_medico',
    'nombre' => 'recepcion.medico',
    'medico_id' => $medicoId,
    'medico_ids' => [$medicoId, $otroMedicoId],
    'paciente_id' => null,
];
$medico = [
    'id' => (int) $usuarioMedico['id'],
    'rol' => 'medico',
    'nombre' => 'Dr. Carlos Méndez',
    'medico_id' => $medicoId,
    'medico_ids' => [$medicoId],
    'paciente_id' => null,
];
$paciente = [
    'id' => (int) $usuarioPaciente['id'],
    'rol' => 'paciente',
    'nombre' => 'Juan Pérez',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => $paciente1Id,
];

$pdo->beginTransaction();

try {
    // Datos de prueba: agenda, pacientes atendidos e historia clínica -------
    DB::clearContext();

    $citaSoloProgramada = crearCita(
        $pdo,
        $paciente2Id,
        $medicoId,
        $especialidadId,
        '2099-12-30',
        '23:00',
        '23:30',
        'programada'
    );
    $citaResumenConfirmada = crearCita(
        $pdo,
        $paciente1Id,
        $medicoId,
        $especialidadId,
        '2099-12-31',
        '08:00',
        '08:30',
        'confirmada'
    );
    $citaResumenProgramada = crearCita(
        $pdo,
        $paciente2Id,
        $medicoId,
        $especialidadId,
        '2099-12-31',
        '09:00',
        '09:30',
        'programada'
    );
    $citaResumenAtendida = crearCita(
        $pdo,
        $paciente1Id,
        $medicoId,
        $especialidadId,
        '2099-12-31',
        '10:00',
        '10:30',
        'atendida'
    );

    $citaHistoriaPropia = crearCita(
        $pdo,
        $paciente1Id,
        $medicoId,
        $especialidadId,
        '2099-12-28',
        '09:00',
        '09:30',
        'atendida'
    );
    $consultaHistoriaPropia = crearConsulta(
        $pdo,
        $citaHistoriaPropia,
        $paciente1Id,
        $medicoId,
        'Control futuro del médico',
        ['pa' => '120/80', 'fc' => 72, 'temp' => 36.5, 'peso' => 70.5, 'spo2' => 98, 'talla' => 170],
        '2099-12-28 09:00:00-04'
    );

    $citaOtroMedico = crearCita(
        $pdo,
        $paciente1Id,
        $otroMedicoId,
        $otraEspecialidadId,
        '2099-12-29',
        '10:00',
        '10:30',
        'atendida'
    );
    $consultaOtroMedico = crearConsulta(
        $pdo,
        $citaOtroMedico,
        $paciente1Id,
        $otroMedicoId,
        'Interconsulta de otra especialidad',
        ['pa' => '110/70', 'fc' => 80, 'temp' => 36.7],
        '2099-12-29 10:00:00-04'
    );

    comprobar(
        'se preparan citas y consultas de prueba',
        $citaSoloProgramada > 0
        && $citaResumenConfirmada > 0
        && $citaResumenProgramada > 0
        && $citaResumenAtendida > 0
        && $consultaHistoriaPropia > 0
        && $consultaOtroMedico > 0
    );

    // Dashboard y agenda del médico ---------------------------------------
    cambiarSesion($medico);

    $respuesta = $enviar(solicitud('GET', '/api/dashboard/medico', ['fecha' => '2099-12-31']));
    $dashboard = cuerpo($respuesta)['data'] ?? [];
    $agenda = $dashboard['agenda'] ?? [];

    comprobar(
        'el dashboard médico responde con la fecha solicitada',
        $respuesta->status() === 200
        && (int) ($dashboard['medico_id'] ?? 0) === $medicoId
        && ($dashboard['fecha'] ?? '') === '2099-12-31'
    );
    comprobar(
        'el dashboard médico calcula los contadores del día',
        (int) ($dashboard['citas_hoy'] ?? -1) === 3
        && (int) ($dashboard['en_espera'] ?? -1) === 1
        && (int) ($dashboard['atendidas'] ?? -1) === 1
        && (int) ($dashboard['proximas'] ?? -1) === 2
        && (int) ($dashboard['pendientes'] ?? -1) === 1
        && (int) ($dashboard['inasistencias'] ?? -1) === 0
    );
    comprobar(
        'el dashboard médico devuelve la siguiente cita y su agenda',
        count($agenda) === 3
        && ($agenda[0]['hora'] ?? '') === '08:00'
        && ($agenda[0]['estado'] ?? '') === 'confirmada'
        && ($dashboard['siguiente_cita']['hora'] ?? '') === '08:00'
        && ($dashboard['siguiente_cita']['paciente'] ?? '') !== ''
    );

    $respuesta = $enviar(solicitud('GET', '/api/dashboard/medico'));
    comprobar(
        'el dashboard médico usa la fecha de hoy por defecto',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['fecha'] ?? '') === date('Y-m-d')
    );

    $respuesta = $enviar(solicitud('GET', '/api/dashboard/medico', ['fecha' => 'fecha-invalida']));
    comprobar(
        'el dashboard médico valida la fecha',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['fecha'])
    );

    $respuesta = $enviar(solicitud('GET', '/api/medicos/' . $medicoId . '/agenda', ['fecha' => '2099-12-31']));
    $datosAgenda = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la agenda diaria del médico responde',
        $respuesta->status() === 200
        && count($datosAgenda['citas'] ?? []) === 3
        && (int) ($datosAgenda['contadores']['total'] ?? -1) === 3
    );

    $respuesta = $enviar(solicitud('GET', '/api/medicos/' . $medicoId . '/agenda/dia', ['fecha' => '2099-12-31']));
    comprobar(
        'el resumen diario del médico responde',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['contadores']['total'] ?? -1) === 3
    );

    $respuesta = $enviar(solicitud('GET', '/api/medicos/' . $otroMedicoId . '/agenda'));
    comprobar(
        'el médico no puede leer la agenda de otro médico',
        $respuesta->status() === 403
    );

    // Pacientes atendidos ---------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/pacientes', ['atendidos' => '1', 'per_page' => '100']));
    $pacientes = cuerpo($respuesta)['data'] ?? [];
    $idsAtendidos = array_map(static fn (array $fila): int => (int) $fila['id'], $pacientes);

    comprobar(
        'el médico filtra sus pacientes atendidos',
        $respuesta->status() === 200
        && in_array($paciente1Id, $idsAtendidos, true)
        && !in_array($paciente2Id, $idsAtendidos, true)
    );
    comprobar(
        'el listado de atendidos incluye la última atención',
        array_reduce(
            $pacientes,
            static fn (bool $ok, array $fila): bool => $ok && ($fila['ultima_atencion'] ?? null) !== null,
            true
        )
    );

    $respuesta = $enviar(solicitud('GET', '/api/pacientes', ['atendidos' => '0', 'per_page' => '100']));
    $idsSinFiltro = array_map(
        static fn (array $fila): int => (int) $fila['id'],
        cuerpo($respuesta)['data'] ?? []
    );
    comprobar(
        'atendidos=0 conserva los pacientes con citas programadas',
        in_array($paciente1Id, $idsSinFiltro, true)
        && in_array($paciente2Id, $idsSinFiltro, true)
    );

    $respuesta = $enviar(solicitud('GET', '/api/pacientes', ['atendidos' => 'abc']));
    comprobar(
        'el filtro atendidos inválido devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['atendidos'])
    );

    // Historia clínica ------------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/pacientes/' . $paciente1Id . '/historia'));
    $historia = cuerpo($respuesta)['data'] ?? [];
    $consultasMedico = $historia['consultas'] ?? [];

    comprobar(
        'el médico consulta la historia de su paciente',
        $respuesta->status() === 200
        && (int) ($historia['paciente']['id'] ?? 0) === $paciente1Id
        && (int) ($historia['total'] ?? -1) === 2
        && count($consultasMedico) === 2
    );
    comprobar(
        'la historia del médico solo incluye sus consultas',
        array_reduce(
            $consultasMedico,
            static fn (bool $ok, array $consulta): bool => $ok
                && (int) $consulta['medico_id'] === $medicoId,
            true
        )
        && !array_reduce(
            $consultasMedico,
            static fn (bool $ok, array $consulta): bool => $ok
                || (int) $consulta['medico_id'] === $otroMedicoId,
            false
        )
    );
    comprobar(
        'la historia devuelve signos vitales y datos de la cita',
        is_array($consultasMedico[0]['signos_vitales'] ?? null)
        && ($consultasMedico[0]['signos_vitales']['pa'] ?? '') === '120/80'
        && ($consultasMedico[0]['cita']['codigo'] ?? '') !== ''
        && ($consultasMedico[0]['medico']['nombre_completo'] ?? '') !== ''
        && ($consultasMedico[0]['especialidad']['nombre'] ?? '') !== ''
    );
    comprobar(
        'la historia se ordena de la más reciente a la más antigua',
        count($consultasMedico) === 2
        && strcmp((string) $consultasMedico[0]['fecha'], (string) $consultasMedico[1]['fecha']) >= 0
    );

    $respuesta = $enviar(solicitud('GET', '/api/pacientes/' . $paciente3Id . '/historia'));
    comprobar(
        'el médico no consulta la historia de un paciente ajeno',
        $respuesta->status() === 403
    );

    // Historia del paciente -------------------------------------------------
    cambiarSesion($paciente);

    $respuesta = $enviar(solicitud('GET', '/api/pacientes/' . $paciente1Id . '/historia'));
    $historia = cuerpo($respuesta)['data'] ?? [];
    $consultasPaciente = $historia['consultas'] ?? [];
    $medicosHistoria = array_map(
        static fn (array $consulta): int => (int) $consulta['medico_id'],
        $consultasPaciente
    );

    comprobar(
        'el paciente consulta su propia historia completa',
        $respuesta->status() === 200
        && (int) ($historia['total'] ?? -1) >= 3
        && in_array($medicoId, $medicosHistoria, true)
        && in_array($otroMedicoId, $medicosHistoria, true)
    );
    comprobar(
        'el paciente no puede consultar la historia de otro',
        $enviar(solicitud('GET', '/api/pacientes/' . $paciente3Id . '/historia'))->status() === 403
    );
    comprobar(
        'el paciente no accede al dashboard médico',
        $enviar(solicitud('GET', '/api/dashboard/medico'))->status() === 403
    );
    comprobar(
        'el paciente no puede listar pacientes atendidos',
        $enviar(solicitud('GET', '/api/pacientes', ['atendidos' => '1']))->status() === 403
    );

    // Alcance de recepción médica y administración -------------------------
    cambiarSesion($recepcionistaMedico);

    $respuesta = $enviar(solicitud('GET', '/api/pacientes', ['atendidos' => '1', 'per_page' => '100']));
    $idsRecepcionMedica = array_map(
        static fn (array $fila): int => (int) $fila['id'],
        cuerpo($respuesta)['data'] ?? []
    );
    comprobar(
        'recepción médica ve atendidos de todos sus médicos',
        $respuesta->status() === 200
        && in_array($paciente1Id, $idsRecepcionMedica, true)
        && in_array($paciente3Id, $idsRecepcionMedica, true)
        && !in_array($paciente2Id, $idsRecepcionMedica, true)
    );
    comprobar(
        'recepción médica no accede a la historia clínica',
        $enviar(solicitud('GET', '/api/pacientes/' . $paciente1Id . '/historia'))->status() === 403
    );

    cambiarSesion($admin);

    $respuesta = $enviar(solicitud('GET', '/api/pacientes', ['atendidos' => '1', 'per_page' => '100']));
    $idsAdmin = array_map(
        static fn (array $fila): int => (int) $fila['id'],
        cuerpo($respuesta)['data'] ?? []
    );
    comprobar(
        'admin puede filtrar pacientes atendidos',
        $respuesta->status() === 200
        && in_array($paciente1Id, $idsAdmin, true)
        && in_array($paciente3Id, $idsAdmin, true)
        && !in_array($paciente2Id, $idsAdmin, true)
    );
    comprobar(
        'admin no accede al dashboard médico',
        $enviar(solicitud('GET', '/api/dashboard/medico'))->status() === 403
    );
    comprobar(
        'admin no accede a la historia clínica de médico/paciente',
        $enviar(solicitud('GET', '/api/pacientes/' . $paciente1Id . '/historia'))->status() === 403
    );

    // El historial de citas del paciente sigue disponible ------------------
    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('GET', '/api/pacientes/' . $paciente1Id . '/historial'));
    comprobar(
        'el historial de citas existente sigue respondiendo',
        $respuesta->status() === 200
        && is_array(cuerpo($respuesta)['data']['citas'] ?? null)
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

echo PHP_EOL . 'Todas las pruebas del médico (M-30) pasaron.' . PHP_EOL;
