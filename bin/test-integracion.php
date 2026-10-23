#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Models\CitaModel;
use App\Models\HorarioModel;
use App\Services\AuthService;
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

function estadoDeError(callable $operacion): int
{
    try {
        $operacion();
    } catch (HttpException $exception) {
        return $exception->statusCode();
    }

    return 200;
}

/** @return array<string, mixed> */
function cuerpo(Response $response): array
{
    $datos = json_decode($response->body(), true);

    return is_array($datos) ? $datos : [];
}

/** @param array<string, mixed> $query */
function peticionConQuery(array $query, string $ruta): Request
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
    DB::clearContext();
    DB::setContext($usuario, '127.0.0.1');
}

function limpiarSesion(): void
{
    $_SESSION = [];
    DB::clearContext();
}

/** @param array<string, mixed> $parametros */
function contarSql(PDO $pdo, string $sql, array $parametros = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parametros);

    return (int) $statement->fetchColumn();
}

function comoPropietario(callable $operacion): mixed
{
    $usuario = $_SESSION['usuario'] ?? null;

    DB::clearContext();

    try {
        return $operacion();
    } finally {
        if (is_array($usuario) && $usuario !== []) {
            DB::setContext($usuario, '127.0.0.1');
        }
    }
}

/** @return array<string, mixed>|null */
function solicitudHttp(string $metodo, string $url, ?array $cuerpo = null): ?array
{
    $cabeceras = "Accept: application/json\r\n";
    $contenido = null;

    if ($cuerpo !== null) {
        $contenido = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $cabeceras .= "Content-Type: application/json\r\n";
    }

    $contexto = stream_context_create([
        'http' => [
            'method' => $metodo,
            'header' => $cabeceras,
            'content' => $contenido ?? '',
            'timeout' => 3,
            'ignore_errors' => true,
        ],
    ]);

    $respuesta = @file_get_contents($url, false, $contexto);

    if ($respuesta === false) {
        return null;
    }

    $datos = json_decode($respuesta, true);

    return is_array($datos) ? $datos : [];
}

function mailpitBase(): ?string
{
    $candidatos = [];

    $configurada = getenv('MAILPIT_API_URL');

    if (is_string($configurada) && $configurada !== '') {
        $candidatos[] = rtrim($configurada, '/');
    }

    $candidatos[] = 'http://mailpit:8025';
    $candidatos[] = 'http://127.0.0.1:8025';

    foreach (array_unique($candidatos) as $base) {
        $datos = solicitudHttp('GET', $base . '/api/v1/messages?limit=1');

        if (is_array($datos) && (array_key_exists('messages', $datos) || array_key_exists('total', $datos))) {
            return $base;
        }
    }

    return null;
}

/** @return array<int, array<string, mixed>> */
function mailpitMensajes(string $base): array
{
    $datos = solicitudHttp('GET', $base . '/api/v1/messages?limit=500');
    $mensajes = $datos['messages'] ?? [];

    return is_array($mensajes) ? $mensajes : [];
}

/** @param array<int, string> $ids */
function mailpitEliminar(string $base, array $ids): void
{
    $ids = array_values(array_filter($ids, static fn (string $id): bool => $id !== ''));

    if ($ids === []) {
        return;
    }

    solicitudHttp('DELETE', $base . '/api/v1/messages', ['IDs' => $ids]);
}

/**
 * @param array<string, mixed> $mensaje
 * @return array{detalle: array<string, mixed>, texto: string}|null
 */
function mailpitDetalleSiContiene(string $base, array $mensaje, string $codigo): ?array
{
    $id = (string) ($mensaje['ID'] ?? '');

    if ($id === '') {
        return null;
    }

    $detalle = solicitudHttp('GET', $base . '/api/v1/message/' . rawurlencode($id));

    if (!is_array($detalle)) {
        return null;
    }

    $texto = (string) ($detalle['Text'] ?? '')
        . ' ' . (string) ($detalle['HTML'] ?? '')
        . ' ' . (string) ($mensaje['Snippet'] ?? '');

    return str_contains($texto, $codigo)
        ? ['detalle' => $detalle, 'texto' => $texto]
        : null;
}

