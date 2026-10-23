#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Controllers\Api\AgendaApiController;
use App\Controllers\Api\AuditoriaApiController;
use App\Controllers\Api\CitaApiController;
use App\Controllers\Api\ReporteApiController;
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

/** @return array<string, mixed> */
function cuerpo(Response $response): array
{
    $datos = json_decode($response->body(), true);

    return is_array($datos) ? $datos : [];
}

/** @param array<string, mixed> $query */
function peticionConQuery(array $query, string $ruta = '/api/citas'): Request
{
    $getAnterior = $_GET;
    $servidorAnterior = $_SERVER;

    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $ruta . '?' . http_build_query($query);

    try {
        return Request::capture();
    } finally {
        $_GET = $getAnterior;
        $_SERVER = $servidorAnterior;
    }
}

/** @param array<string, mixed> $usuario */
function cambiarSesion(array $usuario): void
{
    $_SESSION['usuario'] = $usuario;
    DB::setContext($usuario, '127.0.0.1');
}

/** @param array<string, mixed> $parametros */
function contarSql(PDO $pdo, string $sql, array $parametros = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parametros);

    return (int) $statement->fetchColumn();
}

$pdo = DB::connection();
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
    'nombre' => 'Dr. Carlos Méndez',
    'medico_id' => 1,
    'medico_ids' => [1],
    'paciente_id' => null,
];
$paciente = [
    'id' => 6,
    'rol' => 'paciente',
    'nombre' => 'Juan Pérez',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => 1,
];

$citasCtrl = new CitaApiController();
$agendaCtrl = new AgendaApiController();
$reportesCtrl = new ReporteApiController();
$auditoriaCtrl = new AuditoriaApiController();

$hoy = date('Y-m-d');
$desdeMes = date('Y-m-01');
$hastaMes = date('Y-m-t');

