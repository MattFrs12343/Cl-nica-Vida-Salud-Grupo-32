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

function cerrarSesion(): void
{
    unset($_SESSION['usuario']);
    DB::clearContext();
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

/** @return array<string, mixed>|null */
function fila(PDO $pdo, string $sql, array $parametros = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parametros);
    $resultado = $statement->fetch();

    return is_array($resultado) ? $resultado : null;
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

/** @return array{id: int, codigo: string} */
function crearConsultaDirecta(PDO $pdo, int $citaId, int $pacienteId, int $medicoId): array
{
    $statement = $pdo->prepare(
        "INSERT INTO consultas (
             cita_id, paciente_id, medico_id, motivo, diagnostico
         ) VALUES (
             :cita, :paciente, :medico, 'Interconsulta de prueba', 'Diagnóstico de prueba'
         )
         RETURNING id"
    );
    $statement->execute(['cita' => $citaId, 'paciente' => $pacienteId, 'medico' => $medicoId]);

    return ['id' => (int) $statement->fetchColumn(), 'codigo' => ''];
}

/** @return array{id: int, codigo: string, codigo_acceso: string} */
function crearRecetaDirecta(PDO $pdo, int $consultaId, int $pacienteId, int $medicoId): array
{
    $statement = $pdo->prepare(
        "INSERT INTO recetas (consulta_id, paciente_id, medico_id, codigo_acceso)
         VALUES (:consulta, :paciente, :medico, '4321')
         RETURNING id, codigo, codigo_acceso"
    );
    $statement->execute(['consulta' => $consultaId, 'paciente' => $pacienteId, 'medico' => $medicoId]);
    $fila = $statement->fetch();

    return [
        'id' => (int) $fila['id'],
        'codigo' => (string) $fila['codigo'],
        'codigo_acceso' => (string) $fila['codigo_acceso'],
    ];
}

/** @return array{id: int, codigo: string, codigo_acceso: string} */
function crearDocumentoDirecto(PDO $pdo, int $consultaId, int $pacienteId, int $medicoId): array
{
    $statement = $pdo->prepare(
        "INSERT INTO documentos (consulta_id, paciente_id, medico_id, tipo, contenido, codigo_acceso)
         VALUES (:consulta, :paciente, :medico, 'informe', 'Informe de control.', '5678')
         RETURNING id, codigo, codigo_acceso"
    );
    $statement->execute(['consulta' => $consultaId, 'paciente' => $pacienteId, 'medico' => $medicoId]);
    $fila = $statement->fetch();

    return [
        'id' => (int) $fila['id'],
        'codigo' => (string) $fila['codigo'],
        'codigo_acceso' => (string) $fila['codigo_acceso'],
    ];
}