/** @param array<int, array<string, mixed>> $citas */
function contieneCita(array $citas, string $codigo): bool
{
    foreach ($citas as $cita) {
        if ((string) ($cita['codigo'] ?? '') === $codigo) {
            return true;
        }
    }

    return false;
}

/** @param array<int, array<string, mixed>> $especialidades */
function contieneEspecialidad(array $especialidades, int $id): bool
{
    foreach ($especialidades as $especialidad) {
        if ((int) ($especialidad['id'] ?? 0) === $id) {
            return true;
        }
    }

    return false;
}

$pdo = DB::connection();
$servidor = new Router();
$router = $servidor;

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

$adminDb = $pdo->query(
    "SELECT id, username FROM usuarios WHERE rol = 'admin' AND activo = TRUE ORDER BY id ASC LIMIT 1"
)->fetch();
$pacienteDb = $pdo->query(
    "SELECT id, usuario_id, ci, email, nombres, apellidos
     FROM pacientes
     WHERE ci = '10000001'
     LIMIT 1"
)->fetch();
$medicoDb = $pdo->query(
    "SELECT nombres, apellidos FROM medicos WHERE id = 3 LIMIT 1"
)->fetch();

if (!is_array($adminDb) || !is_array($pacienteDb) || !is_array($medicoDb)) {
    fwrite(STDERR, 'ERROR: faltan los datos seed de admin, paciente o medico.' . PHP_EOL);
    exit(1);
}

$admin = [
    'id' => (int) $adminDb['id'],
    'rol' => 'admin',
    'nombre' => (string) $adminDb['username'],
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];
$pacienteId = (int) $pacienteDb['id'];
$pacienteUsuarioId = (int) $pacienteDb['usuario_id'];
$destinatario = (string) $pacienteDb['email'];
$nombreMedico = trim((string) $medicoDb['nombres'] . ' ' . (string) $medicoDb['apellidos']);

$medicoId = 3;
$especialidadId = 1;
$sufijo = strtolower(bin2hex(random_bytes(4)));
$nombreEspecialidad = 'Integracion M22 ' . $sufijo;

$fecha = null;
$slot = null;
$franja = null;
$citaId = 0;
$codigo = '';
$fichaIds = [];
$historialIds = [];
$espId = 0;
$mensajesAntesIds = [];
$mensajesAEliminar = [];
$mailpit = mailpitBase();

if ($mailpit !== null) {
    foreach (mailpitMensajes($mailpit) as $mensaje) {
        $id = (string) ($mensaje['ID'] ?? '');

        if ($id !== '') {
            $mensajesAntesIds[] = $id;
        }
    }
}

