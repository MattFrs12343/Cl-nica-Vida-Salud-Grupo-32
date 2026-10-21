#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = DB::connection();
$files = glob(database_path('seeders/*.sql')) ?: [];
sort($files);

if ($files === []) {
    echo "No hay seeders para ejecutar.\n";
    exit(0);
}

foreach ($files as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException('No se pudo leer el seeder: ' . $name);
    }

    echo "SEED  {$name}\n";
    $pdo->beginTransaction();

    try {
        $pdo->exec($sql);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "ERROR {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Seeders ejecutados.\n";
