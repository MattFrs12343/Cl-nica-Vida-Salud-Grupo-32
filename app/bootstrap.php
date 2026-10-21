<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/autoload.php';

Env::load(BASE_PATH . '/.env');

Config::load('app', BASE_PATH . '/app/config/config.php');
Config::load('database', BASE_PATH . '/app/config/database.php');
Config::load('mail', BASE_PATH . '/app/config/mail.php');

date_default_timezone_set((string) config('app.timezone', 'America/La_Paz'));

ErrorHandler::register();

// En CLI (migraciones, seeders) no se abre sesión.
if (PHP_SAPI !== 'cli') {
    Session::start();
}
