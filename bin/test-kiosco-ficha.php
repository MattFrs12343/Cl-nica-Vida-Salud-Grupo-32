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

/** @param array<int, array<string, mixed>> $slots */
function slotDisponible(array $slots, ?string $excluirHora = null): ?array
{
    foreach ($slots as $slot) {
        if (($slot['disponible'] ?? false) === true && $slot['hora'] !== $excluirHora) {
            return $slot;
        }
    }

    return null;
}

/**
 * Busca el primer horario kiosco libre en los próximos 60 días.
 *
 * @return array{fecha: string, slot: array<string, mixed>}|null
 */
function buscarSlot(DisponibilidadService $disponibilidad, int $medicoId, ?string $excluirFecha = null, ?string $excluirHora = null): ?array
{
    for ($dia = 1; $dia <= 60; $dia++) {
        $fecha = (new DateTimeImmutable('today'))->modify('+' . $dia . ' days')->format('Y-m-d');

        if ($fecha === $excluirFecha) {
            continue;
        }

        $slot = slotDisponible($disponibilidad->slots($medicoId, $fecha, 'kiosco'), $excluirHora);

        if ($slot !== null) {
            return ['fecha' => $fecha, 'slot' => $slot];
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
    "SELECT m.id AS medico_id, e.id AS especialidad_id
     FROM medicos m
     JOIN medico_especialidad me ON me.medico_id = m.id
     JOIN especialidades e ON e.id = me.especialidad_id
     WHERE m.codigo = 'MED-DEMO-001' AND e.nombre = 'Medicina General'
     LIMIT 1"
)->fetch();
$pacienteId = (int) $pdo->query(
    "SELECT id FROM pacientes WHERE ci = '10000002' LIMIT 1"
)->fetchColumn();

if (!is_array($medicoDb) || $pacienteId <= 0) {
    fwrite(STDERR, 'ERROR: faltan los datos seed de médico o paciente.' . PHP_EOL);
    exit(1);
}

$medicoId = (int) $medicoDb['medico_id'];
$especialidadId = (int) $medicoDb['especialidad_id'];
$disponibilidad = new DisponibilidadService();

$primero = buscarSlot($disponibilidad, $medicoId);
$segundo = $primero === null
    ? null
    : buscarSlot($disponibilidad, $medicoId, $primero['fecha']);

if ($primero === null || $segundo === null) {
    fwrite(STDERR, 'ERROR: no hay cupo kiosco suficiente para las pruebas.' . PHP_EOL);
    exit(1);
}

$pdo->beginTransaction();

try {
    // Autenticación ---------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco('POST', '/api/kiosco/ficha', [], ['cita_id' => 1]));
    comprobar('emitir ficha exige token de kiosco', $respuesta->status() === 401
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'UNAUTHENTICATED');

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/ficha/FIC-0001',
        [],
        null
    ));
    comprobar('consultar ficha exige token de kiosco', $respuesta->status() === 401);

    // Validaciones ----------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/ficha',
        [],
        ['cita_id' => 'abc'],
        $token
    ));
    comprobar('emitir ficha valida la cita', $respuesta->status() === 422
        && isset(cuerpo($respuesta)['error']['fields']['cita_id']));

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/ficha',
        [],
        ['cita_id' => 999999],
        $token
    ));
    comprobar('emitir ficha de una cita inexistente da 404', $respuesta->status() === 404
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CITA_NOT_FOUND');

    // Reserva base ----------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $primero['fecha'],
            'hora' => $primero['slot']['hora'],
            'motivo' => 'Consulta para ficha',
        ],
        $token
    ));
    $cita = cuerpo($respuesta)['data'] ?? [];
    $citaId = (int) ($cita['id'] ?? 0);
    comprobar('la reserva previa a la ficha se crea programada', $respuesta->status() === 201
        && $citaId > 0
        && ($cita['estado'] ?? '') === 'programada');

    $historialAntes = (int) $pdo->query(
        'SELECT COUNT(*) FROM citas_estado_historial WHERE cita_id = ' . $citaId
    )->fetchColumn();

    // Emisión ---------------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/ficha',
        [],
        ['cita_id' => $citaId, 'verificacion_biometrica' => true],
        $token
    ));
    $datos = cuerpo($respuesta)['data'] ?? [];
    $codigoFicha = (string) ($datos['codigo'] ?? '');
    $qr = (string) ($datos['qr_contenido'] ?? '');
    comprobar('la ficha se emite con código y acceso', $respuesta->status() === 201
        && str_starts_with($codigoFicha, 'FIC-')
        && preg_match('/^\d{4}$/', (string) ($datos['codigo_acceso'] ?? '')) === 1);
    comprobar('la ficha confirma la cita', ($datos['cita']['estado'] ?? '') === 'confirmada'
        && ($datos['canal'] ?? '') === 'kiosco'
        && ($datos['verificacion_biometrica'] ?? false) === true);
    comprobar('el QR identifica ficha y cita', str_starts_with($qr, 'VIDASALUD|' . $codigoFicha . '|CM-')
        && str_contains((string) ($datos['cita']['codigo'] ?? ''), 'CM-'));
    comprobar('la ficha trae datos de impresión', ($datos['impresion']['codigo'] ?? '') === $codigoFicha
        && ($datos['impresion']['paciente'] ?? '') !== ''
        && ($datos['impresion']['especialidad'] ?? '') === 'Medicina General'
        && ($datos['impresion']['instrucciones'] ?? '') !== ''
        && ($datos['impresion']['fecha'] ?? '') !== '');
    comprobar('la ficha identifica al paciente y al médico', (int) ($datos['paciente']['id'] ?? 0) === $pacienteId
        && ($datos['paciente']['ci'] ?? '') === '10000002'
        && str_starts_with((string) ($datos['medico']['nombre_completo'] ?? ''), 'Dr(a). '));
    comprobar('la respuesta de ficha no se cachea', ($respuesta->headers()['Cache-Control'] ?? '') === 'no-store');

    $fichaDb = $pdo->query(
        'SELECT codigo, cita_id, canal, verificacion_biometrica
         FROM fichas WHERE cita_id = ' . $citaId
    )->fetch();
    comprobar('la ficha queda guardada en el canal kiosco', is_array($fichaDb)
        && $fichaDb['codigo'] === $codigoFicha
        && $fichaDb['canal'] === 'kiosco'
        && $fichaDb['verificacion_biometrica'] === true);

    $estadoDb = (string) $pdo->query(
        'SELECT estado FROM citas WHERE id = ' . $citaId
    )->fetchColumn();
    comprobar('la cita queda confirmada en la base', $estadoDb === 'confirmada');

    $historial = $pdo->query(
        'SELECT estado_anterior, estado_nuevo, usuario_id
         FROM citas_estado_historial
         WHERE cita_id = ' . $citaId . '
         ORDER BY id DESC
         LIMIT 1'
    )->fetch();
    $historialDespues = (int) $pdo->query(
        'SELECT COUNT(*) FROM citas_estado_historial WHERE cita_id = ' . $citaId
    )->fetchColumn();
    comprobar('la emisión registra el cambio de estado', is_array($historial)
        && $historial['estado_anterior'] === 'programada'
        && $historial['estado_nuevo'] === 'confirmada'
        && $historial['usuario_id'] === null
        && $historialDespues === $historialAntes + 1);

    // Idempotencia ----------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/ficha',
        [],
        ['cita_id' => $citaId, 'verificacion_biometrica' => true],
        $token
    ));
    $repetida = cuerpo($respuesta)['data'] ?? [];
    $totalFichas = (int) $pdo->query(
        'SELECT COUNT(*) FROM fichas WHERE cita_id = ' . $citaId
    )->fetchColumn();
    $historialFinal = (int) $pdo->query(
        'SELECT COUNT(*) FROM citas_estado_historial WHERE cita_id = ' . $citaId
    )->fetchColumn();
    comprobar('reeemitir la ficha es idempotente', $respuesta->status() === 200
        && ($repetida['codigo'] ?? '') === $codigoFicha
        && ($repetida['codigo_acceso'] ?? '') === ($datos['codigo_acceso'] ?? '')
        && $totalFichas === 1);
    comprobar('reeemitir no duplica el historial', $historialFinal === $historialDespues);

    // Reconsulta ------------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/ficha/' . $codigoFicha,
        [],
        null,
        $token
    ));
    $consultada = cuerpo($respuesta)['data'] ?? [];
    comprobar('la ficha se consulta por código', $respuesta->status() === 200
        && ($consultada['codigo'] ?? '') === $codigoFicha
        && ($consultada['qr_contenido'] ?? '') === $qr
        && ($consultada['cita']['id'] ?? 0) === $citaId);

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/ficha/FIC-999999',
        [],
        null,
        $token
    ));
    comprobar('una ficha inexistente da 404', $respuesta->status() === 404
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'FICHA_NOT_FOUND');

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/ficha/NO-ES-FICHA',
        [],
        null,
        $token
    ));
    comprobar('un código con formato inválido da 404', $respuesta->status() === 404);

    // Cita cancelada --------------------------------------------------------------
    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/reservar',
        [],
        [
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $segundo['fecha'],
            'hora' => $segundo['slot']['hora'],
        ],
        $token
    ));
    $citaCancelableId = (int) (cuerpo($respuesta)['data']['id'] ?? 0);
    comprobar('se crea una segunda cita para cancelar', $respuesta->status() === 201
        && $citaCancelableId > 0);

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/ficha',
        [],
        ['cita_id' => $citaCancelableId],
        $token
    ));
    $sinVerificacion = cuerpo($respuesta)['data'] ?? [];
    comprobar('la verificación facial es opcional', $respuesta->status() === 201
        && ($sinVerificacion['verificacion_biometrica'] ?? true) === false);

    $pdo->exec("UPDATE citas SET estado = 'cancelada' WHERE id = " . $citaCancelableId);

    $respuesta = $enviar(peticionKiosco(
        'POST',
        '/api/kiosco/ficha',
        [],
        ['cita_id' => $citaCancelableId],
        $token
    ));
    comprobar('no se emite ficha de una cita cancelada', $respuesta->status() === 409
        && (cuerpo($respuesta)['error']['code'] ?? '') === 'CITA_CANCELLED');

    $respuesta = $enviar(peticionKiosco(
        'GET',
        '/api/kiosco/ficha/' . (string) ($sinVerificacion['codigo'] ?? ''),
        [],
        null,
        $token
    ));
    comprobar('la reconsulta muestra la cita cancelada', $respuesta->status() === 200
        && (cuerpo($respuesta)['data']['cita']['estado'] ?? '') === 'cancelada');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
