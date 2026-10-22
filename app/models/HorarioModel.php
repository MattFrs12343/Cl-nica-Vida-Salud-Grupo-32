<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class HorarioModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function franjasActivas(int $medicoId, int $diaSemana): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, medico_id, dia_semana, hora_inicio, hora_fin, cupos_web, cupos_kiosco
             FROM horarios
             WHERE medico_id = :medico_id
               AND dia_semana = :dia_semana
               AND activo = TRUE
             ORDER BY hora_inicio ASC'
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'dia_semana' => $diaSemana,
        ]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function bloqueosDelDia(int $medicoId, string $fecha): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, hora_inicio, hora_fin, motivo
             FROM bloqueos
             WHERE medico_id = :medico_id AND fecha = :fecha
             ORDER BY hora_inicio ASC NULLS FIRST'
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
        ]);

        return $statement->fetchAll();
    }
}
