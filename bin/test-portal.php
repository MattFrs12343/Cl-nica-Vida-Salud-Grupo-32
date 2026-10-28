#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-33 - APIs del portal del paciente.
 *
 * Cubre perfil, mis citas, historial clínico, detalle de cita propia,
 * cancelación con la regla del día anterior y cambio de contraseña.
 */

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\AuthService;

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

/** @return array<string, mixed>|null */
function fila(PDO $pdo, string $sql, array $parametros = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parametros);
    $resultado = $statement->fetch();

    return is_array($resultado) ? $resultado : null;
}

function insertarCita(
    PDO $pdo,
    string $codigo,
    int $pacienteId,
    int $medicoId,
    int $especialidadId,
    string $fecha,
    string $horaInicio,
    string $horaFin,
    string $estado = 'programada',
    ?string $motivoCancelacion = null
): int {
    $statement = $pdo->prepare(
        "INSERT INTO citas (
             codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
             estado, origen, motivo, motivo_cancelacion
         ) VALUES (
             :codigo, :paciente, :medico, :especialidad, CAST(:fecha AS date),
             CAST(:hora_inicio AS time), CAST(:hora_fin AS time),
             CAST(:estado AS estado_cita), CAST('web' AS origen_cita),
             'Cita de prueba del portal', :motivo_cancelacion
         ) RETURNING id"
    );
    $statement->execute([
        'codigo' => $codigo,
        'paciente' => $pacienteId,
        'medico' => $medicoId,
        'especialidad' => $especialidadId,
        'fecha' => $fecha,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin,
        'estado' => $estado,
        'motivo_cancelacion' => $motivoCancelacion,
    ]);

    return (int) $statement->fetchColumn();
}

