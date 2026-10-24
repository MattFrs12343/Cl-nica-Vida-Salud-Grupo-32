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

    /**
     * Aplica el contexto de RLS para el usuario autenticado.
     *
     * @param array{id?: int|string, rol?: string, medico_id?: int|string|null} $user
     */
    public static function setContext(array $user, string $ip = ''): void
    {
        $pdo = self::connection();
        $role = (string) config('database.app_role', 'clinica_app');

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $role) !== 1) {
            throw new RuntimeException('El rol de base de datos configurado no es válido.');
        }

        try {
            $pdo->exec('SET ROLE "' . $role . '"');

            $statement = $pdo->prepare(
                "SELECT
                    set_config('app.usuario_id', :usuario_id, false),
                    set_config('app.rol', :rol, false),
                    set_config('app.medico_id', :medico_id, false),
                    set_config('app.ip', :ip, false)"
            );

            $statement->execute([
                'usuario_id' => (string) ($user['id'] ?? ''),
                'rol' => (string) ($user['rol'] ?? ''),
                'medico_id' => (string) ($user['medico_id'] ?? ''),
                'ip' => $ip,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('No se pudo establecer el contexto de base de datos.', 0, $exception);
        }
    }

    public static function clearContext(): void
    {
        $pdo = self::connection();

        try {
            $pdo->exec('RESET ROLE');
            $pdo->exec(
                "SELECT
                    set_config('app.usuario_id', '', false),
                    set_config('app.rol', '', false),
                    set_config('app.medico_id', '', false),
                    set_config('app.ip', '', false)"
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('No se pudo limpiar el contexto de base de datos.', 0, $exception);
        }
    }

    /**
     * Registra el usuario/IP para los triggers de auditoría sin cambiar el rol
     * actual. Útil en operaciones que corren como propietario (por ejemplo, la
     * reserva web, que necesita ver toda la agenda del médico).
     */
    public static function setAuditContext(int $usuarioId, string $ip = ''): void
    {
        $pdo = self::connection();

        try {
            $statement = $pdo->prepare(
                "SELECT
                    set_config('app.usuario_id', :usuario_id, false),
                    set_config('app.ip', :ip, false)"
            );
            $statement->execute([
                'usuario_id' => $usuarioId > 0 ? (string) $usuarioId : '',
                'ip' => $ip,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('No se pudo establecer el contexto de auditoría.', 0, $exception);
        }
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        // Transacción anidada (por ejemplo, un test que envuelve el request):
        // se usa un savepoint para no abortar la transacción externa.
        if ($pdo->inTransaction()) {
            $savepoint = 'sp_' . bin2hex(random_bytes(6));
            $pdo->exec('SAVEPOINT ' . $savepoint);

            try {
                $result = $callback($pdo);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);

                return $result;
            } catch (Throwable $exception) {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);

                throw $exception;
            }
        }

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
