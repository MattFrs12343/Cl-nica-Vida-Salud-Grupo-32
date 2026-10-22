#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;
use App\Models\UsuarioModel;
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
    }

    return null;
}

$pdo = DB::connection();
$auth = new AuthService();
$usuarios = new UsuarioModel();

$pdo->beginTransaction();

try {
    $credenciales = [
        'superadmin' => ['rol' => 'super_admin', 'home' => '/admin'],
        'admin' => ['rol' => 'admin', 'home' => '/admin'],
        'recepcion' => ['rol' => 'recepcionista', 'home' => '/recepcion'],
        'recepcion.medico' => ['rol' => 'recepcionista_medico', 'home' => '/recepcion'],
        'medico' => ['rol' => 'medico', 'home' => '/medico'],
        '12345678' => ['rol' => 'paciente', 'home' => '/portal'],
    ];

    foreach ($credenciales as $identificador => $esperado) {
        $usuario = $auth->login((string) $identificador, 'Demo1234!');

        comprobar(
            'login ' . $identificador . ' → ' . $esperado['rol'],
            $usuario['rol'] === $esperado['rol']
        );
        comprobar(
            'redirect ' . $identificador,
            role_home((string) $usuario['rol']) === $esperado['home']
        );
    }

    $recepcion = $pdo->query(
        "SELECT * FROM usuarios WHERE username = 'recepcion' LIMIT 1"
    )->fetch();

    comprobar(
        'credenciales inválidas',
        codigoDeError(fn () => $auth->login('recepcion', 'incorrecta')) === 'INVALID_CREDENTIALS'
    );
    comprobar(
        'segundo intento inválido',
        codigoDeError(fn () => $auth->login('recepcion', 'incorrecta')) === 'INVALID_CREDENTIALS'
    );
    comprobar(
        'tercer intento bloquea la cuenta',
        codigoDeError(fn () => $auth->login('recepcion', 'incorrecta')) === 'ACCOUNT_LOCKED'
    );
    comprobar(
        'cuenta bloqueada rechaza clave correcta',
        codigoDeError(fn () => $auth->login('recepcion', 'Demo1234!')) === 'ACCOUNT_LOCKED'
    );

    $usuarios->desbloquear((int) $recepcion['id']);
    comprobar(
        'desbloqueo permite iniciar sesión',
        $auth->login('recepcion', 'Demo1234!')['rol'] === 'recepcionista'
    );

    $admin = $pdo->query(
        "SELECT * FROM usuarios WHERE username = 'admin' LIMIT 1"
    )->fetch();
    $usuarios->guardarTokenReset((int) $admin['id'], 'token-expirado-test', 30);
    $pdo->exec(
        "UPDATE usuarios SET token_reset_expira = now() - INTERVAL '1 minute'
         WHERE id = " . (int) $admin['id']
    );
    comprobar(
        'token de reset expirado',
        codigoDeError(
            fn () => $auth->resetPassword('token-expirado-test', 'NuevaClave1', 'NuevaClave1')
        ) === 'TOKEN_INVALID'
    );

    $_SESSION = [
        'usuario' => [
            'id' => 2,
            'rol' => 'admin',
            'nombre' => 'Admin',
            'medico_id' => null,
            'medico_ids' => [],
            'paciente_id' => null,
        ],
    ];
    $requestHtml = Request::create('GET', '/admin');
    $roleMiddleware = new RoleMiddleware('medico');
    $respuestaRol = $roleMiddleware->handle(
        $requestHtml,
        static fn (): Response => Response::html('permitido')
    );

    comprobar('RoleMiddleware redirige a admin', $respuestaRol->status() === 302);
    comprobar(
        'RoleMiddleware usa home del rol',
        str_ends_with((string) ($respuestaRol->headers()['Location'] ?? ''), '/admin')
    );

    $requestJson = Request::create('GET', '/api/admin', [], ['accept' => 'application/json']);
    $respuestaJson = $roleMiddleware->handle(
        $requestJson,
        static fn (): Response => Response::html('permitido')
    );
    comprobar('RoleMiddleware responde 403 JSON', $respuestaJson->status() === 403);

    $_SESSION = [];
    $requestLogin = Request::create('GET', '/medico');
    $authMiddleware = new AuthMiddleware();
    $respuestaAuth = $authMiddleware->handle(
        $requestLogin,
        static fn (): Response => Response::html('permitido')
    );
    comprobar('AuthMiddleware redirige a /login', $respuestaAuth->status() === 302);
    comprobar(
        'AuthMiddleware usa /login',
        str_ends_with((string) ($respuestaAuth->headers()['Location'] ?? ''), '/login')
    );

    $respuestaAuthJson = $authMiddleware->handle(
        Request::create('GET', '/api/medico', [], ['accept' => 'application/json']),
        static fn (): Response => Response::html('permitido')
    );
    comprobar('AuthMiddleware responde 401 JSON', $respuestaAuthJson->status() === 401);

    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

// Activación real, fuera de la transacción anterior porque AuthService usa
// su propia transacción. Se crea un paciente temporal y se limpia al final.
$pdo->beginTransaction();

try {
    $pdo->exec(
        "INSERT INTO pacientes (codigo, ci, nombres, apellidos, fecha_nacimiento, sexo, telefono)
         VALUES ('PAC-TEST-AUTH', '99999998', 'Prueba', 'Activación', '1990-01-01', 'masculino', '70099998')"
    );
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'ERROR preparando activación: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

try {
    comprobar(
        'activación con teléfono incorrecto',
        codigoDeError(
            fn () => $auth->activarPaciente('99999998', '70000000', 'Prueba123', 'Prueba123')
        ) === 'ACTIVATION_NOT_FOUND'
    );

    $resultado = $auth->activarPaciente(
        '99999998',
        '70099998',
        'Prueba123',
        'Prueba123',
        'activacion.test@example.test'
    );
    comprobar('activación de paciente', ($resultado['redirect'] ?? '') === '/login');
    comprobar(
        'login tras activación',
        $auth->login('99999998', 'Prueba123')['rol'] === 'paciente'
    );
} catch (Throwable $exception) {
    comprobar('flujo de activación sin excepciones', false);
    fwrite(STDERR, 'ERROR activación: ' . $exception->getMessage() . PHP_EOL);
} finally {
    $pdo->beginTransaction();

    try {
        $pdo->exec("DELETE FROM pacientes WHERE codigo = 'PAC-TEST-AUTH'");
        $pdo->exec("DELETE FROM usuarios WHERE username = '99999998'");
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, 'ERROR limpiando activación: ' . $exception->getMessage() . PHP_EOL);
    }
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
