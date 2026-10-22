<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class MedicoModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM medicos WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findByUsuarioId(int $usuarioId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM medicos WHERE usuario_id = :usuario_id LIMIT 1'
        );
        $statement->execute(['usuario_id' => $usuarioId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<int, int> */
    public function idsByRecepcionista(int $usuarioId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT medico_id FROM recepcionista_medico WHERE usuario_id = :usuario_id ORDER BY medico_id'
        );
        $statement->execute(['usuario_id' => $usuarioId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
