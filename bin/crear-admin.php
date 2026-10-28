#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-37 - Crea el primer usuario administrador de producción.
 *
 * Uso:
 *   php bin/crear-admin.php [--super] [--dry-run] [usuario] [email] [contraseña]
 *
 * Si no se pasan datos, los pide por consola. La contraseña no se imprime.
 */

use App\Core\DB;

require dirname(__DIR__) . '/app/bootstrap.php';

function leer(string $etiqueta): string
{
    echo $etiqueta;
    $valor = fgets(STDIN);

    return $valor === false ? '' : trim($valor);
}

function leerPassword(): string
{
    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        $estado = @shell_exec('stty -g 2>/dev/null');

        if (is_string($estado) && trim($estado) !== '') {
            @shell_exec('stty -echo');
            $password = trim((string) fgets(STDIN));
            @shell_exec('stty ' . trim($estado));
            echo PHP_EOL;

            return $password;
        }
    }

    return leer('Contraseña: ');
}

$argumentos = array_slice($argv, 1);
$super = in_array('--super', $argumentos, true);
$dryRun = in_array('--dry-run', $argumentos, true);
$argumentos = array_values(array_filter(
    $argumentos,
    static fn (string $arg): bool => !str_starts_with($arg, '--')
));

$username = $argumentos[0] ?? leer('Usuario: ');
$email = $argumentos[1] ?? leer('Email: ');
$password = $argumentos[2] ?? (string) (getenv('ADMIN_PASSWORD') ?: '');

if ($password === '') {
    $password = leerPassword();
}

$errores = [];

if (preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username) !== 1) {
    $errores[] = 'El usuario debe tener 3-60 caracteres (letras, números, punto, guion o guion bajo).';
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errores[] = 'El email no es válido.';
}

if (strlen($password) < 8
    || preg_match('/[A-Za-z]/', $password) !== 1
    || preg_match('/[0-9]/', $password) !== 1) {
    $errores[] = 'La contraseña debe tener al menos 8 caracteres, una letra y un número.';
}

if ($errores !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errores) . PHP_EOL);
    exit(1);
}

try {
    $pdo = DB::connection();
    $existe = $pdo->prepare(
        'SELECT id FROM usuarios
         WHERE lower(username) = lower(:username)
            OR lower(email) = lower(:email)
         LIMIT 1'
    );
    $existe->execute(['username' => $username, 'email' => $email]);

    if ($existe->fetchColumn() !== false) {
        fwrite(STDERR, "Ya existe un usuario con ese nombre o email.\n");
        exit(1);
    }

    $rol = $super ? 'super_admin' : 'admin';

    if ($dryRun) {
        echo "DRY RUN: se crearía {$rol} {$username} <{$email}>.\n";
        exit(0);
    }

    $insert = $pdo->prepare(
        'INSERT INTO usuarios (username, email, password_hash, rol, activo)
         VALUES (:username, :email, :password_hash, CAST(:rol AS rol_usuario), TRUE)
         RETURNING id'
    );
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'rol' => $rol,
    ]);

    echo 'Usuario ' . $rol . ' creado con id ' . (int) $insert->fetchColumn() . '.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'No se pudo crear el usuario: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
