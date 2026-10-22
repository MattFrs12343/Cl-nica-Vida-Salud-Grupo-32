<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class PacienteModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM pacientes WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByCi(string $ci): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM pacientes WHERE ci = :ci LIMIT 1');
        $statement->execute(['ci' => $ci]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUsuarioId(int $usuarioId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM pacientes WHERE usuario_id = :usuario_id LIMIT 1'
        );
        $statement->execute(['usuario_id' => $usuarioId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Paciente registrado en kiosco sin cuenta asociada, para activación.
     *
     * @return array<string, mixed>|null
     */
    public function findPendienteActivacion(string $ci, string $telefono): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM pacientes
             WHERE ci = :ci
               AND telefono = :telefono
               AND usuario_id IS NULL
             LIMIT 1'
        );
        $statement->execute(['ci' => $ci, 'telefono' => $telefono]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public function vincularUsuario(int $pacienteId, int $usuarioId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE pacientes SET usuario_id = :usuario_id WHERE id = :id'
        );
        $statement->execute(['id' => $pacienteId, 'usuario_id' => $usuarioId]);
    }
}
