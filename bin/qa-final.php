#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * M-39 - QA final.
 *
 * Ejecuta todas las suites bin/test-*.php en orden y devuelve un código de
 * salida distinto de cero si alguna falla. Pensado para la demo y para CI.
 */

$base = dirname(__DIR__);
$archivos = glob($base . '/bin/test-*.php') ?: [];
sort($archivos);

if ($archivos === []) {
    fwrite(STDERR, "No se encontraron suites bin/test-*.php.\n");
    exit(1);
}

if (!function_exists('proc_open')) {
    fwrite(STDERR, "proc_open no está disponible; ejecuta las suites manualmente.\n");
    exit(1);
}

$fallos = [];
$inicio = microtime(true);

foreach ($archivos as $archivo) {
    $nombre = basename($archivo);
    echo 'RUN  ' . $nombre . PHP_EOL;

    $descriptores = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proceso = proc_open(
        [PHP_BINARY, $archivo],
        $descriptores,
        $tuberias,
        $base
    );

    if (!is_resource($proceso)) {
        $fallos[] = $nombre;
        echo 'FALLO no se pudo iniciar ' . $nombre . PHP_EOL;
        continue;
    }

    $salida = stream_get_contents($tuberias[1]);
    $error = stream_get_contents($tuberias[2]);
    fclose($tuberias[1]);
    fclose($tuberias[2]);
    $codigo = proc_close($proceso);

    if ($codigo === 0) {
        echo 'OK   ' . $nombre . PHP_EOL;

        continue;
    }

    $fallos[] = $nombre;
    echo 'FALLO ' . $nombre . PHP_EOL;

    if (trim((string) $salida) !== '') {
        echo rtrim((string) $salida) . PHP_EOL;
    }

    if (trim((string) $error) !== '') {
        echo rtrim((string) $error) . PHP_EOL;
    }
}

$segundos = number_format(microtime(true) - $inicio, 1);

echo PHP_EOL;

if ($fallos !== []) {
    echo count($fallos) . ' suite(s) fallaron en ' . $segundos . ' s: ' . implode(', ', $fallos) . PHP_EOL;
    exit(1);
}

echo count($archivos) . ' suites pasaron en ' . $segundos . ' s.' . PHP_EOL;
