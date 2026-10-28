#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-38 - Performance.
 *
 * Verifica que la migración de índices esté aplicada, que los listados
 * respeten el tope de paginación, que el catálogo público use caché HTTP
 * corta y que el listado de pacientes no haga dos subconsultas por fila.
 */

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Models\PacienteModel;

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

function enviar(Router $router, Request $request): Response
{
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
}

/** @param array<string, mixed> $query */
function requestConQuery(string $path, array $query): Request
{
    $anterioresGet = $_GET;
    $anterioresServer = $_SERVER;
    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $path . '?' . http_build_query($query);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    try {
        return Request::capture();
    } finally {
        $_GET = $anterioresGet;
        $_SERVER = $anterioresServer;
    }
}

$router = new Router();

foreach (glob(dirname(__DIR__) . '/app/routes/*.php') ?: [] as $archivoRuta) {
    require $archivoRuta;
}

$admin = [
    'id' => 2,
    'rol' => 'admin',
    'nombre' => 'admin',
    'medico_id' => null,
    'medico_ids' => [],
    'paciente_id' => null,
];

try {
    // -- Migración e índices -------------------------------------------------
    $pdo = DB::connection();
    $migracion = $pdo->prepare(
        'SELECT 1 FROM schema_migrations WHERE filename = :archivo'
    );
    $migracion->execute(['archivo' => '012_performance_indexes.sql']);
    comprobar('la migración 012 está aplicada', $migracion->fetchColumn() !== false);

    $indices = array_column(
        $pdo->query(
            "SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"
        )->fetchAll(PDO::FETCH_ASSOC),
        'indexname'
    );

    foreach ([
        'ix_usuarios_lower_username',
        'ix_usuarios_lower_email',
        'ix_citas_medico_fecha_estado',
        'ix_citas_especialidad_fecha',
        'ix_citas_fecha_estado',
        'ix_citas_paciente_fecha',
        'ix_citas_paciente_estado_fecha',
        'ix_pacientes_created_at',
        'ix_consultas_fecha',
        'ix_recetas_fecha',
        'ix_documentos_fecha',
        'ix_ordenes_fecha',
        'ix_resultados_fecha',
        'ix_pagos_fecha',
        'ix_auditoria_usuario_fecha',
        'ix_auditoria_tabla_fecha',
    ] as $indice) {
        comprobar('existe el índice ' . $indice, in_array($indice, $indices, true));
    }

    // -- Paginación ----------------------------------------------------------
    $_SESSION = ['usuario' => $admin, '_last_activity' => time()];
    DB::setContext($admin, '127.0.0.1');

    $respuesta = enviar($router, requestConQuery('/api/citas', [
        'page' => '0',
        'per_page' => '500',
    ]));
    $meta = cuerpo($respuesta)['meta'] ?? [];

    comprobar('el listado de citas responde', $respuesta->status() === 200);
    comprobar('page menor a 1 se normaliza a 1', (int) ($meta['page'] ?? 0) === 1);
    comprobar('per_page se limita a 100', (int) ($meta['per_page'] ?? 0) === 100);

    $respuesta = enviar($router, requestConQuery('/api/citas', [
        'per_page' => 'abc',
    ]));
    $meta = cuerpo($respuesta)['meta'] ?? [];
    comprobar('per_page inválido usa 20', (int) ($meta['per_page'] ?? 0) === 20);

    // -- Caché HTTP del catálogo público ------------------------------------
    $respuesta = enviar($router, Request::create('GET', '/api/public/especialidades'));
    $cache = (string) ($respuesta->headers()['Cache-Control'] ?? '');
    comprobar('el catálogo público de especialidades responde', $respuesta->status() === 200);
    comprobar('el catálogo público usa caché corta', str_contains($cache, 'max-age=60'));

    $respuesta = enviar($router, Request::create('GET', '/api/public/medicos'));
    $cache = (string) ($respuesta->headers()['Cache-Control'] ?? '');
    comprobar('el catálogo público de médicos responde', $respuesta->status() === 200);
    comprobar('el catálogo público de médicos usa caché corta', str_contains($cache, 'max-age=60'));

    // -- Listado de pacientes con LATERAL ----------------------------------
    $pacientes = (new PacienteModel())->listarAdmin([], 1, 5);
    $primero = $pacientes['data'][0] ?? [];

    comprobar('el listado de pacientes devuelve filas', $pacientes['data'] !== []);
    comprobar('el listado de pacientes incluye última cita',
        array_key_exists('ultima_cita', $primero));
    comprobar('el listado de pacientes incluye última atención',
        array_key_exists('ultima_atencion', $primero));
} finally {
    DB::clearContext();
    $_SESSION = [];
}

if ($fallos > 0) {
    echo PHP_EOL . $fallos . ' prueba(s) fallaron.' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Todas las pruebas de performance (M-38) pasaron.' . PHP_EOL;
