<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\DB;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

$vendorAutoload = BASE_PATH . '/vendor/autoload.php';

if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

require BASE_PATH . '/app/autoload.php';

Env::load(BASE_PATH . '/.env');

Config::load('app', BASE_PATH . '/app/config/config.php');
Config::load('database', BASE_PATH . '/app/config/database.php');
Config::load('mail', BASE_PATH . '/app/config/mail.php');

date_default_timezone_set((string) config('app.timezone', 'America/La_Paz'));

ErrorHandler::register();

// En CLI (migraciones, seeders) no se abre sesión ni se cambia de rol.
if (PHP_SAPI !== 'cli') {
    Session::start();

    $usuario = auth();

    if ($usuario !== null) {
        DB::setContext($usuario, (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}
