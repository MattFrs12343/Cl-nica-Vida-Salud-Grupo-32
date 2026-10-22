#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = DB::connection();

$tables = $pdo->query(
    "SELECT tablename
     FROM pg_tables
     WHERE schemaname = 'public'
       AND tablename <> 'schema_migrations'
     ORDER BY tablename"
)->fetchAll(PDO::FETCH_COLUMN);

if ($tables !== []) {
    $quoted = array_map(
        static fn (string $table): string => '"' . str_replace('"', '""', $table) . '"',
        $tables
    );

    $pdo->exec('TRUNCATE TABLE ' . implode(', ', $quoted) . ' RESTART IDENTITY CASCADE');
    echo 'RESET ' . count($tables) . " tablas\n";
}

$files = glob(database_path('seeders/*.sql')) ?: [];
sort($files);

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

echo "Base de datos reiniciada con datos demo.\n";
