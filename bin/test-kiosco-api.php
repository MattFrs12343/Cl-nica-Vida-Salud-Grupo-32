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

/** @return array<string, mixed> */
function cuerpo(Response $response): array
{
    $datos = json_decode($response->body(), true);

    return is_array($datos) ? $datos : [];
}

/**
 * Construye un request con query string y headers de kiosco.
 *
 * @param array<string, mixed> $query
 * @param array<string, mixed>|null $body
 */
function peticionKiosco(
    string $metodo,
    string $ruta,
    array $query = [],
    ?array $body = null,
    string $token = ''
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

    unset($_SERVER['HTTP_X_KIOSK_TOKEN']);

    if ($token !== '') {
        $_SERVER['HTTP_X_KIOSK_TOKEN'] = $token;
    }

    try {
        return Request::capture();
    } finally {
        $_GET = $getAnterior;
        $_POST = $postAnterior;
        $_SERVER = $serverAnterior;
    }
}

/** @param array<int, array<string, mixed>> $lista */
function contieneEntero(array $lista, string $campo, int $buscado): bool
{
    foreach ($lista as $item) {
        if ((int) ($item[$campo] ?? 0) === $buscado) {
            return true;
        }
    }

    return false;
}

/** @param array<int, array<string, mixed>> $slots */
function slotDisponible(array $slots): ?array
{
    foreach ($slots as $slot) {
        if (($slot['disponible'] ?? false) === true) {
            return $slot;
        }
    }

    return null;
}

$config = (array) config('app.kiosk', []);
$deviceKey = (string) ($config['device_key'] ?? '');

if ($deviceKey === '') {
    fwrite(STDERR, 'ERROR: falta KIOSK_DEVICE_KEY.' . PHP_EOL);
    exit(1);
}

$pdo = DB::connection();
$servidor = new Router();
$router = $servidor;

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

/**
 * Despacha convirtiendo las HttpException en respuestas JSON, como hace el
 * front controller con ErrorHandler.
 */
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

$respuesta = $enviar(Request::create('POST', '/api/kiosco/sesion', [
    'device_key' => $deviceKey,
]));
$token = (string) (cuerpo($respuesta)['data']['token'] ?? '');

if ($token === '') {
    fwrite(STDERR, 'ERROR: no se pudo obtener el token de kiosco.' . PHP_EOL);
    exit(1);
}

$medicoDb = $pdo->query(
    "SELECT m.id AS medico_id, m.nombres, m.apellidos, e.id AS especialidad_id
     FROM medicos m
     JOIN medico_especialidad me ON me.medico_id = m.id
     JOIN especialidades e ON e.id = me.especialidad_id
     WHERE m.codigo = 'MED-DEMO-001' AND e.nombre = 'Medicina General'
     LIMIT 1"
)->fetch();
$ginecologiaId = (int) $pdo->query(
    "SELECT id FROM especialidades WHERE nombre = 'Ginecología' LIMIT 1"
)->fetchColumn();
$medicoGinecologiaId = (int) $pdo->query(
    "SELECT m.id
     FROM medicos m
     JOIN medico_especialidad me ON me.medico_id = m.id
     WHERE me.especialidad_id = " . $ginecologiaId . "
     LIMIT 1"
)->fetchColumn();
$masculino = $pdo->query(
    "SELECT id, ci, fecha_nacimiento, sexo FROM pacientes WHERE ci = '10000001' LIMIT 1"
)->fetch();
$femenino = $pdo->query(
    "SELECT id, ci, fecha_nacimiento, sexo FROM pacientes WHERE ci = '10000002' LIMIT 1"
)->fetch();

if (!is_array($medicoDb) || !is_array($masculino) || !is_array($femenino) || $ginecologiaId <= 0) {
    fwrite(STDERR, 'ERROR: faltan los datos seed de medico/pacientes/especialidad.' . PHP_EOL);
    exit(1);
}