try {
    cambiarSesion($admin);

    // Citas: listado, filtros y detalle -------------------------------------
    $respuesta = $citasCtrl->index(peticionConQuery([], '/api/citas'));
    $cuerpo = cuerpo($respuesta);
    $totalCitas = contarSql($pdo, 'SELECT COUNT(*) FROM citas');

    comprobar('listado de citas responde', $respuesta->status() === 200);
    comprobar('listado incluye todas las citas para admin', (int) ($cuerpo['meta']['total'] ?? -1) === $totalCitas);
    comprobar('listado incluye datos anidados de paciente y médico', isset($cuerpo['data'][0]['paciente']['nombre_completo'], $cuerpo['data'][0]['medico']['nombre_completo']));
    comprobar('las horas se devuelven como HH:MM', strlen((string) ($cuerpo['data'][0]['hora_inicio'] ?? '')) === 5);

    $respuesta = $citasCtrl->index(peticionConQuery(['fecha' => $hoy], '/api/citas'));
    $cuerpo = cuerpo($respuesta);
    $totalHoy = contarSql($pdo, 'SELECT COUNT(*) FROM citas WHERE fecha = CAST(:fecha AS date)', ['fecha' => $hoy]);

    comprobar('filtro por fecha responde', $respuesta->status() === 200);
    comprobar('filtro por fecha cuenta solo ese día', (int) ($cuerpo['meta']['total'] ?? -1) === $totalHoy);

    $respuesta = $citasCtrl->index(peticionConQuery(['medico' => 1], '/api/citas'));
    $cuerpo = cuerpo($respuesta);
    $totalMedico1 = contarSql($pdo, 'SELECT COUNT(*) FROM citas WHERE medico_id = :medico', ['medico' => 1]);

    comprobar('filtro por médico responde', $respuesta->status() === 200);
    comprobar('filtro por médico cuenta solo sus citas', (int) ($cuerpo['meta']['total'] ?? -1) === $totalMedico1);
    comprobar('filtro por médico no mezcla profesionales', array_reduce(
        $cuerpo['data'] ?? [],
        static fn (bool $ok, array $cita): bool => $ok && (int) $cita['medico_id'] === 1,
        true
    ));

    $cita = $pdo->query('SELECT id, codigo, estado FROM citas ORDER BY id LIMIT 1')->fetch();
    $respuesta = $citasCtrl->show(peticionConQuery([], '/api/citas/' . $cita['id']), ['id' => (string) $cita['id']]);
    $cuerpo = cuerpo($respuesta);

    comprobar('detalle de cita responde', $respuesta->status() === 200);
    comprobar('detalle de cita devuelve el código', ($cuerpo['data']['codigo'] ?? null) === $cita['codigo']);
    comprobar('detalle de cita devuelve especialidad', isset($cuerpo['data']['especialidad']['nombre']));

    comprobar('detalle de cita inexistente devuelve NOT_FOUND', codigoDeError(
        fn () => $citasCtrl->show(peticionConQuery([]), ['id' => '999999'])
    ) === 'NOT_FOUND');

    $respuesta = $citasCtrl->historial(peticionConQuery([]), ['id' => (string) $cita['id']]);
    comprobar('historial de estados responde para admin', $respuesta->status() === 200);
    comprobar('historial de estados devuelve lista', is_array(cuerpo($respuesta)['data'] ?? null));

    // Agenda ---------------------------------------------------------------
    $respuesta = $agendaCtrl->medico(peticionConQuery(['fecha' => $hoy], '/api/medicos/1/agenda'), ['medico' => '1']);
    $cuerpo = cuerpo($respuesta);
    $totalAgendaHoy = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM citas WHERE medico_id = :medico AND fecha = CAST(:fecha AS date)',
        ['medico' => 1, 'fecha' => $hoy]
    );

    comprobar('agenda diaria responde', $respuesta->status() === 200);
    comprobar('agenda diaria devuelve el médico', (int) ($cuerpo['data']['medico']['id'] ?? 0) === 1);
    comprobar('agenda diaria cuenta el día', (int) ($cuerpo['data']['contadores']['total'] ?? -1) === $totalAgendaHoy);

    $respuesta = $agendaCtrl->medico(
        peticionConQuery(['desde' => $hoy, 'hasta' => date('Y-m-d', strtotime($hoy . ' +7 days'))], '/api/medicos/1/agenda'),
        ['medico' => '1']
    );
    $cuerpo = cuerpo($respuesta);

    comprobar('agenda por rango responde', $respuesta->status() === 200);
    comprobar('agenda por rango devuelve citas del médico', array_reduce(
        $cuerpo['data']['citas'] ?? [],
        static fn (bool $ok, array $cita): bool => $ok && (int) $cita['medico_id'] === 1,
        true
    ));

    $respuesta = $agendaCtrl->dia(peticionConQuery(['fecha' => $hoy], '/api/medicos/1/agenda/dia'), ['medico' => '1']);
    $cuerpo = cuerpo($respuesta);
    comprobar('contadores de agenda responden', $respuesta->status() === 200);
    comprobar('contadores de agenda coinciden con SQL', (int) ($cuerpo['data']['contadores']['total'] ?? -1) === $totalAgendaHoy);

    cambiarSesion($medico);
    $respuesta = $agendaCtrl->medico(peticionConQuery(['fecha' => $hoy]), ['medico' => '1']);
    comprobar('el médico puede ver su propia agenda', $respuesta->status() === 200);
    comprobar('el médico no puede ver otra agenda', codigoDeError(
        fn () => $agendaCtrl->medico(peticionConQuery(['fecha' => $hoy]), ['medico' => '2'])
    ) === 'FORBIDDEN');

    $respuesta = $citasCtrl->index(peticionConQuery([], '/api/citas'));
    $cuerpo = cuerpo($respuesta);
    comprobar('el médico solo ve sus citas', array_reduce(
        $cuerpo['data'] ?? [],
        static fn (bool $ok, array $cita): bool => $ok && (int) $cita['medico_id'] === 1,
        true
    ));

    cambiarSesion($recepcionistaMedico);
    $respuesta = $agendaCtrl->medico(peticionConQuery(['fecha' => $hoy]), ['medico' => '2']);
    comprobar('recepción médica puede consultar su segundo médico', $respuesta->status() === 200);

    // Historial de paciente ------------------------------------------------
    cambiarSesion($admin);
    $respuesta = $agendaCtrl->historialPaciente(peticionConQuery([]), ['paciente' => '1']);
    $cuerpo = cuerpo($respuesta);
    $totalPaciente1 = contarSql($pdo, 'SELECT COUNT(*) FROM citas WHERE paciente_id = :paciente', ['paciente' => 1]);

    comprobar('historial de paciente responde para admin', $respuesta->status() === 200);
    comprobar('historial de paciente devuelve sus citas', count($cuerpo['data']['citas'] ?? []) === $totalPaciente1);

    cambiarSesion($paciente);
    $respuesta = $agendaCtrl->historialPaciente(peticionConQuery([]), ['paciente' => '1']);
    comprobar('el paciente puede ver su propio historial', $respuesta->status() === 200);
    comprobar('el paciente no puede ver otro historial', codigoDeError(
        fn () => $agendaCtrl->historialPaciente(peticionConQuery([]), ['paciente' => '2'])
    ) === 'FORBIDDEN');

    // Reportes -------------------------------------------------------------
    cambiarSesion($admin);
    $respuesta = $reportesCtrl->citasPorMedico(peticionConQuery([], '/api/reportes/citas-por-medico'));
    $cuerpo = cuerpo($respuesta);
    $totalMes = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM citas WHERE fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)',
        ['desde' => $desdeMes, 'hasta' => $hastaMes]
    );

    comprobar('reporte por médico responde', $respuesta->status() === 200);
    comprobar('reporte por médico suma el total del período', (int) ($cuerpo['meta']['total_citas'] ?? -1) === $totalMes);

    $respuesta = $reportesCtrl->citasPorEspecialidad(peticionConQuery([]));
    $cuerpo = cuerpo($respuesta);
    comprobar('reporte por especialidad responde', $respuesta->status() === 200);
    comprobar('reporte por especialidad suma el total del período', (int) ($cuerpo['meta']['total_citas'] ?? -1) === $totalMes);

    $respuesta = $reportesCtrl->citasPorEstado(peticionConQuery([]));
    $cuerpo = cuerpo($respuesta);
    comprobar('reporte por estado responde', $respuesta->status() === 200);
    comprobar('reporte por estado incluye todos los estados', count($cuerpo['data'] ?? []) === 5);
    comprobar('reporte por estado suma el total del período', (int) ($cuerpo['meta']['total_citas'] ?? -1) === $totalMes);

    $respuesta = $reportesCtrl->pacientes(peticionConQuery([]));
    $cuerpo = cuerpo($respuesta);
    $totalPacientesMes = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM pacientes
         WHERE created_at >= CAST(:desde AS date)
           AND created_at < (CAST(:hasta AS date) + INTERVAL \'1 day\')',
        ['desde' => $desdeMes, 'hasta' => $hastaMes]
    );
    comprobar('reporte de pacientes responde', $respuesta->status() === 200);
    comprobar('reporte de pacientes coincide con SQL', (int) ($cuerpo['meta']['total'] ?? -1) === $totalPacientesMes);

    $respuesta = $reportesCtrl->asistencia(peticionConQuery([]));
    $cuerpo = cuerpo($respuesta);
    $atendidasMes = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas
         WHERE fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)
           AND estado = 'atendida'",
        ['desde' => $desdeMes, 'hasta' => $hastaMes]
    );
    comprobar('reporte de asistencia responde', $respuesta->status() === 200);
    comprobar('reporte de asistencia cuenta atendidas', (int) ($cuerpo['data']['atendidas'] ?? -1) === $atendidasMes);

    $respuesta = $reportesCtrl->agendaDiaria(peticionConQuery(['medico' => 1, 'fecha' => $hoy]));
    $cuerpo = cuerpo($respuesta);
    comprobar('reporte de agenda diaria responde', $respuesta->status() === 200);
    comprobar('reporte de agenda diaria coincide con SQL', (int) ($cuerpo['data']['contadores']['total'] ?? -1) === $totalAgendaHoy);

    $respuesta = $reportesCtrl->dashboardAdmin(peticionConQuery([]));
    $cuerpo = cuerpo($respuesta);
    $citasHoy = contarSql($pdo, 'SELECT COUNT(*) FROM citas WHERE fecha = CAST(:fecha AS date)', ['fecha' => $hoy]);
    comprobar('dashboard admin responde', $respuesta->status() === 200);
    comprobar('dashboard admin cuenta las citas de hoy', (int) ($cuerpo['data']['citas_hoy'] ?? -1) === $citasHoy);
    comprobar('dashboard admin incluye orígenes', isset($cuerpo['data']['por_origen']['web'], $cuerpo['data']['por_origen']['kiosco'], $cuerpo['data']['por_origen']['recepcion']));

    $respuesta = $reportesCtrl->citas(peticionConQuery([], '/api/reportes/citas'));
    $cuerpo = cuerpo($respuesta);
    comprobar('reporte de citas responde', $respuesta->status() === 200);
    comprobar('reporte de citas usa el mes actual por defecto', (int) ($cuerpo['meta']['total'] ?? -1) === $totalMes);

    // Auditoría ------------------------------------------------------------
    $respuesta = $auditoriaCtrl->index(peticionConQuery([], '/api/auditoria'));
    $cuerpo = cuerpo($respuesta);
    $totalAuditoria = (int) ($cuerpo['meta']['total'] ?? 0);
    $primerRegistro = $cuerpo['data'][0] ?? null;

    comprobar('listado de auditoría responde', $respuesta->status() === 200);
    comprobar('listado de auditoría tiene registros', $totalAuditoria > 0);
    comprobar('listado de auditoría no incluye JSON pesado', $primerRegistro !== null && !array_key_exists('datos_antes', $primerRegistro));

    if ($primerRegistro !== null) {
        $respuesta = $auditoriaCtrl->index(peticionConQuery(['tabla' => $primerRegistro['tabla_afectada']]));
        $cuerpo = cuerpo($respuesta);
        comprobar('filtro de auditoría por tabla responde', $respuesta->status() === 200);
        comprobar('filtro de auditoría por tabla no mezcla tablas', array_reduce(
            $cuerpo['data'] ?? [],
            static fn (bool $ok, array $registro): bool => $ok && $registro['tabla_afectada'] === $primerRegistro['tabla_afectada'],
            true
        ));

        $respuesta = $auditoriaCtrl->index(peticionConQuery(['usuario' => 'admin']));
        comprobar('filtro de auditoría por usuario responde', $respuesta->status() === 200);

        $respuesta = $auditoriaCtrl->show(peticionConQuery([], '/api/auditoria/' . $primerRegistro['id']), ['id' => (string) $primerRegistro['id']]);
        $detalle = cuerpo($respuesta);
        comprobar('detalle de auditoría responde', $respuesta->status() === 200);
        comprobar('detalle de auditoría incluye cambios calculados', array_key_exists('cambios', $detalle['data'] ?? []));
    }

    // Rutas y permisos -----------------------------------------------------
    $router = new Router();

    foreach (glob(base_path('app/routes/*.php')) ?: [] as $archivoRutas) {
        require $archivoRutas;
    }

    cambiarSesion($admin);
    comprobar('ruta GET /api/citas responde para admin', $router->dispatch(Request::create('GET', '/api/citas'))->status() === 200);
    comprobar('ruta GET /api/auditoria responde para admin', $router->dispatch(Request::create('GET', '/api/auditoria'))->status() === 200);
    comprobar('ruta GET /api/reportes/citas responde para admin', $router->dispatch(Request::create('GET', '/api/reportes/citas'))->status() === 200);

    cambiarSesion($medico);
    comprobar('ruta GET /api/reportes/citas bloquea al médico', $router->dispatch(Request::create('GET', '/api/reportes/citas'))->status() === 403);
    comprobar('ruta GET /api/auditoria bloquea al médico', $router->dispatch(Request::create('GET', '/api/auditoria'))->status() === 403);
    comprobar('ruta GET /api/citas permite al médico', $router->dispatch(Request::create('GET', '/api/citas'))->status() === 200);

    cambiarSesion($paciente);
    comprobar('ruta GET /api/citas bloquea al paciente', $router->dispatch(Request::create('GET', '/api/citas'))->status() === 403);
    comprobar('ruta GET /api/pacientes/1/historial permite al paciente', $router->dispatch(Request::create('GET', '/api/pacientes/1/historial'))->status() === 200);
    comprobar('ruta GET /api/pacientes/2/historial bloquea al paciente', codigoDeError(
        fn () => $router->dispatch(Request::create('GET', '/api/pacientes/2/historial'))
    ) === 'FORBIDDEN');
} finally {
    DB::clearContext();
    $_SESSION = [];
}

if ($fallos > 0) {
    echo PHP_EOL . $fallos . ' prueba(s) fallaron.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Todas las pruebas de M-21 pasaron.' . PHP_EOL;
