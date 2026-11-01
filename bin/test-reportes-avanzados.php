#!/usr/bin/env php
<?php

declare(strict_types=1);

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

/** @return array<int, string> */
function lineasCsv(Response $response): array
{
    $contenido = $response->body();

    if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
        $contenido = substr($contenido, 3);
    }

    $lineas = preg_split("/\r\n|\n|\r/", trim($contenido));

    return $lineas === false ? [] : $lineas;
}

/** @return array<int, string> */
function encabezadoCsv(Response $response): array
{
    $lineas = lineasCsv($response);

    if ($lineas === []) {
        return [];
    }

    $columnas = str_getcsv($lineas[0]);

    return is_array($columnas) ? $columnas : [];
}

function insertarCita(
    PDO $pdo,
    int $pacienteId,
    int $medicoId,
    int $especialidadId,
    string $fecha,
    string $horaInicio,
    string $horaFin,
    string $estado,
    bool $reconsulta = false,
    ?string $motivo = null
): int {
    $statement = $pdo->prepare(
        "INSERT INTO citas (
             paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
             estado, origen, es_reconsulta, reconsulta_tipo, motivo
         ) VALUES (
             :paciente, :medico, :especialidad, CAST(:fecha AS date),
             CAST(:hora_inicio AS time), CAST(:hora_fin AS time),
             CAST(:estado AS estado_cita), 'recepcion',
             CAST(:reconsulta AS boolean), CAST(:reconsulta_tipo AS tipo_reconsulta),
             :motivo
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
        'reconsulta' => $reconsulta ? 'true' : 'false',
        'reconsulta_tipo' => $reconsulta ? 'seguimiento' : null,
        'motivo' => $motivo,
    ]);

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
$medico = [
    'id' => 5,
    'rol' => 'medico',
    'nombre' => 'Dr. Carlos Mendez',
    'medico_id' => 1,
    'medico_ids' => [1],
    'paciente_id' => null,
];

$citasCtrl = new CitaApiController();
$reportesCtrl = new ReporteApiController();

$hoy = date('Y-m-d');
$fechaPrueba = date('Y-m-d', strtotime('+400 days'));

