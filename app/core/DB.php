<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class DB
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = (array) config('database');

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;sslmode=%s',
            $config['driver'] ?? 'pgsql',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 5432,
            $config['database'] ?? 'clinica',
            $config['sslmode'] ?? 'prefer'
        );

        try {
            self::$connection = new PDO(
                $dsn,
                (string) ($config['username'] ?? ''),
                (string) ($config['password'] ?? ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $timezone = (string) config('app.timezone', 'America/La_Paz');
            self::$connection->exec("SET TIME ZONE " . self::$connection->quote($timezone));
        } catch (PDOException $exception) {
            throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $exception);
        }

        return self::$connection;
    }

    public static function reset(): void
    {
        self::$connection = null;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
