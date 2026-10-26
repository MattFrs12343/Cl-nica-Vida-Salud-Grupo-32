<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Resultados asociados a una orden de laboratorio/imagen (Spec 10 / M-31).
 */
final class ResultadoModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    public function crear(int $ordenId, ?string $archivo, ?string $texto, bool $revisado = false): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO resultados (orden_id, archivo, texto, revisado)
             VALUES (:orden_id, :archivo, :texto, :revisado)
             RETURNING id'
        );
        $statement->execute([
            'orden_id' => $ordenId,
            'archivo' => $archivo,
            'texto' => $texto,
            'revisado' => $revisado ? 'true' : 'false',
        ]);

        return (int) $statement->fetchColumn();
    }
}
