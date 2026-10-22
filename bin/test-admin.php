#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Controllers\Api\DisponibilidadApiController;
use App\Controllers\Api\EspecialidadApiController;
use App\Controllers\Api\HorarioApiController;
use App\Controllers\Api\MedicoApiController;
use App\Controllers\Api\UsuarioApiController;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Models\EspecialidadModel;
use App\Models\HorarioModel;
use App\Models\MedicoModel;
use App\Models\UsuarioModel;

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
function peticionConQuery(array $query, string $ruta = '/api/disponibilidad'): Request
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

function cambiarSesion(array $usuario): void
{
    $_SESSION['usuario'] = $usuario;
    DB::setContext($usuario, '127.0.0.1');
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

$sufijo = strtolower(bin2hex(random_bytes(3)));
$prefijo = 'testadmin_' . $sufijo;
$registro = 'TESTADMIN-' . $sufijo;
$fecha = (new DateTimeImmutable('next saturday'))->format('Y-m-d');

$recId = null;
$espId = null;
$medId = null;
$medUsuarioId = null;
$horarioId = null;
$bloqueoId = null;

cambiarSesion($admin);

try {
    $usuarios = new UsuarioModel();
    $especialidades = new EspecialidadModel();
    $medicos = new MedicoModel();
    $horarios = new HorarioModel();

    $usuarioCtrl = new UsuarioApiController($usuarios);
    $especialidadCtrl = new EspecialidadApiController($especialidades);
    $medicoCtrl = new MedicoApiController($medicos, $especialidades, $usuarios);
    $horarioCtrl = new HorarioApiController($horarios, $medicos);
    $disponibilidadCtrl = new DisponibilidadApiController(null, $medicos);

    // Usuarios -----------------------------------------------------------------
    $usuariosAdmin = $usuarios->listarAdmin(['rol' => 'admin', 'estado' => 'activo'], 1, 100);
    comprobar('listado admin de usuarios responde', $usuariosAdmin['total'] >= 1);

    $respuesta = $usuarioCtrl->store(Request::create('POST', '/api/usuarios', [
        'username' => $prefijo . '.rec',
        'email' => $prefijo . '.rec@example.test',
        'password' => 'ClaveSegura1',
        'rol' => 'recepcionista_medico',
        'activo' => true,
    ]));
    $datos = cuerpo($respuesta);
    $recId = (int) ($datos['data']['id'] ?? 0);
    comprobar('crear usuario devuelve 201 y el rol', $respuesta->status() === 201
        && ($datos['data']['rol'] ?? null) === 'recepcionista_medico');

    comprobar('usuario duplicado devuelve USERNAME_TAKEN', codigoDeError(
        fn () => $usuarioCtrl->store(Request::create('POST', '/api/usuarios', [
            'username' => $prefijo . '.rec',
            'email' => $prefijo . '.otro@example.test',
            'password' => 'ClaveSegura1',
            'rol' => 'recepcionista',
            'activo' => true,
        ]))
    ) === 'USERNAME_TAKEN');

    $respuesta = $usuarioCtrl->update(Request::create('PUT', '/api/usuarios/' . $recId, [
        'email' => $prefijo . '.rec@example.test',
        'rol' => 'recepcionista_medico',
        'activo' => false,
    ]), ['id' => (string) $recId]);
    comprobar('editar usuario desactiva la cuenta', cuerpo($respuesta)['data']['activo'] === false);

    $pdo->prepare('UPDATE usuarios SET bloqueado = TRUE, intentos_fallidos = 2 WHERE id = :id')
        ->execute(['id' => $recId]);
    $respuesta = $usuarioCtrl->desbloquear(
        Request::create('PATCH', '/api/usuarios/' . $recId . '/desbloquear'),
        ['id' => (string) $recId]
    );
    comprobar('desbloquear limpia intentos y bloqueo', cuerpo($respuesta)['data']['bloqueado'] === false
        && (int) cuerpo($respuesta)['data']['intentos_fallidos'] === 0);

    $respuesta = $usuarioCtrl->estado(Request::create('PATCH', '/api/usuarios/' . $recId . '/estado', [
        'estado' => 'activo',
    ]), ['id' => (string) $recId]);
    comprobar('activar usuario funciona', cuerpo($respuesta)['data']['activo'] === true);

    comprobar('no permite autodesactivarse', codigoDeError(
        fn () => $usuarioCtrl->estado(Request::create('PATCH', '/api/usuarios/2/estado', [
            'activo' => false,
        ]), ['id' => '2'])
    ) === 'SELF_DEACTIVATION');

    $medicoConCitas = $pdo->query(
        "SELECT m.id
         FROM medicos m
         WHERE m.estado = 'activo'
           AND EXISTS (
               SELECT 1
               FROM citas c
               WHERE c.medico_id = m.id
                 AND c.fecha >= CURRENT_DATE
                 AND c.estado IN ('programada', 'confirmada')
           )
         LIMIT 1"
    )->fetchColumn();

    if ($medicoConCitas !== false) {
        comprobar('no desactiva un médico con citas futuras', codigoDeError(
            fn () => $medicoCtrl->estado(
                Request::create('PATCH', '/api/medicos/' . (int) $medicoConCitas . '/estado', [
                    'estado' => 'inactivo',
                ]),
                ['id' => (string) $medicoConCitas]
            )
        ) === 'FUTURE_APPOINTMENTS');
    }

    // Especialidades -----------------------------------------------------------
    $respuesta = $especialidadCtrl->store(Request::create('POST', '/api/especialidades', [
        'nombre' => 'Testadmin ' . $sufijo,
        'descripcion' => 'Especialidad temporal de prueba.',
        'sexo_permitido' => 'ambos',
        'edad_min' => 0,
        'edad_max' => 99,
        'destacada' => true,
        'icono' => 'heart',
        'estado' => 'activo',
    ]));
    $datos = cuerpo($respuesta);
    $espId = (int) ($datos['data']['id'] ?? 0);
    comprobar('crear especialidad devuelve 201', $respuesta->status() === 201 && $espId > 0);

    comprobar('especialidad duplicada devuelve DUPLICATE_NAME', codigoDeError(
        fn () => $especialidadCtrl->store(Request::create('POST', '/api/especialidades', [
            'nombre' => 'Testadmin ' . $sufijo,
            'sexo_permitido' => 'ambos',
            'destacada' => false,
            'estado' => 'activo',
        ]))
    ) === 'DUPLICATE_NAME');

    $respuesta = $especialidadCtrl->update(Request::create('PUT', '/api/especialidades/' . $espId, [
        'destacada' => false,
        'edad_min' => 18,
    ]), ['id' => (string) $espId]);
    comprobar('editar especialidad actualiza sus campos', cuerpo($respuesta)['data']['destacada'] === false
        && (int) cuerpo($respuesta)['data']['edad_min'] === 18);

    $respuesta = $especialidadCtrl->estado(Request::create('PATCH', '/api/especialidades/' . $espId . '/estado', [
        'estado' => 'inactivo',
    ]), ['id' => (string) $espId]);
    comprobar('desactivar especialidad funciona', cuerpo($respuesta)['data']['estado'] === 'inactivo');
    $especialidadCtrl->estado(Request::create('PATCH', '/api/especialidades/' . $espId . '/estado', [
        'estado' => 'activo',
    ]), ['id' => (string) $espId]);

    // Médicos ------------------------------------------------------------------
    $respuesta = $medicoCtrl->store(Request::create('POST', '/api/medicos', [
        'registro_profesional' => $registro,
        'nombres' => 'Test',
        'apellidos' => 'Admin ' . $sufijo,
        'telefono' => '70000000',
        'email' => $prefijo . '.med@example.test',
        'duracion_cita_min' => 30,
        'especialidades' => [$espId],
        'crear_usuario' => true,
        'username' => $prefijo . '.med',
        'password' => 'ClaveSegura1',
    ]));
    $datos = cuerpo($respuesta);
    $medId = (int) ($datos['data']['id'] ?? 0);
    $medUsuarioId = (int) ($datos['data']['usuario_id'] ?? 0);
    comprobar('crear médico con usuario devuelve 201', $respuesta->status() === 201
        && $medId > 0 && $medUsuarioId > 0);
    comprobar('médico creado incluye su especialidad', in_array(
        $espId,
        array_map(static fn (array $e): int => (int) $e['id'], $datos['data']['especialidades'] ?? []),
        true
    ));

    comprobar('registro profesional duplicado devuelve DUPLICATE_REGISTRO', codigoDeError(
        fn () => $medicoCtrl->store(Request::create('POST', '/api/medicos', [
            'registro_profesional' => $registro,
            'nombres' => 'Otro',
            'apellidos' => 'Médico',
            'duracion_cita_min' => 30,
            'especialidades' => [$espId],
            'crear_usuario' => false,
        ]))
    ) === 'DUPLICATE_REGISTRO');

    comprobar('médico sin especialidad devuelve VALIDATION_ERROR', codigoDeError(
        fn () => $medicoCtrl->store(Request::create('POST', '/api/medicos', [
            'registro_profesional' => $registro . '-2',
            'nombres' => 'Otro',
            'apellidos' => 'Médico',
            'duracion_cita_min' => 30,
            'especialidades' => [],
            'crear_usuario' => false,
        ]))
    ) === 'VALIDATION_ERROR');

    $respuesta = $medicoCtrl->update(Request::create('PUT', '/api/medicos/' . $medId, [
        'telefono' => '72222222',
        'duracion_cita_min' => 30,
    ]), ['id' => (string) $medId]);
    comprobar('editar médico actualiza contacto y duración', cuerpo($respuesta)['data']['telefono'] === '72222222');

    $respuesta = $medicoCtrl->recepcionista(
        Request::create('POST', '/api/medicos/' . $medId . '/recepcionistas', [
            'usuario_id' => $recId,
        ]),
        ['id' => (string) $medId]
    );
    $recepcionistas = cuerpo($respuesta)['data'] ?? [];
    comprobar('asignar recepcionista devuelve la asignación', in_array(
        $recId,
        array_map(static fn (array $r): int => (int) $r['id'], $recepcionistas),
        true
    ));

    // Horarios y bloqueos ------------------------------------------------------
    $respuesta = $horarioCtrl->store(Request::create('POST', '/api/medicos/' . $medId . '/horarios', [
        'dia_semana' => 6,
        'hora_inicio' => '08:00',
        'hora_fin' => '10:00',
        'cupos_web' => 2,
        'cupos_kiosco' => 2,
        'activo' => true,
    ]), ['medico' => (string) $medId]);
    $datos = cuerpo($respuesta);
    $horarioId = (int) ($datos['data']['id'] ?? 0);
    comprobar('crear franja devuelve 201', $respuesta->status() === 201 && $horarioId > 0);

    comprobar('franja solapada devuelve OVERLAP', codigoDeError(
        fn () => $horarioCtrl->store(Request::create('POST', '/api/medicos/' . $medId . '/horarios', [
            'dia_semana' => 6,
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'cupos_web' => 2,
            'cupos_kiosco' => 2,
            'activo' => true,
        ]), ['medico' => (string) $medId])
    ) === 'OVERLAP');

    comprobar('cupos mayores a los espacios devuelven VALIDATION_ERROR', codigoDeError(
        fn () => $horarioCtrl->store(Request::create('POST', '/api/medicos/' . $medId . '/horarios', [
            'dia_semana' => 6,
            'hora_inicio' => '11:00',
            'hora_fin' => '13:00',
            'cupos_web' => 5,
            'cupos_kiosco' => 5,
            'activo' => true,
        ]), ['medico' => (string) $medId])
    ) === 'VALIDATION_ERROR');

    $respuesta = $horarioCtrl->update(Request::create('PUT', '/api/horarios/' . $horarioId, [
        'cupos_web' => 3,
        'cupos_kiosco' => 1,
    ]), ['id' => (string) $horarioId]);
    comprobar('editar cupos de una franja funciona', (int) cuerpo($respuesta)['data']['cupos_web'] === 3);

    comprobar('editar con cupos excedidos devuelve VALIDATION_ERROR', codigoDeError(
        fn () => $horarioCtrl->update(Request::create('PUT', '/api/horarios/' . $horarioId, [
            'cupos_web' => 3,
            'cupos_kiosco' => 2,
        ]), ['id' => (string) $horarioId])
    ) === 'VALIDATION_ERROR');

    $respuesta = $horarioCtrl->storeBloqueo(
        Request::create('POST', '/api/medicos/' . $medId . '/bloqueos', [
            'fecha' => $fecha,
            'hora_inicio' => '09:00',
            'hora_fin' => '10:00',
            'motivo' => 'Prueba administrativa',
        ]),
        ['medico' => (string) $medId]
    );
    $datos = cuerpo($respuesta);
    $bloqueoId = (int) ($datos['data']['id'] ?? 0);
    comprobar('crear bloqueo devuelve 201', $respuesta->status() === 201 && $bloqueoId > 0);

    $respuesta = $disponibilidadCtrl->show(peticionConQuery([
        'medico' => $medId,
        'fecha' => $fecha,
        'canal' => 'web',
    ]));
    $slots = cuerpo($respuesta)['data']['slots'] ?? [];
    comprobar('disponibilidad devuelve slots y aplica el bloqueo', $slots !== []
        && in_array(false, array_column($slots, 'disponible'), true));
    comprobar('disponibilidad incluye cupos_libres', array_key_exists('cupos_libres', $slots[0] ?? []));

    $respuesta = $horarioCtrl->destroy(
        Request::create('DELETE', '/api/horarios/' . $horarioId),
        ['id' => (string) $horarioId]
    );
    comprobar('desactivar franja devuelve 204', $respuesta->status() === 204);
    comprobar('franja desactivada queda inactiva', $horarios->findById($horarioId)['activo'] === false);

    // Permisos del médico ------------------------------------------------------
    $medicoSesion = [
        'id' => $medUsuarioId,
        'rol' => 'medico',
        'nombre' => 'Dr. Test Admin',
        'medico_id' => $medId,
        'medico_ids' => [$medId],
        'paciente_id' => null,
    ];
    cambiarSesion($medicoSesion);

    $respuesta = $medicoCtrl->show(Request::create('GET', '/api/medicos/' . $medId), ['id' => (string) $medId]);
    comprobar('el médico puede ver su propio detalle', $respuesta->status() === 200);

    comprobar('el médico no puede ver otro médico', codigoDeError(
        fn () => $medicoCtrl->show(Request::create('GET', '/api/medicos/7'), ['id' => '7'])
    ) === 'FORBIDDEN');

    $respuesta = $medicoCtrl->update(Request::create('PUT', '/api/medicos/' . $medId, [
        'telefono' => '73333333',
    ]), ['id' => (string) $medId]);
    comprobar('el médico puede editar su contacto', cuerpo($respuesta)['data']['telefono'] === '73333333');

    comprobar('el médico no puede editar su registro', codigoDeError(
        fn () => $medicoCtrl->update(Request::create('PUT', '/api/medicos/' . $medId, [
            'registro_profesional' => 'HACK-001',
        ]), ['id' => (string) $medId])
    ) === 'FORBIDDEN');

    comprobar('subir QR sin archivo devuelve VALIDATION_ERROR', codigoDeError(
        fn () => $medicoCtrl->qr(Request::create('POST', '/api/medicos/' . $medId . '/qr'), ['id' => (string) $medId])
    ) === 'VALIDATION_ERROR');

    $respuesta = $disponibilidadCtrl->show(peticionConQuery([
        'medico' => $medId,
        'fecha' => $fecha,
        'canal' => 'web',
    ]));
    comprobar('el médico puede consultar su disponibilidad', $respuesta->status() === 200);

    comprobar('el médico no puede consultar otra disponibilidad', codigoDeError(
        fn () => $disponibilidadCtrl->show(peticionConQuery([
            'medico' => 7,
            'fecha' => $fecha,
            'canal' => 'web',
        ]))
    ) === 'FORBIDDEN');

    cambiarSesion($admin);

    comprobar('el admin no puede subir el QR de un médico', codigoDeError(
        fn () => $medicoCtrl->qr(Request::create('POST', '/api/medicos/' . $medId . '/qr'), ['id' => (string) $medId])
    ) === 'FORBIDDEN');

    // Rutas y CSRF -------------------------------------------------------------
    $router = new Router();

    foreach (glob(base_path('app/routes/*.php')) ?: [] as $archivoRutas) {
        require $archivoRutas;
    }

    $respuesta = $router->dispatch(Request::create('GET', '/api/usuarios'));
    comprobar('la ruta GET /api/usuarios está registrada', $respuesta->status() === 200);

    comprobar('las escrituras sin CSRF se rechazan', codigoDeError(
        fn () => $router->dispatch(Request::create('POST', '/api/usuarios', [
            'username' => $prefijo . '.csrf',
            'email' => $prefijo . '.csrf@example.test',
            'password' => 'ClaveSegura1',
            'rol' => 'recepcionista',
        ]))
    ) === 'CSRF_TOKEN_MISMATCH');
} catch (Throwable $exception) {
    $fallos++;
    echo 'FALLO excepción no controlada: ' . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
} finally {
    DB::clearContext();
    $_SESSION = [];

    $pdo->beginTransaction();

    try {
        $pdo->prepare("DELETE FROM recepcionista_medico
                       WHERE usuario_id IN (SELECT id FROM usuarios WHERE username LIKE :prefijo)
                          OR medico_id IN (SELECT id FROM medicos WHERE registro_profesional LIKE :registro)")
            ->execute(['prefijo' => $prefijo . '%', 'registro' => 'TESTADMIN-' . $sufijo . '%']);
        $pdo->prepare("DELETE FROM medico_especialidad
                       WHERE medico_id IN (SELECT id FROM medicos WHERE registro_profesional LIKE :registro)")
            ->execute(['registro' => 'TESTADMIN-' . $sufijo . '%']);
        $pdo->prepare("DELETE FROM horarios
                       WHERE medico_id IN (SELECT id FROM medicos WHERE registro_profesional LIKE :registro)")
            ->execute(['registro' => 'TESTADMIN-' . $sufijo . '%']);
        $pdo->prepare("DELETE FROM bloqueos
                       WHERE medico_id IN (SELECT id FROM medicos WHERE registro_profesional LIKE :registro)")
            ->execute(['registro' => 'TESTADMIN-' . $sufijo . '%']);
        $pdo->prepare("DELETE FROM medicos WHERE registro_profesional LIKE :registro")
            ->execute(['registro' => 'TESTADMIN-' . $sufijo . '%']);
        $pdo->prepare("DELETE FROM usuarios WHERE username LIKE :prefijo")
            ->execute(['prefijo' => $prefijo . '%']);
        $pdo->prepare("DELETE FROM especialidades WHERE nombre LIKE :nombre")
            ->execute(['nombre' => 'Testadmin ' . $sufijo . '%']);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $fallos++;
        echo 'FALLO no se pudo limpiar por completo: ' . $exception->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas admin pasaron.' : $fallos . ' prueba(s) admin fallaron.') . PHP_EOL;

exit($fallos === 0 ? 0 : 1);
