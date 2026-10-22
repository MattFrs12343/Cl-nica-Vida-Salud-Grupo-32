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
}