function insertarConsulta(
    PDO $pdo,
    int $citaId,
    int $pacienteId,
    int $medicoId,
    string $fecha
): int {
    $statement = $pdo->prepare(
        "INSERT INTO consultas (
             cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales,
             examen_fisico, diagnostico, tratamiento, indicaciones, fecha
         ) VALUES (
             :cita, :paciente, :medico, 'Control de prueba', 'Evolución favorable',
             CAST('{\"pa\":\"120/80\",\"fc\":72}' AS jsonb),
             'Sin signos de alarma', 'Diagnóstico de prueba', 'Continuar control',
             'Control en 30 días', CAST(:fecha AS timestamptz)
         ) RETURNING id"
    );
    $statement->execute([
        'cita' => $citaId,
        'paciente' => $pacienteId,
        'medico' => $medicoId,
        'fecha' => $fecha,
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

$pacienteDb = fila(
    $pdo,
    "SELECT p.id, p.usuario_id, p.ci, p.nombres, p.apellidos, u.id AS usuario_id
     FROM pacientes p
     JOIN usuarios u ON u.id = p.usuario_id
     WHERE p.codigo = 'PAC-DEMO-001'
     LIMIT 1"
);
$otroPacienteDb = fila($pdo, "SELECT id FROM pacientes WHERE codigo = 'PAC-DEMO-002' LIMIT 1");
$medicoDb = fila(
    $pdo,
    "SELECT m.id AS medico_id, e.id AS especialidad_id
     FROM medicos m
     JOIN medico_especialidad me ON me.medico_id = m.id
     JOIN especialidades e ON e.id = me.especialidad_id
     WHERE m.codigo = 'MED-DEMO-001' AND e.nombre = 'Medicina General'
     LIMIT 1"
);
$adminDb = fila($pdo, "SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1");
$medicoUsuarioDb = fila($pdo, "SELECT id FROM usuarios WHERE username = 'medico' LIMIT 1");

if (!is_array($pacienteDb) || !is_array($otroPacienteDb) || !is_array($medicoDb)
    || !is_array($adminDb) || !is_array($medicoUsuarioDb)) {
    fwrite(STDERR, 'ERROR: faltan los datos seed para las pruebas del portal.' . PHP_EOL);
    exit(1);
}

$pacienteId = (int) $pacienteDb['id'];
$otroPacienteId = (int) $otroPacienteDb['id'];
$medicoId = (int) $medicoDb['medico_id'];
$especialidadId = (int) $medicoDb['especialidad_id'];

$pacienteSesion = [
    'id' => (int) $pacienteDb['usuario_id'],
    'rol' => 'paciente',
    'nombre' => trim((string) $pacienteDb['nombres'] . ' ' . (string) $pacienteDb['apellidos']),
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => $pacienteId,
];
$adminSesion = [
    'id' => (int) $adminDb['id'],
    'rol' => 'admin',
    'nombre' => 'admin',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];
$medicoSesion = [
    'id' => (int) $medicoUsuarioDb['id'],
    'rol' => 'medico',
    'nombre' => 'Dr. Carlos Méndez',
    'medico_id' => $medicoId,
    'medico_ids' => [$medicoId],
    'paciente_id' => null,
];

$hoy = new DateTimeImmutable('today');
$fechaFutura = $hoy->modify('+90 days')->format('Y-m-d');
$fechaPasada = $hoy->modify('-90 days')->format('Y-m-d');
$fechaHoy = $hoy->format('Y-m-d');

$pdo->beginTransaction();

try {
    $citaFuturaId = insertarCita(
        $pdo,
        'CM-PORTAL-FUTURA',
        $pacienteId,
        $medicoId,
        $especialidadId,
        $fechaFutura,
        '03:00',
        '03:30'
    );
    $citaCancelableId = insertarCita(
        $pdo,
        'CM-PORTAL-CANCELABLE',
        $pacienteId,
        $medicoId,
        $especialidadId,
        $fechaFutura,
        '03:30',
        '04:00'
    );
    $citaTardiaId = insertarCita(
        $pdo,
        'CM-PORTAL-HOY',
        $pacienteId,
        $medicoId,
        $especialidadId,
        $fechaHoy,
        '04:00',
        '04:30'
    );
    $citaPasadaId = insertarCita(
        $pdo,
        'CM-PORTAL-PASADA',
        $pacienteId,
        $medicoId,
        $especialidadId,
        $fechaPasada,
        '03:00',
        '03:30',
        'atendida'
    );
    $citaCanceladaId = insertarCita(
        $pdo,
        'CM-PORTAL-CANCELADA',
        $pacienteId,
        $medicoId,
        $especialidadId,
        $fechaFutura,
        '04:30',
        '05:00',
        'cancelada',
        'Cancelada en prueba'
    );
    $citaAjenaId = insertarCita(
        $pdo,
        'CM-PORTAL-AJENA',
        $otroPacienteId,
        $medicoId,
        $especialidadId,
        $fechaFutura,
        '05:00',
        '05:30'
    );
    $consultaPropiaId = insertarConsulta(
        $pdo,
        $citaPasadaId,
        $pacienteId,
        $medicoId,
        $fechaPasada . ' 03:30:00'
    );

    $citaAjenaPasadaId = insertarCita(
        $pdo,
        'CM-PORTAL-AJ-PAS',
        $otroPacienteId,
        $medicoId,
        $especialidadId,
        $fechaPasada,
        '06:00',
        '06:30',
        'atendida'
    );
    $consultaAjenaId = insertarConsulta(
        $pdo,
        $citaAjenaPasadaId,
        $otroPacienteId,
        $medicoId,
        $fechaPasada . ' 06:30:00'
    );

    cambiarSesion($pacienteSesion);

    // Perfil ----------------------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/me/perfil'));
    $perfil = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'GET /api/me/perfil devuelve el perfil propio sin hash de contraseña',
        $respuesta->status() === 200
        && (int) ($perfil['id'] ?? 0) === $pacienteId
        && !array_key_exists('password_hash', $perfil)
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/perfil', [], [
        'telefono' => '70029999',
        'alergias' => 'Ninguna',
        'nombres' => 'Nombre intruso',
        'ci' => '00000000',
    ]));
    $perfil = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'PUT /api/me/perfil actualiza contacto y datos médicos sin cambiar identidad',
        $respuesta->status() === 200
        && ($perfil['telefono'] ?? '') === '70029999'
        && ($perfil['alergias'] ?? '') === 'Ninguna'
        && ($perfil['nombres'] ?? '') === (string) $pacienteDb['nombres']
        && ($perfil['ci'] ?? '') === (string) $pacienteDb['ci']
    );

    // Mis citas -------------------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/me/citas', ['per_page' => '100']));
    $datos = cuerpo($respuesta);
    $citas = $datos['data'] ?? [];
    $idsCitas = array_map(static fn (array $cita): int => (int) $cita['id'], $citas);
    $pacientesDeCitas = array_unique(array_map(
        static fn (array $cita): int => (int) $cita['paciente_id'],
        $citas
    ));
    comprobar(
        'GET /api/me/citas lista solo las citas del paciente autenticado',
        $respuesta->status() === 200
        && (int) ($datos['meta']['total'] ?? 0) >= 5
        && in_array($citaFuturaId, $idsCitas, true)
        && in_array($citaPasadaId, $idsCitas, true)
        && !in_array($citaAjenaId, $idsCitas, true)
        && $pacientesDeCitas === [$pacienteId]
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', [
        'estado' => 'cancelada',
        'per_page' => '100',
    ]));
    $citas = cuerpo($respuesta)['data'] ?? [];
    $idsCitas = array_map(static fn (array $cita): int => (int) $cita['id'], $citas);
    comprobar(
        'GET /api/me/citas filtra por estado',
        $respuesta->status() === 200
        && in_array($citaCanceladaId, $idsCitas, true)
        && array_reduce(
            $citas,
            static fn (bool $ok, array $cita): bool => $ok && ($cita['estado'] ?? '') === 'cancelada',
            true
        )
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', [
        'proximas' => '1',
        'per_page' => '100',
    ]));
    $citas = cuerpo($respuesta)['data'] ?? [];
    $idsCitas = array_map(static fn (array $cita): int => (int) $cita['id'], $citas);
    $fechas = array_map(static fn (array $cita): string => (string) $cita['fecha'], $citas);
    $fechasOrdenadas = $fechas;
    sort($fechasOrdenadas);
    comprobar(
        'GET /api/me/citas?proximas=1 excluye el pasado y ordena ascendente',
        $respuesta->status() === 200
        && in_array($citaFuturaId, $idsCitas, true)
        && !in_array($citaPasadaId, $idsCitas, true)
        && array_reduce(
            $fechas,
            static fn (bool $ok, string $fecha): bool => $ok && $fecha >= $fechaHoy,
            true
        )
        && $fechas === $fechasOrdenadas
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', [
        'desde' => $fechaFutura,
        'hasta' => $fechaFutura,
        'per_page' => '100',
    ]));
    $citas = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'GET /api/me/citas respeta el rango de fechas',
        $respuesta->status() === 200
        && $citas !== []
        && array_reduce(
            $citas,
            static fn (bool $ok, array $cita): bool => $ok && (string) $cita['fecha'] === $fechaFutura,
            true
        )
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', ['estado' => 'inventado']));
    comprobar(
        'GET /api/me/citas valida el estado',
        $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'VALIDATION_ERROR'
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/citas', [
        'desde' => '2026-12-31',
        'hasta' => '2026-01-01',
    ]));
    comprobar(
        'GET /api/me/citas valida el rango de fechas',
        $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'VALIDATION_ERROR'
    );

    // Historial -------------------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/me/historial'));
    $datos = cuerpo($respuesta);
    $historial = $datos['data'] ?? [];
    $citasHistorial = $historial['citas'] ?? [];
    $consultasHistorial = $historial['consultas'] ?? [];
    $idsCitas = array_map(static fn (array $cita): int => (int) $cita['id'], $citasHistorial);
    $idsConsultas = array_map(
        static fn (array $consulta): int => (int) $consulta['id'],
        $consultasHistorial
    );
    comprobar(
        'GET /api/me/historial devuelve citas y consultas propias',
        $respuesta->status() === 200
        && (int) ($historial['paciente']['id'] ?? 0) === $pacienteId
        && in_array($citaPasadaId, $idsCitas, true)
        && in_array($citaCanceladaId, $idsCitas, true)
        && in_array($consultaPropiaId, $idsConsultas, true)
        && !in_array($citaAjenaId, $idsCitas, true)
        && !in_array($consultaAjenaId, $idsConsultas, true)
        && (int) ($historial['total_citas'] ?? 0) === count($citasHistorial)
        && (int) ($historial['total_consultas'] ?? 0) === count($consultasHistorial)
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/historial', [
        'desde' => $fechaPasada,
        'hasta' => $fechaPasada,
    ]));
    $historial = cuerpo($respuesta)['data'] ?? [];
    $idsCitas = array_map(
        static fn (array $cita): int => (int) $cita['id'],
        $historial['citas'] ?? []
    );
    $idsConsultas = array_map(
        static fn (array $consulta): int => (int) $consulta['id'],
        $historial['consultas'] ?? []
    );
    comprobar(
        'GET /api/me/historial filtra citas y consultas por fecha',
        $respuesta->status() === 200
        && in_array($citaPasadaId, $idsCitas, true)
        && !in_array($citaFuturaId, $idsCitas, true)
        && in_array($consultaPropiaId, $idsConsultas, true)
    );

    $respuesta = $enviar(solicitud('GET', '/api/me/historial', ['estado' => 'cancelada']));
    $historial = cuerpo($respuesta)['data'] ?? [];
    $citasHistorial = $historial['citas'] ?? [];
    comprobar(
        'GET /api/me/historial filtra las citas por estado',
        $respuesta->status() === 200
        && $citasHistorial !== []
        && array_reduce(
            $citasHistorial,
            static fn (bool $ok, array $cita): bool => $ok && ($cita['estado'] ?? '') === 'cancelada',
            true
        )
    );

    // Detalle de cita -------------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/citas/' . $citaFuturaId));
    comprobar(
        'el paciente puede ver el detalle de su propia cita',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['id'] ?? 0) === $citaFuturaId
    );

    $respuesta = $enviar(solicitud('GET', '/api/citas/' . $citaAjenaId));
    comprobar(
        'el paciente no puede ver el detalle de una cita ajena',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN'
    );

    // Cancelación -----------------------------------------------------------
    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaCancelableId . '/cancelar', [], [
        'motivo' => 'No puedo asistir',
    ]));
    $citaCancelada = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el paciente cancela su propia cita futura',
        $respuesta->status() === 200
        && ($citaCancelada['estado'] ?? '') === 'cancelada'
        && ($citaCancelada['motivo_cancelacion'] ?? '') === 'No puedo asistir'
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaTardiaId . '/cancelar', [], [
        'motivo' => 'Aviso tardío',
    ]));
    comprobar(
        'la cancelación del mismo día se rechaza',
        $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CANCELLATION_TOO_LATE'
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaAjenaId . '/cancelar', [], [
        'motivo' => 'Intento ajeno',
    ]));
    comprobar(
        'el paciente no puede cancelar una cita ajena',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN'
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaFuturaId . '/cancelar'));
    comprobar(
        'la cancelación exige motivo',
        $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'VALIDATION_ERROR'
    );

    // Cambio de contraseña --------------------------------------------------
    $respuesta = $enviar(solicitud('PUT', '/api/me/password', [], [
        'password_actual' => 'Demo1234!',
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]));
    $hash = (string) $pdo->query(
        'SELECT password_hash FROM usuarios WHERE id = ' . (int) $pacienteSesion['id']
    )->fetchColumn();
    comprobar(
        'PUT /api/me/password cambia la contraseña con la credencial actual',
        $respuesta->status() === 200
        && password_verify('NuevaClave1', $hash)
        && !password_verify('Demo1234!', $hash)
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/password', [], [
        'password_actual' => 'Incorrecta1',
        'password' => 'OtraClave2',
        'password_confirmation' => 'OtraClave2',
    ]));
    comprobar(
        'el cambio de contraseña rechaza la credencial actual incorrecta',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['password_actual'])
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/password', [], [
        'password_actual' => 'NuevaClave1',
        'password' => 'corta',
        'password_confirmation' => 'corta',
    ]));
    comprobar(
        'el cambio de contraseña valida la complejidad',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['password'])
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/password', [], [
        'password_actual' => 'NuevaClave1',
        'password' => 'OtraClave2',
        'password_confirmation' => 'OtraClave3',
    ]));
    comprobar(
        'el cambio de contraseña exige confirmación coincidente',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['password_confirmation'])
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/password', [], [
        'password_actual' => 'NuevaClave1',
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]));
    comprobar(
        'el cambio de contraseña rechaza reutilizar la actual',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['password'])
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/password', [], [
        'password_actual' => 'NuevaClave1',
        'password' => 'OtraClave2',
        'password_confirmation' => 'OtraClave2',
    ], false));
    comprobar(
        'el cambio de contraseña exige CSRF',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH'
    );

    $auth = new AuthService();
    comprobar(
        'la nueva contraseña permite iniciar sesión',
        $auth->login('12345678', 'NuevaClave1')['rol'] === 'paciente'
    );
    comprobar(
        'la contraseña anterior deja de funcionar',
        codigoDeError(fn () => $auth->login('12345678', 'Demo1234!')) === 'INVALID_CREDENTIALS'
    );

    // Restricciones de rol --------------------------------------------------
    cambiarSesion($adminSesion);
    comprobar(
        'un admin no accede a mis citas del portal',
        $enviar(solicitud('GET', '/api/me/citas'))->status() === 403
    );
    comprobar(
        'un admin no accede al historial del portal',
        $enviar(solicitud('GET', '/api/me/historial'))->status() === 403
    );
    comprobar(
        'un admin no cambia la contraseña del portal',
        $enviar(solicitud('PUT', '/api/me/password', [], [
            'password_actual' => 'Demo1234!',
            'password' => 'OtraClave2',
            'password_confirmation' => 'OtraClave2',
        ]))->status() === 403
    );

    cambiarSesion($medicoSesion);
    comprobar(
        'un médico no accede a mis citas del portal',
        $enviar(solicitud('GET', '/api/me/citas'))->status() === 403
    );

    $_SESSION = [];
    DB::clearContext();
    comprobar(
        'sin sesión el portal responde 401',
        $enviar(solicitud('GET', '/api/me/citas'))->status() === 401
    );

    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $_SESSION = [];
    DB::clearContext();
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
