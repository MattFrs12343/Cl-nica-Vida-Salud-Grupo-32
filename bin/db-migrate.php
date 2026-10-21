#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = DB::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )'
);

$files = glob(database_path('migrations/*.sql')) ?: [];
sort($files);

if ($files === []) {
    echo "No hay migraciones para aplicar.\n";
    exit(0);
}

$check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ?');
$record = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');

foreach ($files as $file) {
    $name = basename($file);
    $check->execute([$name]);

    if ($check->fetchColumn() !== false) {
        echo "SKIP  {$name}\n";
        continue;
    }

    echo "APPLY {$name}\n";
    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException('No se pudo leer la migración: ' . $name);
    }

    $pdo->beginTransaction();

    try {
        $pdo->exec($sql);
        $record->execute([$name]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "ERROR {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Migraciones aplicadas.\n";