$medicoId = (int) $medicoDb['medico_id'];
$especialidadId = (int) $medicoDb['especialidad_id'];
$masculinoId = (int) $masculino['id'];
$femeninoId = (int) $femenino['id'];

$disponibilidad = new DisponibilidadService();
$fecha = null;
$slot = null;

for ($dia = 1; $dia <= 60 && $slot === null; $dia++) {
    $candidata = (new DateTimeImmutable('today'))->modify('+' . $dia . ' days')->format('Y-m-d');
    $candidato = slotDisponible($disponibilidad->slots($medicoId, $candidata, 'kiosco'));

    if ($candidato !== null) {
        $fecha = $candidata;
        $slot = $candidato;
    }
}

if ($fecha === null || $slot === null) {
    fwrite(STDERR, 'ERROR: no hay cupo kiosco disponible en los proximos 60 dias.' . PHP_EOL);
    exit(1);
}

$nuevaCi = (string) random_int(20000000, 29999999);

while ($pdo->query("SELECT 1 FROM pacientes WHERE ci = " . $pdo->quote($nuevaCi))->fetchColumn() !== false) {
    $nuevaCi = (string) random_int(20000000, 29999999);
}

$ciConComplemento = $nuevaCi . '-1a';

$pdo->beginTransaction();

try {
    // Autenticación del dispositivo ---------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/identificar',
        [],
        ['ci' => '10000001']
    ));
    comprobar('identificar exige token de kiosco', $respuesta->status() === 401
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'UNAUTHENTICATED');

    $respuesta = $enviar(peticionKiosco('GET', '/api/kiosco/especialidades'));
    comprobar('los catalogos exigen token de kiosco', $respuesta->status() === 401);

    // Identificar ----------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/identificar',
        [],
        ['ci' => 'abc'],
        $token
    ));
    comprobar('identificar rechaza una CI invalida', $respuesta->status() === 422
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'VALIDATION_ERROR');

    $respuesta = $enviar(peticionKiosco('POST', '/api/kiosco/identificar', [], [], $token));
    comprobar('identificar exige la CI', $respuesta->status() === 422);

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/identificar',
        [],
        ['ci' => '99999999'],
        $token
    ));
    $datos = cuerpo($respuesta);
    comprobar('identificar una CI desconocida pide registro', $respuesta->status() === 200
        && ($datos['data']['existe'] ?? null) === false
        && ($datos['data']['requiere_registro'] ?? null) === true);
    comprobar('la respuesta de identificar no se cachea', ($respuesta->headers()['Cache-Control'] ?? '') === 'no-store');

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/identificar',
        [],
        ['ci' => '10000001'],
        $token
    ));
    $datos = cuerpo($respuesta);
    $paciente = $datos['data']['paciente'] ?? [];
    comprobar('identificar una CI existente devuelve al paciente', $respuesta->status() === 200
        && ($datos['data']['existe'] ?? null) === true
        && (int) ($paciente['id'] ?? 0) === $masculinoId
        && ($paciente['ci'] ?? '') === '10000001');
    comprobar('identificar no expone datos clinicos ni correo', !array_key_exists('email', $paciente)
        && !array_key_exists('alergias', $paciente)
        && !array_key_exists('enfermedades_cronicas', $paciente));

    // Registro rápido -------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/registrar',
        [],
        ['ci' => '123', 'nombres' => '', 'apellidos' => ''],
        $token
    ));
    $error = cuerpo($respuesta)['error'] ?? [];
    comprobar('el alta rapida valida los campos', $respuesta->status() === 422
        && isset($error['fields']['ci'], $error['fields']['nombres'], $error['fields']['apellidos'])
        && isset($error['fields']['fecha_nacimiento'], $error['fields']['sexo'], $error['fields']['telefono']));

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/registrar',
        [],
        [
            'ci' => $ciConComplemento,
            'nombres' => 'María José',
            'apellidos' => 'Gómez Quispe',
            'fecha_nacimiento' => '1990-05-12',
            'sexo' => 'femenino',
            'telefono' => '71234567',
        ],
        $token
    ));
    $datos = cuerpo($respuesta);
    $nuevoPaciente = $datos['data'] ?? [];
    $nuevoId = (int) ($nuevoPaciente['id'] ?? 0);
    comprobar('el alta rapida crea un paciente sin cuenta', $respuesta->status() === 201
        && $nuevoId > 0
        && str_starts_with((string) ($nuevoPaciente['codigo'] ?? ''), 'PAC-')
        && ($nuevoPaciente['estado'] ?? '') === 'activo'
        && ($nuevoPaciente['sexo'] ?? '') === 'femenino');
    comprobar('la CI con complemento se normaliza a mayusculas', ($nuevoPaciente['ci'] ?? '') === strtoupper($ciConComplemento));

    $verificacion = $pdo->query(
        'SELECT usuario_id, estado FROM pacientes WHERE id = ' . $nuevoId
    )->fetch();
    comprobar('el paciente del kiosco queda sin usuario y activo', is_array($verificacion)
        && $verificacion['usuario_id'] === null
        && $verificacion['estado'] === 'activo');

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/identificar',
        [],
        ['ci' => $ciConComplemento],
        $token
    ));
    comprobar('el paciente recien creado se identifica igual', $respuesta->status() === 200
        && (int) (cuerpo($respuesta)['data']['paciente']['id'] ?? 0) === $nuevoId);

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/registrar',
        [],
        [
            'ci' => $ciConComplemento,
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'fecha_nacimiento' => '1985-01-01',
            'sexo' => 'femenino',
            'telefono' => '70000000',
        ],
        $token
    ));
    comprobar('el alta rapida rechaza una CI duplicada', $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CI_TAKEN');

    // Catálogos con elegibilidad ---------------------------------------------------
    $respuesta = $enviar(peticionKiosco('GET', '/api/kiosco/especialidades', [], null, $token));
    $datos = cuerpo($respuesta);
    comprobar('el kiosco lista las especialidades activas', $respuesta->status() === 200
        && count($datos['data'] ?? []) > 0);

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/especialidades',
        ['paciente_id' => $masculinoId],
        null,
        $token
    ));
    $especialidadesMasculino = cuerpo($respuesta)['data'] ?? [];
    comprobar('un paciente masculino no ve Ginecologia', !contieneEntero($especialidadesMasculino, 'id', $ginecologiaId));

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/especialidades',
        ['paciente_id' => $femeninoId],
        null,
        $token
    ));
    $especialidadesFemenino = cuerpo($respuesta)['data'] ?? [];
    comprobar('una paciente femenina ve Ginecologia', contieneEntero($especialidadesFemenino, 'id', $ginecologiaId));

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/especialidades',
        ['paciente_id' => 'abc'],
        null,
        $token
    ));
    comprobar('el paciente_id invalido devuelve 422', $respuesta->status() === 422);

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/medicos',
        ['especialidad' => $ginecologiaId, 'paciente_id' => $masculinoId],
        null,
        $token
    ));
    comprobar('el kiosco bloquea una especialidad no elegible', $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'NOT_ELIGIBLE');

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/medicos',
        ['especialidad' => $ginecologiaId, 'paciente_id' => $femeninoId],
        null,
        $token
    ));
    $datos = cuerpo($respuesta);
    comprobar('el kiosco lista medicos de la especialidad', $respuesta->status() === 200
        && contieneEntero($datos['data'] ?? [], 'id', $medicoGinecologiaId));

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/medicos',
        ['especialidad' => 'abc'],
        null,
        $token
    ));
    comprobar('el filtro de especialidad invalido devuelve 422', $respuesta->status() === 422);

    // Disponibilidad ----------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/disponibilidad',
        ['medico' => $medicoId, 'fecha' => $fecha],
        null,
        $token
    ));
    $datos = cuerpo($respuesta);
    $slots = $datos['data']['slots'] ?? [];
    comprobar('la disponibilidad del kiosco responde 200', $respuesta->status() === 200
        && ($datos['data']['canal'] ?? '') === 'kiosco'
        && count($slots) > 0);
    comprobar('la disponibilidad del kiosco tiene slots libres', slotDisponible($slots) !== null);

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/disponibilidad',
        ['medico' => $medicoId, 'fecha' => (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d')],
        null,
        $token
    ));
    comprobar('la disponibilidad rechaza fechas pasadas', $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['fecha']));

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/disponibilidad',
        ['medico' => 999999, 'fecha' => $fecha],
        null,
        $token
    ));
    comprobar('la disponibilidad rechaza un medico inexistente', $respuesta->status() === 404);

    // Reserva ----------------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $fecha,
            'hora' => $slot['hora'],
        ],
        $token
    ));
    comprobar('la reserva exige al paciente identificado', $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['paciente_id']));

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'paciente_id' => $masculinoId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => (new DateTimeImmutable('today'))->modify('-1 day')->format('Y-m-d'),
            'hora' => $slot['hora'],
        ],
        $token
    ));
    comprobar('la reserva rechaza fechas pasadas', $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['fecha']));

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'paciente_id' => $masculinoId,
            'medico_id' => $medicoGinecologiaId,
            'especialidad_id' => $ginecologiaId,
            'fecha' => $fecha,
            'hora' => $slot['hora'],
        ],
        $token
    ));
    comprobar('la reserva bloquea una especialidad no elegible', $respuesta->status() === 403
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'NOT_ELIGIBLE');

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'paciente_id' => $masculinoId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $fecha,
            'hora' => $slot['hora'],
            'motivo' => 'Consulta en kiosco',
        ],
        $token
    ));
    $datos = cuerpo($respuesta);
    $cita = $datos['data'] ?? [];
    $citaId = (int) ($cita['id'] ?? 0);
    comprobar('la reserva del kiosco crea la cita', $respuesta->status() === 201
        && $citaId > 0
        && ($cita['origen'] ?? '') === 'kiosco'
        && ($cita['estado'] ?? '') === 'programada'
        && ($cita['fecha'] ?? '') === $fecha
        && ($cita['hora_inicio'] ?? '') === $slot['hora']);

    $citaDb = $pdo->query(
        'SELECT origen, estado, paciente_id, medico_id FROM citas WHERE id = ' . $citaId
    )->fetch();
    comprobar('la cita queda guardada con origen kiosco', is_array($citaDb)
        && $citaDb['origen'] === 'kiosco'
        && $citaDb['estado'] === 'programada'
        && (int) $citaDb['paciente_id'] === $masculinoId);

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'paciente_id' => $femeninoId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $fecha,
            'hora' => $slot['hora'],
        ],
        $token
    ));
    comprobar('el kiosco rechaza un slot ya tomado', $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'SLOT_TAKEN');

    // Cupo kiosco agotado ------------------------------------------------------------
    $actualizarCupos = $pdo->prepare(
        'UPDATE horarios
         SET cupos_kiosco = 0
         WHERE medico_id = :medico_id
           AND dia_semana = :dia_semana'
    );
    $actualizarCupos->execute([
        'medico_id' => $medicoId,
        'dia_semana' => (int) (new DateTimeImmutable($fecha))->format('w'),
    ]);

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/disponibilidad',
        ['medico' => $medicoId, 'fecha' => $fecha],
        null,
        $token
    ));
    $slotsKiosco = cuerpo($respuesta)['data']['slots'] ?? [];
    comprobar('sin cupo kiosco no quedan slots disponibles', $respuesta->status() === 200
        && $slotsKiosco !== []
        && slotDisponible($slotsKiosco) === null);

    $slotsWeb = $disponibilidad->slots($medicoId, $fecha, 'web');
    comprobar('la web conserva cupo cuando el kiosco se agota', slotDisponible($slotsWeb) !== null);
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
