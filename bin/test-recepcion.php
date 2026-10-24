#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\DisponibilidadService;

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
 * Construye una petición como si viniera del navegador. Permite decidir si
 * incluye el token CSRF para probar las mutaciones.
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
 * Busca el primer horario libre de recepción en los próximos 60 días.
 *
 * @return array{fecha: string, hora: string}|null
 */
function buscarSlot(DisponibilidadService $disponibilidad, int $medicoId): ?array
{
    for ($dia = 1; $dia <= 60; $dia++) {
        $fecha = (new DateTimeImmutable('today'))->modify('+' . $dia . ' days')->format('Y-m-d');

        foreach ($disponibilidad->slots($medicoId, $fecha, 'recepcion') as $slot) {
            if (($slot['disponible'] ?? false) === true) {
                return ['fecha' => $fecha, 'hora' => (string) $slot['hora']];
            }
        }
    }

    return null;
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

$medicoDb = $pdo->query(
    "SELECT m.id AS medico_id, e.id AS especialidad_id
     FROM medicos m
     JOIN medico_especialidad me ON me.medico_id = m.id
     JOIN especialidades e ON e.id = me.especialidad_id
     WHERE m.codigo = 'MED-DEMO-001' AND e.nombre = 'Medicina General'
     LIMIT 1"
)->fetch();

if (!is_array($medicoDb)) {
    fwrite(STDERR, 'ERROR: faltan los datos seed del médico.' . PHP_EOL);
    exit(1);
}

$medicoId = (int) $medicoDb['medico_id'];
$especialidadId = (int) $medicoDb['especialidad_id'];
$disponibilidad = new DisponibilidadService();
$ci = '99' . (string) random_int(10000000, 99999999);

$pdo->beginTransaction();

try {
    // Rutas y permisos -----------------------------------------------------
    cambiarSesion($admin);
    comprobar(
        'GET /api/pacientes responde para admin',
        $enviar(solicitud('GET', '/api/pacientes'))->status() === 200
    );
    comprobar(
        'GET /api/pacientes/buscar está registrada',
        $enviar(solicitud('GET', '/api/pacientes/buscar', ['q' => 'x']))->status() === 200
    );

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/pacientes'));
    comprobar(
        'el paciente no puede listar pacientes',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN'
    );

    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('POST', '/api/pacientes', [], [
        'ci' => $ci,
        'nombres' => 'Paciente',
        'apellidos' => 'Recepcion Prueba',
        'fecha_nacimiento' => '1995-03-15',
        'sexo' => 'femenino',
    ], false));
    comprobar(
        'crear paciente sin CSRF se rechaza',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH'
    );

    // Alta de paciente -----------------------------------------------------
    $respuesta = $enviar(solicitud('POST', '/api/pacientes', [], [
        'ci' => $ci,
        'nombres' => 'Paciente',
        'apellidos' => 'Recepcion Prueba',
        'fecha_nacimiento' => '1995-03-15',
        'sexo' => 'femenino',
        'telefono' => '77712345',
        'email' => 'paciente.m27@correo.com',
        'direccion' => 'Av. Prueba 123',
        'tipo_sangre' => 'O+',
        'alergias' => 'Ninguna',
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    $pacienteId = (int) ($datos['id'] ?? 0);

    comprobar(
        'se registra un paciente desde recepción',
        $respuesta->status() === 201
        && $pacienteId > 0
        && ($datos['codigo'] ?? '') !== ''
        && ($datos['nombre_completo'] ?? '') === 'Paciente Recepcion Prueba'
        && ($datos['estado'] ?? '') === 'activo'
    );

    $respuesta = $enviar(solicitud('POST', '/api/pacientes', [], [
        'ci' => $ci,
        'nombres' => 'Duplicado',
        'apellidos' => 'Recepcion Prueba',
        'fecha_nacimiento' => '1995-03-15',
        'sexo' => 'femenino',
    ]));
    comprobar(
        'la CI duplicada se rechaza con DUPLICATE_CI',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'DUPLICATE_CI'
    );

    $respuesta = $enviar(solicitud('POST', '/api/pacientes', [], [
        'ci' => 'abc',
        'nombres' => 'Paciente',
        'apellidos' => 'Recepcion Prueba',
        'fecha_nacimiento' => '1995-03-15',
        'sexo' => 'femenino',
    ]));
    comprobar(
        'la CI inválida devuelve VALIDATION_ERROR',
        $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['ci'])
    );

    // Búsqueda y edición ---------------------------------------------------
    $respuesta = $enviar(solicitud('GET', '/api/pacientes/buscar', ['q' => 'Recepcion Prueba']));
    $encontrados = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la búsqueda encuentra al paciente por apellido',
        $respuesta->status() === 200
        && array_reduce(
            $encontrados,
            static fn (bool $ok, array $fila): bool => $ok || (int) $fila['id'] === $pacienteId,
            false
        )
    );

    $respuesta = $enviar(solicitud('PUT', '/api/pacientes/' . $pacienteId, [], [
        'telefono' => '77799999',
        'email' => 'actualizado.m27@correo.com',
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la edición actualiza solo los campos enviados',
        $respuesta->status() === 200
        && ($datos['telefono'] ?? '') === '77799999'
        && ($datos['email'] ?? '') === 'actualizado.m27@correo.com'
        && ($datos['nombres'] ?? '') === 'Paciente'
    );

    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('GET', '/api/pacientes/' . $pacienteId));
    comprobar(
        'recepción médica no ve pacientes sin citas con sus médicos',
        $respuesta->status() === 403
    );

    // Estado del paciente --------------------------------------------------
    cambiarSesion($recepcionista);
    $respuesta = $enviar(solicitud('PATCH', '/api/pacientes/' . $pacienteId . '/estado', [], [
        'estado' => 'inactivo',
    ]));
    comprobar(
        'solo admin puede desactivar pacientes',
        $respuesta->status() === 403
    );

    cambiarSesion($admin);
    $respuesta = $enviar(solicitud('PATCH', '/api/pacientes/' . $pacienteId . '/estado', [], [
        'estado' => 'inactivo',
    ]));
    comprobar(
        'admin desactiva al paciente',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'inactivo'
    );

    $slot = buscarSlot($disponibilidad, $medicoId);

    if ($slot === null) {
        fwrite(STDERR, 'ERROR: no hay horarios libres para las pruebas.' . PHP_EOL);
        exit(1);
    }

    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot['fecha'],
        'hora' => $slot['hora'],
    ]));
    comprobar(
        'un paciente inactivo no puede agendar',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'PATIENT_INACTIVE'
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/pacientes/' . $pacienteId . '/estado', [], [
        'estado' => 'activo',
    ]));
    comprobar(
        'admin reactiva al paciente',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'activo'
    );

    // Creación de citas ----------------------------------------------------
    $slot1 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot1['fecha'],
        'hora' => $slot1['hora'],
        'motivo' => 'Consulta de recepción',
    ]));
    $cita1 = cuerpo($respuesta)['data'] ?? [];
    $cita1Id = (int) ($cita1['id'] ?? 0);

    comprobar(
        'recepción crea una cita programada',
        $respuesta->status() === 201
        && $cita1Id > 0
        && ($cita1['estado'] ?? '') === 'programada'
        && ($cita1['origen'] ?? '') === 'recepcion'
        && str_starts_with((string) ($cita1['codigo'] ?? ''), 'CM-')
        && (int) ($cita1['paciente_id'] ?? 0) === $pacienteId
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot1['fecha'],
        'hora' => $slot1['hora'],
    ]));
    comprobar(
        'un horario ocupado devuelve SLOT_TAKEN',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'SLOT_TAKEN'
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot1['fecha'],
        'hora' => '03:00',
    ]));
    comprobar(
        'un horario fuera de la agenda devuelve SCHEDULE_OUTSIDE',
        $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'SCHEDULE_OUTSIDE'
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => (new DateTimeImmutable('yesterday'))->format('Y-m-d'),
        'hora' => '09:00',
    ]));
    comprobar(
        'una fecha pasada no se puede agendar',
        $respuesta->status() === 422
    );

    // Reprogramación -------------------------------------------------------
    $slot2 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot2['fecha'],
        'hora' => $slot2['hora'],
    ]));
    $cita2Id = (int) (cuerpo($respuesta)['data']['id'] ?? 0);

    $respuesta = $enviar(solicitud('PUT', '/api/citas/' . $cita2Id . '/reprogramar', [], [
        'fecha' => $slot1['fecha'],
        'hora' => $slot1['hora'],
    ]));
    comprobar(
        'no se puede reprogramar sobre un horario ocupado',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'SLOT_TAKEN'
    );

    $slot3 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('PUT', '/api/citas/' . $cita2Id . '/reprogramar', [], [
        'fecha' => $slot3['fecha'],
        'hora' => $slot3['hora'],
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'recepción reprograma la cita',
        $respuesta->status() === 200
        && ($datos['fecha'] ?? '') === $slot3['fecha']
        && ($datos['hora_inicio'] ?? '') === $slot3['hora']
    );

    // Cancelación ----------------------------------------------------------
    $slot4 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot4['fecha'],
        'hora' => $slot4['hora'],
    ]));
    $citaCancelarId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaCancelarId . '/cancelar', [], [
        'motivo' => 'Paciente no puede asistir',
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'recepción cancela una cita futura con motivo',
        $respuesta->status() === 200
        && ($datos['estado'] ?? '') === 'cancelada'
        && ($datos['motivo_cancelacion'] ?? '') === 'Paciente no puede asistir'
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaCancelarId . '/cancelar', [], [
        'motivo' => 'Repetir cancelación',
    ]));
    comprobar(
        'una cita cancelada no se vuelve a cancelar',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_STATE'
    );

    $statement = $pdo->prepare(
        "INSERT INTO citas (
             paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
             estado, origen
         ) VALUES (
             :paciente, :medico, :especialidad, CURRENT_DATE, '00:01', '00:31',
             'programada', 'recepcion'
         )
         RETURNING id"
    );
    $statement->execute([
        'paciente' => $pacienteId,
        'medico' => $medicoId,
        'especialidad' => $especialidadId,
    ]);
    $citaHoyId = (int) $statement->fetchColumn();

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaHoyId . '/cancelar', [], [
        'motivo' => 'Intento el mismo día',
    ]));
    comprobar(
        'no se puede cancelar el mismo día',
        $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CANCELLATION_TOO_LATE'
    );

    // El paciente solo cancela sus propias citas ---------------------------
    $slot5 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => 1,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot5['fecha'],
        'hora' => $slot5['hora'],
    ]));
    $citaPaciente1Id = (int) (cuerpo($respuesta)['data']['id'] ?? 0);

    $slot6 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot6['fecha'],
        'hora' => $slot6['hora'],
    ]));
    $citaOtroPacienteId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaOtroPacienteId . '/cancelar', [], [
        'motivo' => 'No es mi cita',
    ]));
    comprobar(
        'el paciente no puede cancelar la cita de otro',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaPaciente1Id . '/cancelar', [], [
        'motivo' => 'Cancelación del paciente',
    ]));
    comprobar(
        'el paciente cancela su propia cita',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'cancelada'
    );

    // Asistencia -----------------------------------------------------------
    cambiarSesion($admin);
    $slot7 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot7['fecha'],
        'hora' => $slot7['hora'],
    ]));
    $citaAtenderId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaAtenderId . '/atender'));
    comprobar(
        'no se atiende una cita programada',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_TRANSITION'
    );

    $pdo->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = :id")
        ->execute(['id' => $citaAtenderId]);

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaAtenderId . '/atender'));
    comprobar(
        'el médico marca atendida una cita confirmada',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'atendida'
    );

    $slot8 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot8['fecha'],
        'hora' => $slot8['hora'],
    ]));
    $citaInasistenciaId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaInasistenciaId . '/inasistencia'));
    comprobar(
        'se marca inasistencia desde programada',
        $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['estado'] ?? '') === 'inasistencia'
    );

    $respuesta = $enviar(solicitud('PATCH', '/api/citas/' . $citaInasistenciaId . '/atender'));
    comprobar(
        'una inasistencia no pasa a atendida',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_TRANSITION'
    );

    // Reconsulta por ausencia del médico -----------------------------------
    $slot9 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot9['fecha'],
        'hora' => $slot9['hora'],
    ]));
    $citaAusenciaId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);
    $slot10 = buscarSlot($disponibilidad, $medicoId);

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaAusenciaId . '/reconsulta', [], [
        'tipo' => 'ausencia_medico',
        'fecha' => $slot10['fecha'],
        'hora' => $slot10['hora'],
        'motivo' => 'El médico no pudo atender',
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la reconsulta por ausencia cancela el origen y crea una prioritaria',
        $respuesta->status() === 201
        && ($datos['cita_origen']['estado'] ?? '') === 'cancelada'
        && ($datos['reconsulta']['estado'] ?? '') === 'programada'
        && ($datos['reconsulta']['es_reconsulta'] ?? false) === true
        && ($datos['reconsulta']['es_prioritaria'] ?? false) === true
        && ($datos['reconsulta']['reconsulta_tipo'] ?? '') === 'ausencia_medico'
        && (int) ($datos['reconsulta']['cita_origen_id'] ?? 0) === $citaAusenciaId
    );

    // Reconsulta de seguimiento -------------------------------------------
    $slot11 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot11['fecha'],
        'hora' => $slot11['hora'],
    ]));
    $citaSeguimientoId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);
    $pdo->prepare("UPDATE citas SET estado = 'confirmada' WHERE id = :id")
        ->execute(['id' => $citaSeguimientoId]);
    $enviar(solicitud('PATCH', '/api/citas/' . $citaSeguimientoId . '/atender'));

    $slot12 = buscarSlot($disponibilidad, $medicoId);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaSeguimientoId . '/reconsulta', [], [
        'tipo' => 'seguimiento',
        'fecha' => $slot12['fecha'],
        'hora' => $slot12['hora'],
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la reconsulta de seguimiento conserva la cita atendida',
        $respuesta->status() === 201
        && ($datos['cita_origen']['estado'] ?? '') === 'atendida'
        && ($datos['reconsulta']['reconsulta_tipo'] ?? '') === 'seguimiento'
        && ($datos['reconsulta']['es_prioritaria'] ?? false) === true
    );

    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaInasistenciaId . '/reconsulta', [], [
        'tipo' => 'seguimiento',
        'fecha' => $slot10['fecha'],
        'hora' => $slot10['hora'],
    ]));
    comprobar(
        'la reconsulta de seguimiento exige una cita atendida',
        $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_STATE'
    );

    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('POST', '/api/citas/' . $citaOtroPacienteId . '/reconsulta', [], [
        'tipo' => 'ausencia_medico',
        'fecha' => $slot10['fecha'],
        'hora' => $slot10['hora'],
    ]));
    comprobar(
        'el médico no registra una reconsulta por ausencia',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FORBIDDEN'
    );

    // Historial ------------------------------------------------------------
    cambiarSesion($admin);
    $respuesta = $enviar(solicitud('GET', '/api/pacientes/' . $pacienteId . '/citas'));
    $cuerpoHistorial = cuerpo($respuesta);
    comprobar(
        'el historial del paciente incluye sus citas',
        $respuesta->status() === 200
        && is_array($cuerpoHistorial['data']['citas'] ?? null)
        && count($cuerpoHistorial['data']['citas'] ?? []) > 0
    );

    $respuesta = $enviar(solicitud('GET', '/api/citas/' . $cita1Id . '/historial-estados'));
    $estados = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la creación deja rastro en el historial de estados',
        $respuesta->status() === 200
        && array_reduce(
            $estados,
            static fn (bool $ok, array $fila): bool => $ok
                || ($fila['estado_nuevo'] ?? '') === 'programada',
            false
        )
    );

    // Perfil del paciente --------------------------------------------------
    cambiarSesion($admin);
    comprobar(
        'admin no usa el perfil de paciente',
        $enviar(solicitud('GET', '/api/me/perfil'))->status() === 403
    );

    cambiarSesion($paciente);
    $respuesta = $enviar(solicitud('GET', '/api/me/perfil'));
    comprobar(
        'el paciente consulta su propio perfil',
        $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['id'] ?? 0) === 1
    );

    $respuesta = $enviar(solicitud('PUT', '/api/me/perfil', [], [
        'telefono' => '70001111',
        'ci' => '00000000',
    ]));
    $datos = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el paciente actualiza su contacto sin cambiar la CI',
        $respuesta->status() === 200
        && ($datos['telefono'] ?? '') === '70001111'
        && ($datos['ci'] ?? '') === '10000001'
    );

    // CSRF en escrituras de citas ------------------------------------------
    cambiarSesion($admin);
    $respuesta = $enviar(solicitud('POST', '/api/citas', [], [
        'paciente_id' => $pacienteId,
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $slot1['fecha'],
        'hora' => '03:00',
    ], false));
    comprobar(
        'crear cita sin CSRF se rechaza',
        $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CSRF_TOKEN_MISMATCH'
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

echo PHP_EOL . 'Todas las pruebas de recepción (M-27) pasaron.' . PHP_EOL;
