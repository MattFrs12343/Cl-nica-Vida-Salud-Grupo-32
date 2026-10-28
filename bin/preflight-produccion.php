#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-37 - Preflight de producción.
 *
 * Comprueba configuración, extensiones, base de datos, correo, kiosco y
 * permisos antes de exponer la aplicación. No imprime secretos.
 *
 * Uso: php bin/preflight-produccion.php
 */

use App\Core\DB;

require dirname(__DIR__) . '/app/bootstrap.php';

$fallos = 0;
$avisos = 0;

function ok(string $mensaje): void
{
    echo 'OK    ' . $mensaje . PHP_EOL;
}

function fallo(string $mensaje): void
{
    global $fallos;

    $fallos++;
    echo 'FALLO ' . $mensaje . PHP_EOL;
}

function aviso(string $mensaje): void
{
    global $avisos;

    $avisos++;
    echo 'AVISO ' . $mensaje . PHP_EOL;
}

$app = (array) config('app', []);
$session = (array) ($app['session'] ?? []);
$database = (array) config('database', []);
$mail = (array) config('mail', []);
$kiosk = (array) ($app['kiosk'] ?? []);

// -- Configuración general --------------------------------------------------
if (($app['env'] ?? '') === 'production') {
    ok('APP_ENV=production');
} else {
    fallo('APP_ENV debe ser production.');
}

if (($app['debug'] ?? true) === false) {
    ok('APP_DEBUG=false');
} else {
    fallo('APP_DEBUG debe ser false en producción.');
}

$appUrl = (string) ($app['url'] ?? '');

if (str_starts_with($appUrl, 'https://')) {
    ok('APP_URL usa HTTPS');
} else {
    fallo('APP_URL debe empezar con https://.');
}

if ((bool) ($session['secure'] ?? false)) {
    ok('SESSION_SECURE=true');
} else {
    fallo('SESSION_SECURE debe ser true con HTTPS.');
}

if ((int) ($session['lifetime'] ?? 0) > 0) {
    ok('SESSION_LIFETIME configurado');
} else {
    fallo('SESSION_LIFETIME debe ser mayor que cero.');
}

if (PHP_VERSION_ID >= 80100) {
    ok('PHP ' . PHP_VERSION . ' (>= 8.1)');
} else {
    fallo('Se requiere PHP 8.1 o superior.');
}

foreach (['pdo_pgsql', 'mbstring', 'openssl', 'fileinfo', 'curl'] as $extension) {
    if (extension_loaded($extension)) {
        ok('extensión ' . $extension);
    } else {
        fallo('falta la extensión ' . $extension . '.');
    }
}

// -- Base de datos ----------------------------------------------------------
$sslMode = (string) ($database['sslmode'] ?? 'prefer');

if (in_array($sslMode, ['require', 'verify-ca', 'verify-full'], true)) {
    ok('DB_SSLMODE=' . $sslMode);
} else {
    fallo('DB_SSLMODE debe ser require, verify-ca o verify-full.');
}

if ((string) ($database['app_role'] ?? '') !== '') {
    ok('DB_APP_ROLE configurado');
} else {
    fallo('DB_APP_ROLE no puede estar vacío.');
}

try {
    $pdo = DB::connection();
    $pdo->query('SELECT 1');
    ok('conexión a PostgreSQL');

    try {
        $ssl = $pdo->query(
            'SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()'
        )->fetchColumn();

        if ($ssl === true || $ssl === 't' || $ssl === 1) {
            ok('la conexión PostgreSQL usa SSL');
        } else {
            aviso('no se pudo confirmar SSL en pg_stat_ssl; revisa el proveedor.');
        }
    } catch (Throwable) {
        aviso('pg_stat_ssl no disponible; confirma SSL con el proveedor.');
    }

    $migracion = $pdo->query(
        "SELECT COUNT(*) FROM schema_migrations WHERE filename = '012_performance_indexes.sql'"
    )->fetchColumn();

    if ((int) $migracion > 0) {
        ok('migración 012_performance_indexes aplicada');
    } else {
        fallo('falta aplicar la migración 012_performance_indexes.sql.');
    }

    $demo = $pdo->query(
        "SELECT COUNT(*) FROM usuarios
         WHERE username IN ('superadmin', 'admin', 'recepcion', 'recepcion.medico', 'medico', '12345678')"
    )->fetchColumn();

    if ((int) $demo > 0) {
        aviso('hay usuarios demo en la base de producción; elimínalos o cámbiales la contraseña.');
    } else {
        ok('sin usuarios demo conocidos');
    }
} catch (Throwable $exception) {
    fallo('no se pudo validar la base de datos: ' . $exception->getMessage());
}