function vincularPacienteUsuario(PDO $pdo, int $pacienteId, string $username): int
{
    $statement = $pdo->prepare(
        "INSERT INTO usuarios (username, email, password_hash, rol)
         VALUES (:username, :email, :password_hash, 'paciente')
         RETURNING id"
    );
    $statement->execute([
        'username' => $username,
        'email' => $username . '@vidasalud.test',
        'password_hash' => password_hash('Demo1234!', PASSWORD_DEFAULT),
    ]);
    $usuarioId = (int) $statement->fetchColumn();

    $pdo->prepare('UPDATE pacientes SET usuario_id = :usuario_id WHERE id = :id')
        ->execute(['usuario_id' => $usuarioId, 'id' => $pacienteId]);

    return $usuarioId;
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

$medicoDb = fila(
    $pdo,
    "SELECT m.id,
            (SELECT me.especialidad_id FROM medico_especialidad me
             WHERE me.medico_id = m.id ORDER BY me.especialidad_id LIMIT 1) AS especialidad_id
     FROM medicos m WHERE m.codigo = 'MED-DEMO-001' LIMIT 1"
);
$otroMedicoDb = fila(
    $pdo,
    "SELECT m.id,
            (SELECT me.especialidad_id FROM medico_especialidad me
             WHERE me.medico_id = m.id ORDER BY me.especialidad_id LIMIT 1) AS especialidad_id
     FROM medicos m WHERE m.id <> :medico_id AND m.estado = 'activo' ORDER BY m.id LIMIT 1",
    ['medico_id' => (int) ($medicoDb['id'] ?? 0)]
);

$paciente1 = fila($pdo, "SELECT id, telefono FROM pacientes WHERE codigo = 'PAC-DEMO-001' LIMIT 1");
$paciente2 = fila($pdo, "SELECT id, telefono FROM pacientes WHERE codigo = 'PAC-DEMO-002' LIMIT 1");
$paciente3 = fila($pdo, "SELECT id, telefono FROM pacientes WHERE codigo = 'PAC-DEMO-003' LIMIT 1");

$usuarioAdmin = fila($pdo, "SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1");
$usuarioRecepcionMedico = fila($pdo, "SELECT id FROM usuarios WHERE username = 'recepcion.medico' LIMIT 1");
$usuarioMedico = fila($pdo, "SELECT id FROM usuarios WHERE username = 'medico' LIMIT 1");
$usuarioPaciente1 = fila($pdo, "SELECT id FROM usuarios WHERE username = '12345678' LIMIT 1");

if (
    !is_array($medicoDb) || (int) ($medicoDb['especialidad_id'] ?? 0) <= 0
    || !is_array($otroMedicoDb) || (int) ($otroMedicoDb['especialidad_id'] ?? 0) <= 0
    || !is_array($paciente1) || !is_array($paciente2) || !is_array($paciente3)
    || !is_array($usuarioAdmin) || !is_array($usuarioRecepcionMedico)
    || !is_array($usuarioMedico) || !is_array($usuarioPaciente1)
) {
    fwrite(STDERR, 'ERROR: faltan los datos seed para las pruebas de consultas y pedidos.' . PHP_EOL);
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
    'id' => (int) $usuarioAdmin['id'], 'rol' => 'admin', 'nombre' => 'admin',
    'medico_id' => null, 'medico_ids' => [], 'paciente_id' => null,
];
$recepcionistaMedico = [
    'id' => (int) $usuarioRecepcionMedico['id'], 'rol' => 'recepcionista_medico', 'nombre' => 'recepcion.medico',
    'medico_id' => $medicoId, 'medico_ids' => [$medicoId, $otroMedicoId], 'paciente_id' => null,
];
$medico = [
    'id' => (int) $usuarioMedico['id'], 'rol' => 'medico', 'nombre' => 'Dr. Carlos Méndez',
    'medico_id' => $medicoId, 'medico_ids' => [$medicoId], 'paciente_id' => null,
];
$paciente1Sesion = [
    'id' => (int) $usuarioPaciente1['id'], 'rol' => 'paciente', 'nombre' => 'Juan Pérez',
    'medico_id' => null, 'medico_ids' => [], 'paciente_id' => $paciente1Id,
];

$pdo->beginTransaction();

try {
    DB::clearContext();

    $usuarioPaciente3Id = vincularPacienteUsuario($pdo, $paciente3Id, 'paciente3-test-m31');
    $paciente3Sesion = [
        'id' => $usuarioPaciente3Id, 'rol' => 'paciente', 'nombre' => 'Luis Niño',
        'medico_id' => null, 'medico_ids' => [], 'paciente_id' => $paciente3Id,
    ];

    $citaConfirmada = crearCita($pdo, $paciente1Id, $medicoId, $especialidadId, '2099-11-01', '08:00', '08:30', 'confirmada');
    $citaAtendida = crearCita($pdo, $paciente1Id, $medicoId, $especialidadId, '2099-11-01', '09:00', '09:30', 'atendida');
    $citaProgramada = crearCita($pdo, $paciente1Id, $medicoId, $especialidadId, '2099-11-01', '10:00', '10:30', 'programada');
    $citaOtroMedico = crearCita($pdo, $paciente1Id, $otroMedicoId, $otraEspecialidadId, '2099-11-01', '08:00', '08:30', 'confirmada');
    $citaSinTelefono = crearCita($pdo, $paciente2Id, $medicoId, $especialidadId, '2099-11-02', '08:00', '08:30', 'atendida');

    $citaConsultaOtroMedico = crearCita($pdo, $paciente1Id, $otroMedicoId, $otraEspecialidadId, '2099-11-03', '08:00', '08:30', 'atendida');
    $consultaOtroMedico = crearConsultaDirecta($pdo, $citaConsultaOtroMedico, $paciente1Id, $otroMedicoId);
    $recetaOtroMedico = crearRecetaDirecta($pdo, $consultaOtroMedico['id'], $paciente1Id, $otroMedicoId);

    $pdo->prepare('UPDATE pacientes SET telefono = NULL WHERE id = :id')->execute(['id' => $paciente2Id]);
    $consultaSinTelefono = crearConsultaDirecta($pdo, $citaSinTelefono, $paciente2Id, $medicoId);
    $documentoSinTelefono = crearDocumentoDirecto($pdo, $consultaSinTelefono['id'], $paciente2Id, $medicoId);

    comprobar(
        'se preparan citas y fixtures de prueba',
        $citaConfirmada > 0 && $citaAtendida > 0 && $citaProgramada > 0 && $citaOtroMedico > 0
        && $citaSinTelefono > 0 && $consultaOtroMedico['id'] > 0 && $recetaOtroMedico['id'] > 0
        && $documentoSinTelefono['id'] > 0
    );

    // -- Consultas ----------------------------------------------------------
    cambiarSesion($medico);

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaConfirmada}/consulta", [], ['diagnostico' => 'Algo']));
    comprobar(
        'la consulta exige motivo',
        $respuesta->status() === 422 && isset(cuerpo($respuesta)['error']['fields']['motivo'])
    );

    $payloadConsulta = [
        'motivo' => 'Dolor de cabeza',
        'anamnesis' => 'Paciente refiere cefalea desde hace 3 días.',
        'signos_vitales' => ['pa' => '120/80', 'fc' => 72, 'temp' => 36.5, 'peso' => 68, 'spo2' => 98, 'talla' => 1.65],
        'examen_fisico' => 'Sin signos de alarma.',
        'diagnostico' => 'Cefalea tensional.',
        'tratamiento' => 'Paracetamol 500 mg cada 8 h por 3 días.',
        'indicaciones' => 'Hidratación y reposo.',
    ];

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaConfirmada}/consulta", [], $payloadConsulta));
    $datosConsulta = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la consulta se registra y la cita confirmada pasa a atendida',
        $respuesta->status() === 201
        && ($datosConsulta['motivo'] ?? '') === 'Dolor de cabeza'
        && ($datosConsulta['signos_vitales']['pa'] ?? '') === '120/80'
        && ($datosConsulta['cita']['estado'] ?? '') === 'atendida'
    );

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaConfirmada}/consulta", [], $payloadConsulta));
    comprobar(
        'una cita no admite dos consultas',
        $respuesta->status() === 409 && (cuerpo($respuesta)['error']['code'] ?? '') === 'CONFLICT'
    );

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaProgramada}/consulta", [], $payloadConsulta));
    comprobar(
        'no se registra consulta sobre una cita programada',
        $respuesta->status() === 409 && (cuerpo($respuesta)['error']['code'] ?? '') === 'INVALID_STATE'
    );

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaOtroMedico}/consulta", [], $payloadConsulta));
    comprobar(
        'el médico no registra consultas de citas ajenas',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('POST', "/api/citas/{$citaAtendida}/consulta", [], $payloadConsulta));
    $consulta2 = cuerpo($respuesta)['data'] ?? [];
    $consultaId = (int) ($consulta2['id'] ?? 0);
    comprobar(
        'se registra la consulta sobre una cita ya atendida sin re-transicionar',
        $respuesta->status() === 201 && ($consulta2['cita']['estado'] ?? '') === 'atendida' && $consultaId > 0
    );

    $respuesta = $enviar(solicitud('GET', "/api/consultas/{$consultaId}"));
    comprobar('el médico lee su propia consulta', $respuesta->status() === 200);

    cambiarSesion($paciente1Sesion);
    $respuesta = $enviar(solicitud('GET', "/api/consultas/{$consultaId}"));
    comprobar('el paciente dueño lee su consulta', $respuesta->status() === 200);

    cambiarSesion($paciente3Sesion);
    $respuesta = $enviar(solicitud('GET', "/api/consultas/{$consultaId}"));
    comprobar('un paciente ajeno no ve la consulta', $respuesta->status() === 404);

    cambiarSesion($admin);
    $respuesta = $enviar(solicitud('GET', "/api/consultas/{$consultaId}"));
    comprobar('admin no accede a la consulta (rol fuera de contrato)', $respuesta->status() === 403);

    // -- Recetas --------------------------------------------------------------
    cambiarSesion($medico);

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaOtroMedico['id']}/recetas", [], [
        'detalle' => [['medicamento' => 'Paracetamol 500 mg']],
    ]));
    comprobar(
        'el médico no crea recetas en consultas de otro médico',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/recetas", [], ['detalle' => []]));
    comprobar(
        'la receta exige al menos un medicamento',
        $respuesta->status() === 422 && isset(cuerpo($respuesta)['error']['fields']['detalle'])
    );

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/recetas", [], [
        'detalle' => [
            ['medicamento' => 'Paracetamol 500 mg', 'dosis' => '1 tableta', 'via' => 'oral', 'frecuencia' => 'cada 8 horas', 'duracion' => '3 días', 'instrucciones' => 'Después de comer'],
            ['medicamento' => 'Ibuprofeno 400 mg'],
        ],
    ]));
    $receta = cuerpo($respuesta)['data'] ?? [];
    $recetaId = (int) ($receta['id'] ?? 0);
    comprobar(
        'la receta se crea con código y detalle',
        $respuesta->status() === 201
        && str_starts_with((string) ($receta['codigo'] ?? ''), 'RSV-')
        && preg_match('/^\d{4}$/', (string) ($receta['codigo_acceso'] ?? '')) === 1
        && count($receta['detalle'] ?? []) === 2
    );

    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar('el médico lee la receta', $respuesta->status() === 200);

    cambiarSesion($paciente1Sesion);
    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar('el paciente dueño lee su receta', $respuesta->status() === 200);

    cambiarSesion($paciente3Sesion);
    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar('un paciente ajeno no ve la receta', $respuesta->status() === 404);

    cambiarSesion($medico);
    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaOtroMedico['id']}"));
    comprobar('el médico ajeno no ve la receta de otro médico', $respuesta->status() === 404);

    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}/imprimir"));
    comprobar(
        'la vista imprimible de la receta responde en HTML',
        $respuesta->status() === 200 && str_contains($respuesta->body(), (string) $receta['codigo'])
    );

    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar('recepción médica no puede leer el detalle de una receta', $respuesta->status() === 403);

    // -- Documentos -----------------------------------------------------------
    cambiarSesion($medico);

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/documentos", [], ['tipo' => 'invalido']));
    comprobar(
        'el documento valida el tipo',
        $respuesta->status() === 422 && isset(cuerpo($respuesta)['error']['fields']['tipo'])
    );

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/documentos", [], [
        'tipo' => 'certificado',
        'contenido' => 'El paciente requiere reposo por 48 horas.',
    ]));
    $documento = cuerpo($respuesta)['data'] ?? [];
    $documentoId = (int) ($documento['id'] ?? 0);
    comprobar(
        'el documento se crea con código',
        $respuesta->status() === 201 && str_starts_with((string) ($documento['codigo'] ?? ''), 'DOC-')
    );

    $respuesta = $enviar(solicitud('GET', "/api/documentos/{$documentoId}/imprimir"));
    comprobar(
        'la vista imprimible del documento responde en HTML',
        $respuesta->status() === 200 && str_contains($respuesta->body(), 'reposo por 48 horas')
    );

    cambiarSesion($paciente3Sesion);
    $respuesta = $enviar(solicitud('GET', "/api/documentos/{$documentoId}/imprimir"));
    comprobar('un paciente ajeno no imprime el documento de otro', $respuesta->status() === 404);

    // -- Órdenes y resultados ---------------------------------------------------
    cambiarSesion($medico);

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/ordenes", [], ['tipo' => 'laboratorio', 'detalle' => []]));
    comprobar(
        'la orden exige al menos un estudio',
        $respuesta->status() === 422 && isset(cuerpo($respuesta)['error']['fields']['detalle'])
    );

    $respuesta = $enviar(solicitud('POST', "/api/consultas/{$consultaId}/ordenes", [], [
        'tipo' => 'laboratorio',
        'detalle' => [
            ['estudio' => 'Hemograma completo'],
            ['estudio' => 'Glicemia en ayunas', 'observaciones' => 'Ayuno de 8 horas'],
        ],
    ]));
    $orden = cuerpo($respuesta)['data'] ?? [];
    $ordenId = (int) ($orden['id'] ?? 0);
    comprobar(
        'la orden se crea con código y detalle',
        $respuesta->status() === 201
        && str_starts_with((string) ($orden['codigo'] ?? ''), 'ORD-')
        && count($orden['detalle'] ?? []) === 2
    );

    $respuesta = $enviar(solicitud('POST', "/api/ordenes/{$ordenId}/resultados", [], []));
    comprobar(
        'el resultado exige archivo o texto',
        $respuesta->status() === 422
    );

    $respuesta = $enviar(solicitud('POST', "/api/ordenes/{$ordenId}/resultados", [], [
        'texto' => 'Hemograma dentro de parámetros normales.',
    ]));
    $ordenConResultado = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el resultado de texto se registra en la orden',
        $respuesta->status() === 201
        && count($ordenConResultado['resultados'] ?? []) === 1
        && ($ordenConResultado['resultados'][0]['revisado'] ?? true) === false
    );

    $respuesta = $enviar(solicitud('GET', "/api/ordenes/{$ordenId}/imprimir"));
    comprobar(
        'la vista imprimible de la orden incluye el resultado',
        $respuesta->status() === 200
        && str_contains($respuesta->body(), 'Hemograma completo')
        && str_contains($respuesta->body(), 'dentro de parámetros normales')
    );

    // -- Envío por WhatsApp -------------------------------------------------
    $respuesta = $enviar(solicitud('POST', "/api/pedidos/receta/{$recetaId}/enviar"));
    $envio = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'el médico envía la receta por WhatsApp con el teléfono del paciente',
        $respuesta->status() === 200
        && str_starts_with((string) ($envio['whatsapp_url'] ?? ''), 'https://wa.me/591')
        && str_contains((string) ($envio['mensaje'] ?? ''), 'Código da prescrição')
    );

    $respuesta = $enviar(solicitud('GET', "/api/recetas/{$recetaId}"));
    comprobar(
        'la receta queda marcada como enviada',
        (cuerpo($respuesta)['data']['enviado_whatsapp'] ?? null) === true
    );

    $respuesta = $enviar(solicitud('POST', '/api/pedidos/algo-invalido/1/enviar'));
    comprobar('un tipo de pedido inválido devuelve 404', $respuesta->status() === 404);

    $respuesta = $enviar(solicitud('POST', "/api/pedidos/receta/{$recetaOtroMedico['id']}/enviar"));
    comprobar('el médico no envía pedidos de otro médico', $respuesta->status() === 403);

    $respuesta = $enviar(solicitud('POST', "/api/pedidos/documento/{$documentoSinTelefono['id']}/enviar"));
    comprobar(
        'no se envía un pedido si el paciente no tiene teléfono',
        $respuesta->status() === 409 && (cuerpo($respuesta)['error']['code'] ?? '') === 'NOT_ELIGIBLE'
    );

    cambiarSesion($recepcionistaMedico);
    $respuesta = $enviar(solicitud('POST', "/api/pedidos/documento/{$documentoId}/enviar"));
    comprobar(
        'recepción médica envía pedidos de sus médicos asignados',
        $respuesta->status() === 200
    );

    // -- Página pública de pedidos -------------------------------------------
    cerrarSesion();

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => $receta['codigo'], 'acceso' => $receta['codigo_acceso']], null, false));
    $pedidoPublico = cuerpo($respuesta)['data'] ?? [];
    comprobar(
        'la página pública muestra el pedido con el código de acceso correcto',
        $respuesta->status() === 200
        && ($pedidoPublico['tipo'] ?? '') === 'receta'
        && !array_key_exists('telefono', $pedidoPublico['paciente'] ?? [])
        && count($pedidoPublico['detalle'] ?? []) === 2
    );

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => $receta['codigo'], 'acceso' => '0000'], null, false));
    comprobar(
        'la página pública rechaza un código de acceso incorrecto',
        $respuesta->status() === 403
    );

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => 'RSV-999999', 'acceso' => '1234'], null, false));
    comprobar('la página pública no revela pedidos inexistentes', $respuesta->status() === 404);

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => 'XXX-000001', 'acceso' => '1234'], null, false));
    comprobar('un prefijo de código desconocido no resuelve ningún pedido', $respuesta->status() === 404);

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => $receta['codigo']], null, false));
    comprobar(
        'la página pública exige el código de acceso',
        $respuesta->status() === 422 && isset(cuerpo($respuesta)['error']['fields']['acceso'])
    );

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => $documento['codigo'], 'acceso' => $documento['codigo_acceso']], null, false));
    comprobar(
        'la página pública resuelve documentos por su prefijo DOC-',
        $respuesta->status() === 200 && (cuerpo($respuesta)['data']['tipo'] ?? '') === 'documento'
    );

    $respuesta = $enviar(solicitud('GET', '/api/public/pedido', ['codigo' => $orden['codigo'], 'acceso' => $orden['codigo_acceso']], null, false));
    comprobar(
        'la página pública resuelve órdenes por su prefijo ORD-',
        $respuesta->status() === 200 && (cuerpo($respuesta)['data']['tipo'] ?? '') === 'orden'
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

echo PHP_EOL . 'Todas las pruebas de consultas y pedidos (M-31) pasaron.' . PHP_EOL;