try {
    cambiarSesion($admin);
    $pdo->beginTransaction();

    // Datos de prueba: 25 citas para comprobar que el CSV no pagina.
    $estados = array_merge(
        array_fill(0, 10, 'programada'),
        array_fill(0, 5, 'confirmada'),
        array_fill(0, 3, 'atendida'),
        array_fill(0, 4, 'cancelada'),
        array_fill(0, 3, 'inasistencia')
    );

    foreach ($estados as $indice => $estado) {
        $inicioMinutos = $indice * 30;
        $horaInicio = sprintf('%02d:%02d', intdiv($inicioMinutos, 60), $inicioMinutos % 60);
        $finMinutos = $inicioMinutos + 30;
        $horaFin = sprintf('%02d:%02d', intdiv($finMinutos, 60), $finMinutos % 60);

        insertarCita(
            $pdo,
            1,
            1,
            1,
            $fechaPrueba,
            $horaInicio,
            $horaFin,
            $estado,
            $indice < 2,
            'Prueba M-34 ' . ($indice + 1)
        );
    }

    // Avisos del dashboard medico para hoy.
    insertarCita($pdo, 1, 1, 1, $hoy, '22:00', '22:15', 'programada', true, 'Reconsulta M-34');
    insertarCita($pdo, 1, 1, 1, $hoy, '22:15', '22:30', 'confirmada', true, 'Reconsulta M-34');
    insertarCita($pdo, 1, 1, 1, $hoy, '22:30', '22:45', 'inasistencia', false, 'Inasistencia M-34');
    insertarCita($pdo, 1, 1, 1, $hoy, '22:45', '23:00', 'cancelada', false, 'Cancelacion M-34');

    // Alias de orden usados por el panel administrativo.
    $respuesta = $citasCtrl->index(peticionConQuery([
        'fecha' => $hoy,
        'sort' => 'hora_inicio',
        'direction' => 'asc',
    ]));
    comprobar('el listado acepta sort=hora_inicio', $respuesta->status() === 200);

    $respuesta = $citasCtrl->index(peticionConQuery([
        'sort' => 'fecha_registro',
        'direction' => 'desc',
    ]));
    comprobar('el listado acepta sort=fecha_registro', $respuesta->status() === 200);

    comprobar('un medico inexistente devuelve validacion', codigoDeError(
        fn () => $citasCtrl->index(peticionConQuery(['medico' => 999999]))
    ) === 'VALIDATION_ERROR');
    comprobar('una especialidad inexistente devuelve validacion', codigoDeError(
        fn () => $citasCtrl->index(peticionConQuery(['especialidad' => 999999]))
    ) === 'VALIDATION_ERROR');

    // Dashboard administrativo -------------------------------------------
    $respuesta = $reportesCtrl->dashboardAdmin(peticionConQuery([]));
    $dashboard = cuerpo($respuesta)['data'] ?? [];
    $atendidasHoy = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas
         WHERE fecha = CAST(:fecha AS date) AND estado = 'atendida'",
        ['fecha' => $hoy]
    );
    $inasistenciasHoy = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas
         WHERE fecha = CAST(:fecha AS date) AND estado = 'inasistencia'",
        ['fecha' => $hoy]
    );
    $baseAsistencia = $atendidasHoy + $inasistenciasHoy;
    $tasaEsperada = $baseAsistencia > 0
        ? round(($atendidasHoy / $baseAsistencia) * 100, 2)
        : 0.0;

    comprobar('dashboard admin responde', $respuesta->status() === 200);
    comprobar('dashboard admin incluye por_estado', isset(
        $dashboard['por_estado']['programada'],
        $dashboard['por_estado']['confirmada'],
        $dashboard['por_estado']['atendida'],
        $dashboard['por_estado']['cancelada'],
        $dashboard['por_estado']['inasistencia']
    ));
    comprobar('dashboard admin calcula la tasa de asistencia', (float) ($dashboard['tasa_asistencia'] ?? -1) === (float) $tasaEsperada);

    // Exportaciones CSV ---------------------------------------------------
    $csvCitas = $reportesCtrl->csv(
        peticionConQuery(['desde' => $fechaPrueba, 'hasta' => $fechaPrueba], '/api/reportes/citas/csv'),
        ['tipo' => 'citas']
    );
    $lineas = lineasCsv($csvCitas);
    $columnas = encabezadoCsv($csvCitas);

    comprobar('CSV de citas responde', $csvCitas->status() === 200);
    comprobar('CSV de citas usa text/csv', str_contains($csvCitas->headers()['Content-Type'] ?? '', 'text/csv'));
    comprobar('CSV de citas se descarga como adjunto', str_contains($csvCitas->headers()['Content-Disposition'] ?? '', 'attachment'));
    comprobar('CSV de citas incluye BOM UTF-8', str_starts_with($csvCitas->body(), "\xEF\xBB\xBF"));
    comprobar('CSV de citas tiene las columnas esperadas', $columnas === [
        'codigo', 'fecha', 'hora_inicio', 'hora_fin', 'paciente', 'medico',
        'especialidad', 'estado', 'origen', 'motivo',
    ]);
    comprobar('CSV de citas no se limita a una pagina', count($lineas) === 26);

    $csvMedicos = $reportesCtrl->csv(
        peticionConQuery(['desde' => $fechaPrueba, 'hasta' => $fechaPrueba], '/api/reportes/citas-por-medico/csv'),
        ['tipo' => 'citas-por-medico']
    );
    comprobar('CSV por medico responde', $csvMedicos->status() === 200);
    comprobar('CSV por medico tiene las columnas esperadas', encabezadoCsv($csvMedicos) === [
        'medico_id', 'codigo', 'nombre_completo', 'total', 'programadas',
        'confirmadas', 'atendidas', 'canceladas', 'inasistencias',
    ]);
    comprobar('CSV por medico incluye filas', count(lineasCsv($csvMedicos)) > 1);

    $csvEspecialidades = $reportesCtrl->csv(
        peticionConQuery(['desde' => $fechaPrueba, 'hasta' => $fechaPrueba], '/api/reportes/citas-por-especialidad/csv'),
        ['tipo' => 'citas-por-especialidad']
    );
    comprobar('CSV por especialidad responde', $csvEspecialidades->status() === 200);
    comprobar('CSV por especialidad tiene las columnas esperadas', encabezadoCsv($csvEspecialidades) === [
        'especialidad_id', 'nombre', 'total', 'programadas', 'confirmadas',
        'atendidas', 'canceladas', 'inasistencias',
    ]);

    $csvEstados = $reportesCtrl->csv(
        peticionConQuery(['desde' => $fechaPrueba, 'hasta' => $fechaPrueba], '/api/reportes/citas-por-estado/csv'),
        ['tipo' => 'citas-por-estado']
    );
    comprobar('CSV por estado responde', $csvEstados->status() === 200);
    comprobar('CSV por estado incluye los cinco estados', count(lineasCsv($csvEstados)) === 6);
    comprobar('CSV por estado tiene las columnas esperadas', encabezadoCsv($csvEstados) === ['estado', 'total']);

    // Rango explícito: sin él el reporte usa el mes actual y el test dependía
    // de que los pacientes del seed se hubieran creado en el mes en curso.
    $csvPacientes = $reportesCtrl->csv(
        peticionConQuery(['desde' => '2020-01-01', 'hasta' => '2099-12-31'], '/api/reportes/pacientes/csv'),
        ['tipo' => 'pacientes']
    );
    comprobar('CSV de pacientes responde', $csvPacientes->status() === 200);
    comprobar('CSV de pacientes tiene las columnas esperadas', encabezadoCsv($csvPacientes) === [
        'id', 'codigo', 'ci', 'nombre_completo', 'telefono', 'email', 'estado', 'created_at',
    ]);
    comprobar('CSV de pacientes incluye filas', count(lineasCsv($csvPacientes)) > 1);

    $csvAsistencia = $reportesCtrl->csv(
        peticionConQuery(['desde' => $fechaPrueba, 'hasta' => $fechaPrueba], '/api/reportes/asistencia/csv'),
        ['tipo' => 'asistencia']
    );
    comprobar('CSV de asistencia responde', $csvAsistencia->status() === 200);
    comprobar('CSV de asistencia tiene una fila de totales', count(lineasCsv($csvAsistencia)) === 2);
    comprobar('CSV de asistencia tiene las columnas esperadas', encabezadoCsv($csvAsistencia) === [
        'total', 'programadas', 'confirmadas', 'atendidas', 'canceladas',
        'inasistencias', 'porcentaje_asistencia',
    ]);

    $csvAgenda = $reportesCtrl->csv(
        peticionConQuery(['medico' => 1, 'fecha' => $fechaPrueba], '/api/reportes/agenda-diaria/csv'),
        ['tipo' => 'agenda-diaria']
    );
    comprobar('CSV de agenda diaria responde', $csvAgenda->status() === 200);
    comprobar('CSV de agenda diaria incluye las citas del dia', count(lineasCsv($csvAgenda)) === 26);
    comprobar('CSV de agenda diaria tiene las columnas esperadas', encabezadoCsv($csvAgenda) === [
        'codigo', 'fecha', 'hora_inicio', 'hora_fin', 'medico', 'paciente', 'estado', 'motivo',
    ]);

    comprobar('un tipo de CSV inexistente devuelve NOT_FOUND', codigoDeError(
        fn () => $reportesCtrl->csv(peticionConQuery([]), ['tipo' => 'inexistente'])
    ) === 'NOT_FOUND');

    // Dashboard medico y permisos ---------------------------------------
    cambiarSesion($medico);

    $respuesta = $reportesCtrl->dashboardMedico(peticionConQuery(['fecha' => $hoy]));
    $dashboardMedico = cuerpo($respuesta)['data'] ?? [];
    $reconsultasHoy = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas
         WHERE medico_id = 1
           AND fecha = CAST(:fecha AS date)
           AND es_reconsulta = TRUE
           AND estado IN ('programada', 'confirmada')",
        ['fecha' => $hoy]
    );
    $inasistenciasMedicoHoy = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas
         WHERE medico_id = 1
           AND fecha = CAST(:fecha AS date)
           AND estado = 'inasistencia'",
        ['fecha' => $hoy]
    );
    $canceladasMedicoHoy = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas
         WHERE medico_id = 1
           AND fecha = CAST(:fecha AS date)
           AND estado = 'cancelada'",
        ['fecha' => $hoy]
    );

    comprobar('dashboard medico responde', $respuesta->status() === 200);
    comprobar('dashboard medico incluye notificaciones', isset(
        $dashboardMedico['notificaciones']['reconsultas'],
        $dashboardMedico['notificaciones']['inasistencias'],
        $dashboardMedico['notificaciones']['canceladas']
    ));
    comprobar('dashboard medico cuenta reconsultas', (int) ($dashboardMedico['notificaciones']['reconsultas'] ?? -1) === $reconsultasHoy);
    comprobar('dashboard medico cuenta inasistencias', (int) ($dashboardMedico['notificaciones']['inasistencias'] ?? -1) === $inasistenciasMedicoHoy);
    comprobar('dashboard medico cuenta cancelaciones', (int) ($dashboardMedico['notificaciones']['canceladas'] ?? -1) === $canceladasMedicoHoy);
    comprobar('dashboard medico expone reconsultas en el nivel superior', (int) ($dashboardMedico['reconsultas'] ?? -1) === $reconsultasHoy);

    comprobar('el medico no puede exportar reportes', codigoDeError(
        fn () => $reportesCtrl->csv(peticionConQuery([]), ['tipo' => 'citas'])
    ) === 'FORBIDDEN');

    // Rutas -------------------------------------------------------------
    $router = new Router();

    foreach (glob(base_path('app/routes/*.php')) ?: [] as $archivoRutas) {
        require $archivoRutas;
    }

    cambiarSesion($admin);
    comprobar('ruta GET /api/dashboard/admin responde', $router->dispatch(
        Request::create('GET', '/api/dashboard/admin')
    )->status() === 200);
    comprobar('ruta GET /api/reportes/citas/csv responde', $router->dispatch(
        peticionConQuery(['desde' => $fechaPrueba, 'hasta' => $fechaPrueba], '/api/reportes/citas/csv')
    )->status() === 200);
    comprobar('ruta GET /api/reportes/{tipo}/csv valida el tipo', codigoDeError(
        fn () => $router->dispatch(
            Request::create('GET', '/api/reportes/inexistente/csv')
        )
    ) === 'NOT_FOUND');
    comprobar('ruta GET /api/citas acepta el alias hora_inicio', $router->dispatch(
        peticionConQuery(['sort' => 'hora_inicio'], '/api/citas')
    )->status() === 200);

    cambiarSesion($medico);
    comprobar('ruta GET /api/dashboard/medico responde', $router->dispatch(
        Request::create('GET', '/api/dashboard/medico')
    )->status() === 200);
    comprobar('ruta GET /api/reportes/citas/csv bloquea al medico', $router->dispatch(
        Request::create('GET', '/api/reportes/citas/csv')
    )->status() === 403);
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

echo PHP_EOL . 'Todas las pruebas de M-34 pasaron.' . PHP_EOL;