// -- Correo -----------------------------------------------------------------
$mailHost = strtolower((string) ($mail['host'] ?? ''));

if ($mailHost !== '' && !in_array($mailHost, ['localhost', '127.0.0.1', 'mailpit'], true)) {
    ok('MAIL_HOST de producción');
} else {
    fallo('MAIL_HOST no puede ser Mailpit/localhost en producción.');
}

if ((string) ($mail['username'] ?? '') !== '' && (string) ($mail['password'] ?? '') !== '') {
    ok('credenciales SMTP configuradas');
} else {
    fallo('MAIL_USERNAME y MAIL_PASSWORD son obligatorios.');
}

if ((bool) ($mail['smtp_auth'] ?? false)) {
    ok('MAIL_SMTP_AUTH=true');
} else {
    fallo('MAIL_SMTP_AUTH debe ser true con Brevo.');
}

if ((string) ($mail['encryption'] ?? '') !== '') {
    ok('cifrado SMTP configurado');
} else {
    aviso('MAIL_ENCRYPTION está vacío; Brevo normalmente usa tls.');
}

if ((string) ($mail['from'] ?? '') !== '' && !str_contains((string) $mail['from'], 'vidasalud.test')) {
    ok('MAIL_FROM de producción');
} else {
    aviso('MAIL_FROM parece de desarrollo; usa un remitente verificado.');
}

// -- Kiosco -----------------------------------------------------------------
$deviceKey = (string) ($kiosk['device_key'] ?? '');
$hmacSecret = (string) ($kiosk['hmac_secret'] ?? '');

if ($deviceKey !== '' && !str_contains($deviceKey, 'dev-')) {
    ok('KIOSK_DEVICE_KEY configurado');
} else {
    fallo('KIOSK_DEVICE_KEY debe ser un valor aleatorio de producción.');
}

if (strlen($hmacSecret) >= 32 && !str_contains($hmacSecret, 'dev-')) {
    ok('KIOSK_HMAC_SECRET con longitud suficiente');
} else {
    fallo('KIOSK_HMAC_SECRET debe tener al menos 32 caracteres y no ser el de desarrollo.');
}

// -- Archivos y permisos ----------------------------------------------------
foreach ([storage_path('logs'), public_path('uploads')] as $ruta) {
    if (is_dir($ruta) && is_writable($ruta)) {
        ok('escritura en ' . $ruta);
    } else {
        fallo('la ruta ' . $ruta . ' debe existir y ser escribible.');
    }
}

if (is_file(public_path('uploads/.htaccess'))) {
    ok('uploads protegido contra ejecución PHP');
} else {
    fallo('falta public/uploads/.htaccess.');
}

if (is_file(public_path('.htaccess'))) {
    ok('public/.htaccess presente');
} else {
    fallo('falta public/.htaccess.');
}

if (is_file(base_path('vendor/autoload.php'))) {
    ok('dependencias de Composer instaladas');
} else {
    fallo('falta vendor/autoload.php; ejecuta composer install --no-dev.');
}

if ((int) config('app.uploads.max_mb', 2) >= 1 && (int) config('app.uploads.max_mb', 2) <= 10) {
    ok('UPLOAD_MAX_MB dentro de rango');
} else {
    aviso('UPLOAD_MAX_MB fuera del rango recomendado (1-10).');
}

if (!filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOL)) {
    ok('display_errors desactivado');
} else {
    aviso('display_errors está activo; desactívalo en php.ini de producción.');
}

echo PHP_EOL;

if ($fallos > 0) {
    echo $fallos . ' fallo(s) y ' . $avisos . ' aviso(s). Corrige los fallos antes de publicar.' . PHP_EOL;
    exit(1);
}

echo 'Preflight correcto con ' . $avisos . ' aviso(s).' . PHP_EOL;