try {
    limpiarSesion();

    comprobar('datos seed: admin activo', $admin['id'] > 0);
    comprobar('datos seed: paciente con usuario vinculado', $pacienteUsuarioId > 0 && $destinatario !== '');
    comprobar(
        'datos seed: medico y especialidad de la reserva',
        contarSql($pdo, "SELECT COUNT(*) FROM medicos WHERE id = :id AND estado = 'activo'", ['id' => $medicoId]) === 1
        && contarSql($pdo, "SELECT COUNT(*) FROM especialidades WHERE id = :id AND estado = 'activo'", ['id' => $especialidadId]) === 1
    );

    // Landing y páginas públicas ------------------------------------------------
    $respuesta = $servidor->dispatch(Request::create('GET', '/'));
    $html = $respuesta->body();
    comprobar('landing responde 200', $respuesta->status() === 200);
    comprobar('landing contiene el CTA de reserva', str_contains($html, 'Reservar cita'));
    comprobar('landing lista una especialidad seed', str_contains($html, 'Medicina General'));
    comprobar('landing enlaza el flujo de reserva', str_contains($html, '/reservar'));

    $respuesta = $servidor->dispatch(Request::create('GET', '/kiosco'));
    comprobar('pagina de kiosco responde 200', $respuesta->status() === 200
        && str_contains($respuesta->body(), 'kiosco-ticket-card'));

    $respuesta = $servidor->dispatch(Request::create('GET', '/reservar'));
    $ubicacion = $respuesta->headers()['Location'] ?? '';
    comprobar(
        'reservar sin sesion redirige al login',
        $respuesta->status() === 302 && str_ends_with($ubicacion, '/login')
    );

    comprobar(
        'ruta inexistente devuelve 404',
        estadoDeError(fn () => $servidor->dispatch(Request::create('GET', '/ruta-inexistente-m22'))) === 404
    );

    $respuesta = $servidor->dispatch(peticionConQuery([], '/api/public/especialidades'));
    $datos = cuerpo($respuesta);
    comprobar('API publica de especialidades responde 200', $respuesta->status() === 200);
    comprobar('API publica devuelve especialidades activas', count($datos['data'] ?? []) >= 3);

    $respuesta = $servidor->dispatch(peticionConQuery(
        ['especialidad' => $especialidadId],
        '/api/public/medicos'
    ));
    $datos = cuerpo($respuesta);
    $medicosPublicos = $datos['data'] ?? [];
    $todosConEspecialidad = $medicosPublicos !== [];

    foreach ($medicosPublicos as $medicoPublico) {
        $ids = array_map(
            static fn (array $item): int => (int) ($item['id'] ?? 0),
            (array) ($medicoPublico['especialidades'] ?? [])
        );

        if (!in_array($especialidadId, $ids, true)) {
            $todosConEspecialidad = false;
            break;
        }
    }

    comprobar('API publica filtra medicos por especialidad', $respuesta->status() === 200
        && $todosConEspecialidad);

    // Disponibilidad real -------------------------------------------------------
    $disponibilidad = new DisponibilidadService();
    $horarios = new HorarioModel();
    $inicioBusqueda = new DateTimeImmutable('today');

    for ($dia = 1; $dia <= 60 && $fecha === null; $dia++) {
        $candidata = $inicioBusqueda->modify('+' . $dia . ' days')->format('Y-m-d');
        $slots = $disponibilidad->slots($medicoId, $candidata, 'web');

        if ($slots === []) {
            continue;
        }

        $diaSemana = (int) (new DateTimeImmutable($candidata))->format('w');
        $franjas = $horarios->franjasActivas($medicoId, $diaSemana);

        foreach ($slots as $slotCandidato) {
            if (($slotCandidato['disponible'] ?? false) !== true) {
                continue;
            }

            foreach ($franjas as $franjaCandidata) {
                $inicioFranja = substr((string) $franjaCandidata['hora_inicio'], 0, 5);
                $finFranja = substr((string) $franjaCandidata['hora_fin'], 0, 5);

                if ($slotCandidato['hora'] >= $inicioFranja && $slotCandidato['hora'] < $finFranja) {
                    $fecha = $candidata;
                    $slot = $slotCandidato;
                    $franja = $franjaCandidata;
                    break 3;
                }
            }
        }
    }

    if ($fecha === null || $slot === null || $franja === null) {
        throw new RuntimeException('No hay un slot web disponible en los proximos 60 dias.');
    }

    $inicioFranja = substr((string) $franja['hora_inicio'], 0, 8);
    $finFranja = substr((string) $franja['hora_fin'], 0, 8);
    $cupoWeb = (int) $franja['cupos_web'];
    $citasModel = new CitaModel();
    $usadosAntes = $citasModel->contarCanalEnFranja(
        $medicoId,
        $fecha,
        $inicioFranja,
        $finFranja,
        'web'
    );

    comprobar('slot web con cupo disponible', $slot['disponible'] === true
        && $cupoWeb > $usadosAntes);

    $respuesta = $servidor->dispatch(peticionConQuery(
        ['medico' => $medicoId, 'fecha' => $fecha],
        '/api/public/disponibilidad'
    ));
    $datos = cuerpo($respuesta);
    $slotsPublicos = $datos['data']['slots'] ?? [];
    $slotPublico = null;

    foreach ($slotsPublicos as $slotPublicoItem) {
        if (($slotPublicoItem['hora'] ?? '') === $slot['hora']) {
            $slotPublico = $slotPublicoItem;
            break;
        }
    }

    comprobar('API publica de disponibilidad responde 200', $respuesta->status() === 200);
    comprobar('API publica muestra el slot elegido', is_array($slotPublico)
        && ($slotPublico['disponible'] ?? false) === true);

    // Login y landing autenticada ----------------------------------------------
    $auth = new AuthService();
    $pacienteSesion = $auth->login('12345678', 'Demo1234!');
    comprobar('login real del paciente', ($pacienteSesion['rol'] ?? '') === 'paciente'
        && (int) ($pacienteSesion['paciente_id'] ?? 0) === $pacienteId);

    cambiarSesion($pacienteSesion);

    $respuesta = $servidor->dispatch(Request::create('GET', '/reservar'));
    comprobar('paciente autenticado ve el formulario de reserva', $respuesta->status() === 200
        && str_contains($respuesta->body(), 'data-reserva-app'));

    // Reserva real --------------------------------------------------------------
    $respuesta = $servidor->dispatch(Request::create('POST', '/api/public/reservar', [
        'medico_id' => $medicoId,
        'especialidad_id' => $especialidadId,
        'fecha' => $fecha,
        'hora' => $slot['hora'],
        'motivo' => 'Integracion M-22 ' . $sufijo,
    ]));
    $datos = cuerpo($respuesta);
    $cita = $datos['data']['cita'] ?? [];
    $ficha = $datos['data']['ficha'] ?? [];
    $citaId = (int) ($cita['id'] ?? 0);
    $codigo = (string) ($cita['codigo'] ?? '');

    comprobar('reserva web responde 201', $respuesta->status() === 201);
    comprobar('reserva devuelve cita y ficha', $citaId > 0
        && str_starts_with($codigo, 'CM-')
        && str_starts_with((string) ($ficha['codigo'] ?? ''), 'FIC-'));
    comprobar('cita creada como programada y origen web', ($cita['estado'] ?? '') === 'programada'
        && ($cita['origen'] ?? '') === 'web');

    $citaDb = comoPropietario(function () use ($pdo, $citaId): array {
        $statement = $pdo->prepare(
            'SELECT c.*, p.usuario_id AS paciente_usuario_id
             FROM citas c
             JOIN pacientes p ON p.id = c.paciente_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $citaId]);
        $fila = $statement->fetch();

        return is_array($fila) ? $fila : [];
    });

    comprobar('cita persistida con el paciente y medico elegidos', (int) ($citaDb['paciente_id'] ?? 0) === $pacienteId
        && (int) ($citaDb['medico_id'] ?? 0) === $medicoId
        && (int) ($citaDb['especialidad_id'] ?? 0) === $especialidadId);
    comprobar('cita persistida con fecha, hora y motivo', (string) ($citaDb['fecha'] ?? '') === $fecha
        && substr((string) ($citaDb['hora_inicio'] ?? ''), 0, 5) === $slot['hora']
        && str_contains((string) ($citaDb['motivo'] ?? ''), $sufijo));

    $fichaDb = comoPropietario(function () use ($pdo, $citaId): array {
        $statement = $pdo->prepare(
            'SELECT id, codigo, canal FROM fichas WHERE cita_id = :cita_id ORDER BY id ASC'
        );
        $statement->execute(['cita_id' => $citaId]);
        $filas = $statement->fetchAll();

        return is_array($filas) ? $filas : [];
    });

    foreach ($fichaDb as $filaFicha) {
        $fichaIds[] = (int) $filaFicha['id'];
    }

    comprobar('ficha emitida en el canal web', count($fichaDb) === 1
        && (string) ($fichaDb[0]['canal'] ?? '') === 'web');

    $usadosDespues = comoPropietario(fn (): int => $citasModel->contarCanalEnFranja(
        $medicoId,
        $fecha,
        $inicioFranja,
        $finFranja,
        'web'
    ));

    comprobar('el cupo web de la franja aumenta exactamente en uno', $usadosDespues === $usadosAntes + 1);
    comprobar('el cupo web no se excede', $usadosDespues <= $cupoWeb);

    $slotSigueDisponible = comoPropietario(fn (): bool => $disponibilidad->slotDisponible(
        $medicoId,
        $fecha,
        $slot['hora'],
        'web'
    ));
    comprobar('el slot reservado deja de estar disponible', $slotSigueDisponible === false);

    comprobar(
        'segunda reserva del mismo slot es rechazada',
        codigoDeError(fn () => $servidor->dispatch(Request::create('POST', '/api/public/reservar', [
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $fecha,
            'hora' => $slot['hora'],
        ]))) === 'SLOT_TAKEN'
    );

    // Correo de confirmación ----------------------------------------------------
    cambiarSesion($admin);

    $asuntoCorreo = '';
    $textoCorreo = '';
    $correoVia = '';

    if ($mailpit !== null) {
        $mensajesDespues = mailpitMensajes($mailpit);
        $mensajesNuevos = [];

        foreach ($mensajesDespues as $mensaje) {
            $id = (string) ($mensaje['ID'] ?? '');

            if ($id === '' || in_array($id, $mensajesAntesIds, true)) {
                continue;
            }

            $mensajesNuevos[] = $mensaje;

            $para = array_map(
                static fn (array $item): string => strtolower((string) ($item['Address'] ?? '')),
                (array) ($mensaje['To'] ?? [])
            );

            if (!in_array(strtolower($destinatario), $para, true)) {
                continue;
            }

            $encontrado = mailpitDetalleSiContiene($mailpit, $mensaje, $codigo);

            if ($encontrado !== null) {
                $mensajesAEliminar[] = $id;
                $asuntoCorreo = (string) ($encontrado['detalle']['Subject'] ?? $mensaje['Subject'] ?? '');
                $textoCorreo = $encontrado['texto'];
                $correoVia = 'mailpit';
                break;
            }
        }
    }

    if ($correoVia === '') {
        $filaCola = comoPropietario(function () use ($pdo, $destinatario, $codigo): ?array {
            $statement = $pdo->prepare(
                'SELECT destinatario, asunto, cuerpo
                 FROM cola_correos
                 WHERE lower(destinatario) = lower(:destinatario)
                   AND cuerpo LIKE :codigo
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $statement->execute([
                'destinatario' => $destinatario,
                'codigo' => '%' . $codigo . '%',
            ]);
            $fila = $statement->fetch();

            return is_array($fila) ? $fila : null;
        });

        if (is_array($filaCola)) {
            $asuntoCorreo = (string) ($filaCola['asunto'] ?? '');
            $textoCorreo = (string) ($filaCola['cuerpo'] ?? '');
            $correoVia = 'cola';
        }
    }

    comprobar('correo de confirmacion localizado (Mailpit o cola)', $correoVia !== '');
    comprobar('correo de confirmacion tiene asunto esperado', str_contains(strtolower($asuntoCorreo), 'confirmaci'));
    comprobar(
        'correo incluye fecha, hora, medico y codigo',
        str_contains($textoCorreo, $fecha)
        && str_contains($textoCorreo, $slot['hora'])
        && str_contains($textoCorreo, $nombreMedico)
        && str_contains($textoCorreo, $codigo)
    );

    // Visibilidad en admin ------------------------------------------------------
    $respuesta = $servidor->dispatch(peticionConQuery([
        'paciente' => $pacienteId,
        'desde' => $fecha,
        'hasta' => $fecha,
        'per_page' => 100,
    ], '/api/citas'));
    $datos = cuerpo($respuesta);
    $citasAdmin = $datos['data'] ?? [];
    $totalPacienteFecha = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM citas WHERE paciente_id = :paciente AND fecha = CAST(:fecha AS date)',
        ['paciente' => $pacienteId, 'fecha' => $fecha]
    );
    comprobar('cita visible en el listado admin', $respuesta->status() === 200
        && contieneCita($citasAdmin, $codigo));
    comprobar('listado admin respeta el total del filtro', (int) ($datos['meta']['total'] ?? -1) === $totalPacienteFecha);

    $respuesta = $servidor->dispatch(Request::create('GET', '/api/citas/' . $citaId));
    $datos = cuerpo($respuesta);
    comprobar('detalle admin de la cita responde', $respuesta->status() === 200
        && ($datos['data']['codigo'] ?? '') === $codigo
        && (int) ($datos['data']['paciente']['id'] ?? 0) === $pacienteId);

    $respuesta = $servidor->dispatch(peticionConQuery(
        ['fecha' => $fecha],
        '/api/medicos/' . $medicoId . '/agenda'
    ));
    $datos = cuerpo($respuesta);
    $totalAgenda = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM citas WHERE medico_id = :medico AND fecha = CAST(:fecha AS date)',
        ['medico' => $medicoId, 'fecha' => $fecha]
    );
    comprobar('cita visible en la agenda del medico', $respuesta->status() === 200
        && contieneCita($datos['data']['citas'] ?? [], $codigo));
    comprobar('contadores de agenda coinciden con la base', (int) ($datos['data']['contadores']['total'] ?? -1) === $totalAgenda);

    $respuesta = $servidor->dispatch(peticionConQuery([
        'desde' => $fecha,
        'hasta' => $fecha,
    ], '/api/pacientes/' . $pacienteId . '/historial'));
    $datos = cuerpo($respuesta);
    comprobar('cita visible en el historial del paciente', $respuesta->status() === 200
        && contieneCita($datos['data']['citas'] ?? [], $codigo));

    $respuesta = $servidor->dispatch(peticionConQuery([
        'paciente' => $pacienteId,
        'desde' => $fecha,
        'hasta' => $fecha,
        'per_page' => 100,
    ], '/api/reportes/citas'));
    $datos = cuerpo($respuesta);
    comprobar('reporte de citas incluye la reserva', $respuesta->status() === 200
        && contieneCita($datos['data'] ?? [], $codigo));
    comprobar('reporte de citas suma el total del paciente', (int) ($datos['meta']['total'] ?? -1) === $totalPacienteFecha);

    $totalFecha = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM citas WHERE fecha = CAST(:fecha AS date)',
        ['fecha' => $fecha]
    );

    $respuesta = $servidor->dispatch(peticionConQuery([
        'desde' => $fecha,
        'hasta' => $fecha,
    ], '/api/reportes/citas-por-medico'));
    $datos = cuerpo($respuesta);
    $filaMedico = null;

    foreach ($datos['data'] ?? [] as $fila) {
        if ((int) ($fila['medico_id'] ?? 0) === $medicoId) {
            $filaMedico = $fila;
            break;
        }
    }

    $totalMedicoFecha = contarSql(
        $pdo,
        'SELECT COUNT(*) FROM citas WHERE medico_id = :medico AND fecha = CAST(:fecha AS date)',
        ['medico' => $medicoId, 'fecha' => $fecha]
    );
    comprobar('reporte por medico responde y suma el dia', $respuesta->status() === 200
        && (int) ($datos['meta']['total_citas'] ?? -1) === $totalFecha);
    comprobar('reporte por medico incluye la cita del medico', is_array($filaMedico)
        && (int) ($filaMedico['total'] ?? -1) === $totalMedicoFecha);

    $respuesta = $servidor->dispatch(peticionConQuery([
        'desde' => $fecha,
        'hasta' => $fecha,
    ], '/api/reportes/citas-por-especialidad'));
    $datos = cuerpo($respuesta);
    $filaEspecialidad = null;

    foreach ($datos['data'] ?? [] as $fila) {
        if ((int) ($fila['especialidad_id'] ?? 0) === $especialidadId) {
            $filaEspecialidad = $fila;
            break;
        }
    }

    comprobar('reporte por especialidad suma el dia', $respuesta->status() === 200
        && (int) ($datos['meta']['total_citas'] ?? -1) === $totalFecha);
    comprobar('reporte por especialidad incluye la cita', is_array($filaEspecialidad)
        && (int) ($filaEspecialidad['total'] ?? 0) >= 1);

    $respuesta = $servidor->dispatch(peticionConQuery([
        'desde' => $fecha,
        'hasta' => $fecha,
    ], '/api/reportes/citas-por-estado'));
    $datos = cuerpo($respuesta);
    $programadasFecha = contarSql(
        $pdo,
        "SELECT COUNT(*) FROM citas WHERE fecha = CAST(:fecha AS date) AND estado = 'programada'",
        ['fecha' => $fecha]
    );
    comprobar('reporte por estado suma el dia', $respuesta->status() === 200
        && (int) ($datos['meta']['total_citas'] ?? -1) === $totalFecha);
    comprobar('reporte por estado cuenta la cita programada', (int) ($datos['data']['programada'] ?? -1) === $programadasFecha);

    $respuesta = $servidor->dispatch(peticionConQuery([
        'desde' => $fecha,
        'hasta' => $fecha,
    ], '/api/reportes/asistencia'));
    $datos = cuerpo($respuesta);
    comprobar('reporte de asistencia suma el dia', $respuesta->status() === 200
        && (int) ($datos['data']['total'] ?? -1) === $totalFecha);

    $respuesta = $servidor->dispatch(peticionConQuery([
        'medico' => $medicoId,
        'fecha' => $fecha,
    ], '/api/reportes/agenda-diaria'));
    $datos = cuerpo($respuesta);
    comprobar('reporte de agenda diaria incluye la cita', $respuesta->status() === 200
        && contieneCita($datos['data']['citas'] ?? [], $codigo));
    comprobar('reporte de agenda diaria coincide con la agenda', (int) ($datos['data']['contadores']['total'] ?? -1) === $totalMedicoFecha);

    $respuesta = $servidor->dispatch(Request::create('GET', '/api/dashboard/admin'));
    $datos = cuerpo($respuesta);
    comprobar('dashboard admin responde con contadores', $respuesta->status() === 200
        && array_key_exists('citas_hoy', $datos['data'] ?? [])
        && array_key_exists('total_pacientes', $datos['data'] ?? []));

    $respuesta = $servidor->dispatch(peticionConQuery([
        'tabla' => 'citas',
        'accion' => 'INSERT',
        'usuario_id' => $pacienteUsuarioId,
        'per_page' => 100,
    ], '/api/auditoria'));
    $datos = cuerpo($respuesta);
    $auditoriaCita = false;

    foreach ($datos['data'] ?? [] as $registro) {
        if ((int) ($registro['registro_id'] ?? 0) === $citaId) {
            $auditoriaCita = true;
            break;
        }
    }

    comprobar('auditoria registra la reserva del paciente', $respuesta->status() === 200
        && $auditoriaCita);

    // CRUD temporal visible en el catalogo publico -------------------------------
    $csrf = Session::csrfToken();

    $respuesta = $servidor->dispatch(Request::create('POST', '/api/especialidades', [
        'nombre' => $nombreEspecialidad,
        'descripcion' => 'Especialidad temporal de la prueba M-22.',
        'sexo_permitido' => 'ambos',
        'edad_min' => null,
        'edad_max' => null,
        'destacada' => false,
        'icono' => null,
        'estado' => 'activo',
    ], ['X-CSRF-Token' => $csrf]));
    $datos = cuerpo($respuesta);
    $espId = (int) ($datos['data']['id'] ?? 0);
    comprobar('admin crea una especialidad temporal', $respuesta->status() === 201
        && $espId > 0
        && ($datos['data']['estado'] ?? '') === 'activo');

    limpiarSesion();
    $respuesta = $servidor->dispatch(peticionConQuery([], '/api/public/especialidades'));
    $datos = cuerpo($respuesta);
    comprobar('especialidad nueva visible en el catalogo publico', $respuesta->status() === 200
        && contieneEspecialidad($datos['data'] ?? [], $espId));

    cambiarSesion($admin);
    $csrf = Session::csrfToken();

    $respuesta = $servidor->dispatch(Request::create('PUT', '/api/especialidades/' . $espId, [
        'descripcion' => 'Especialidad temporal editada por M-22.',
        'destacada' => true,
    ], ['X-CSRF-Token' => $csrf]));
    $datos = cuerpo($respuesta);
    comprobar('admin edita la especialidad temporal', $respuesta->status() === 200
        && ($datos['data']['descripcion'] ?? '') === 'Especialidad temporal editada por M-22.'
        && ($datos['data']['destacada'] ?? false) === true);

    $respuesta = $servidor->dispatch(Request::create('PATCH', '/api/especialidades/' . $espId . '/estado', [
        'estado' => 'inactivo',
    ], ['X-CSRF-Token' => $csrf]));
    $datos = cuerpo($respuesta);
    comprobar('admin desactiva la especialidad temporal', $respuesta->status() === 200
        && ($datos['data']['estado'] ?? '') === 'inactivo');

    limpiarSesion();
    $respuesta = $servidor->dispatch(peticionConQuery([], '/api/public/especialidades'));
    $datos = cuerpo($respuesta);
    comprobar('especialidad inactiva desaparece del catalogo publico', $respuesta->status() === 200
        && !contieneEspecialidad($datos['data'] ?? [], $espId));

    cambiarSesion($admin);
    $respuesta = $servidor->dispatch(Request::create('GET', '/api/especialidades/' . $espId));
    $datos = cuerpo($respuesta);
    comprobar('admin conserva el detalle de la especialidad inactiva', $respuesta->status() === 200
        && ($datos['data']['estado'] ?? '') === 'inactivo');
} catch (Throwable $exception) {
    $fallos++;
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
} finally {
    limpiarSesion();

    if ($mailpit !== null && $mensajesAEliminar !== []) {
        mailpitEliminar($mailpit, $mensajesAEliminar);
    }

    if ($citaId > 0) {
        try {
            $historialIds = $pdo->query(
                'SELECT id FROM citas_estado_historial WHERE cita_id = ' . $citaId
            )->fetchAll(PDO::FETCH_COLUMN);
            $fichaIds = array_merge(
                $fichaIds,
                array_map('intval', $pdo->query(
                    'SELECT id FROM fichas WHERE cita_id = ' . $citaId
                )->fetchAll(PDO::FETCH_COLUMN))
            );

            $pdo->exec('DELETE FROM citas_estado_historial WHERE cita_id = ' . $citaId);
            $pdo->exec('DELETE FROM fichas WHERE cita_id = ' . $citaId);
            $pdo->exec('DELETE FROM citas WHERE id = ' . $citaId);

            $idsAuditoria = array_values(array_unique(array_merge(
                [$citaId],
                array_map('intval', $fichaIds),
                array_map('intval', $historialIds)
            )));

            if ($idsAuditoria !== []) {
                $pdo->exec(
                    "DELETE FROM auditoria
                     WHERE tabla_afectada IN ('citas', 'fichas', 'citas_estado_historial')
                       AND registro_id IN (" . implode(',', $idsAuditoria) . ')'
                );
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, 'ERROR limpiando la cita de integracion: ' . $exception->getMessage() . PHP_EOL);
        }
    }

    if ($codigo !== '') {
        try {
            $statement = $pdo->prepare('DELETE FROM cola_correos WHERE cuerpo LIKE :codigo');
            $statement->execute(['codigo' => '%' . $codigo . '%']);
        } catch (Throwable $exception) {
            fwrite(STDERR, 'ERROR limpiando la cola de correos: ' . $exception->getMessage() . PHP_EOL);
        }
    }

    if ($espId > 0) {
        try {
            $pdo->exec('DELETE FROM especialidades WHERE id = ' . $espId);
            $pdo->exec(
                "DELETE FROM auditoria
                 WHERE tabla_afectada = 'especialidades'
                   AND registro_id = " . $espId
            );
        } catch (Throwable $exception) {
            fwrite(STDERR, 'ERROR limpiando la especialidad temporal: ' . $exception->getMessage() . PHP_EOL);
        }
    }
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
