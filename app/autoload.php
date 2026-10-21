<?php

declare(strict_types=1);

/**
 * Autoload PSR-4 adaptado a las carpetas en minúscula del proyecto.
 * App\Core\Router  -> app/core/Router.php
 * App\Controllers\PublicController -> app/controllers/PublicController.php
 */
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\Core\\' => __DIR__ . '/core/',
        'App\\Controllers\\' => __DIR__ . '/controllers/',
        'App\\Middlewares\\' => __DIR__ . '/middlewares/',
        'App\\Models\\' => __DIR__ . '/models/',
        'App\\Services\\' => __DIR__ . '/services/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $file = $directory . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require_once __DIR__ . '/core/helpers.php';
