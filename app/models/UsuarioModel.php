<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class UsuarioModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM usuarios WHERE lower(email) = lower(:email) LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM usuarios WHERE lower(username) = lower(:username) LIMIT 1'
        );
        $statement->execute(['username' => $username]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Busca una cuenta interna por usuario o email.
     *
     * @return array<string, mixed>|null
     */
    public function findInternalByIdentificador(string $identificador): ?array
    {
        if (str_contains($identificador, '@')) {
            return $this->findByEmail($identificador);
        }

        $usuario = $this->findByUsername($identificador);

        return $usuario ?? $this->findByEmail($identificador);
    }

    public function incrementarIntentos(int $id): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = intentos_fallidos + 1,
                 bloqueado = CASE WHEN intentos_fallidos + 1 >= 3 THEN TRUE ELSE bloqueado END
             WHERE id = :id
             RETURNING intentos_fallidos'
        );
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
    }

    public function registrarAcceso(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET intentos_fallidos = 0,
                 bloqueado = FALSE,
                 ultimo_acceso = now()
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function guardarTokenReset(int $id, string $token, int $minutos = 30): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE usuarios
             SET token_reset = :token,
                 token_reset_expira = now() + (:minutos || ' minutes')::interval
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'token' => $token,
            'minutos' => (string) $minutos,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByResetToken(string $token): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM usuarios
             WHERE token_reset = :token
               AND token_reset_expira IS NOT NULL
               AND token_reset_expira > now()
             LIMIT 1'
        );
        $statement->execute(['token' => $token]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function actualizarPassword(int $id, string $hash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET password_hash = :hash,
                 token_reset = NULL,
                 token_reset_expira = NULL
             WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'hash' => $hash]);
    }

    public function crearPaciente(
        string $username,
        ?string $email,
        string $passwordHash
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO usuarios (username, email, password_hash, rol, activo)
             VALUES (:username, :email, :password_hash, 'paciente', TRUE)
             RETURNING id"
        );
        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function desbloquear(int $id): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios
             SET bloqueado = FALSE, intentos_fallidos = 0
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    /**
     * Listado administrativo de usuarios.
     *
     * @param array{q?: string, rol?: string, estado?: string, bloqueado?: bool} $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listarAdmin(array $filtros, int $page, int $perPage): array
    {
        $where = [];
        $parametros = [];

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(username ILIKE :q OR email ILIKE :q)';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        if (($filtros['rol'] ?? '') !== '') {
            $where[] = 'rol = CAST(:rol AS rol_usuario)';
            $parametros['rol'] = $filtros['rol'];
        }

        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null) {
            $where[] = 'activo = CAST(:activo AS boolean)';
            $parametros['activo'] = $filtros['estado'] === 'activo' ? 1 : 0;
        }

        if (array_key_exists('bloqueado', $filtros) && $filtros['bloqueado'] !== null) {
            $where[] = 'bloqueado = CAST(:bloqueado AS boolean)';
            $parametros['bloqueado'] = $filtros['bloqueado'] ? 1 : 0;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios' . $whereSql);
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT id, username, email, rol, activo, bloqueado, intentos_fallidos,
                       ultimo_acceso, created_at, updated_at
                FROM usuarios'
            . $whereSql
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return [
            'data' => array_map([$this, 'normalizarAdmin'], $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findAdminById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, rol, activo, bloqueado, intentos_fallidos,
                    ultimo_acceso, created_at, updated_at
             FROM usuarios
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarAdmin($row);
    }

    public function existeUsername(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM usuarios WHERE lower(username) = lower(:username)';
        $parametros = ['username' => $username];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parametros['exclude_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    public function existeEmail(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM usuarios WHERE lower(email) = lower(:email)';
        $parametros = ['email' => $email];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parametros['exclude_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array{username: ?string, email: ?string, rol: string, activo: bool} $datos
     */
    public function crearAdmin(array $datos, string $passwordHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO usuarios (username, email, password_hash, rol, activo)
             VALUES (:username, :email, :password_hash, CAST(:rol AS rol_usuario), CAST(:activo AS boolean))
             RETURNING id'
        );
        $statement->execute([
            'username' => $datos['username'],
            'email' => $datos['email'],
            'password_hash' => $passwordHash,
            'rol' => $datos['rol'],
            'activo' => $datos['activo'] ? 1 : 0,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function actualizarAdmin(int $id, array $datos): void
    {
        $asignaciones = [];
        $parametros = ['id' => $id];

        foreach (['username', 'email', 'password_hash'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = $campo . ' = :' . $campo;
                $parametros[$campo] = $datos[$campo];
            }
        }

        if (array_key_exists('rol', $datos)) {
            $asignaciones[] = 'rol = CAST(:rol AS rol_usuario)';
            $parametros['rol'] = $datos['rol'];
        }

        if (array_key_exists('activo', $datos)) {
            $asignaciones[] = 'activo = CAST(:activo AS boolean)';
            $parametros['activo'] = $datos['activo'] ? 1 : 0;
        }

        if ($asignaciones === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE usuarios SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $statement->execute($parametros);
    }

    public function actualizarEstado(int $id, bool $activo): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE usuarios SET activo = CAST(:activo AS boolean) WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'activo' => $activo ? 1 : 0]);
    }

    public function contarSuperAdminsActivos(?int $excludeId = null): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE rol = 'super_admin' AND activo = TRUE";
        $parametros = [];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parametros['exclude_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarAdmin(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['activo'] = (bool) $row['activo'];
        $row['bloqueado'] = (bool) $row['bloqueado'];
        $row['intentos_fallidos'] = (int) $row['intentos_fallidos'];

        return $row;
    }
}
