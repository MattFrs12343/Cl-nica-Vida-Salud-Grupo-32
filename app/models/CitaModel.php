<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class CitaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Bloquea la fila del médico dentro de una transacción para serializar
     * reservas concurrentes del mismo profesional.
     */
    public function bloquearMedico(int $medicoId): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM medicos WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $medicoId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Citas activas (no canceladas) de un médico en una fecha.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activasDelDia(int $medicoId, string $fecha): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, hora_inicio, hora_fin, origen, es_reconsulta
             FROM citas
             WHERE medico_id = :medico_id
               AND fecha = :fecha
               AND estado <> 'cancelada'
             ORDER BY hora_inicio ASC"
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
        ]);

        return $statement->fetchAll();
    }

    public function contarCanalEnFranja(
        int $medicoId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        string $canal
    ): int {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM citas
             WHERE medico_id = :medico_id
               AND fecha = :fecha
               AND estado <> 'cancelada'
               AND origen = CAST(:canal AS origen_cita)
               AND es_reconsulta = FALSE
               AND hora_inicio >= CAST(:hora_inicio AS time)
               AND hora_inicio < CAST(:hora_fin AS time)"
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
            'canal' => $canal,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    public function crearWeb(
        int $pacienteId,
        int $medicoId,
        int $especialidadId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        string $motivo
    ): array {
        $statement = $this->pdo->prepare(
            "INSERT INTO citas (
                 paciente_id, medico_id, especialidad_id, fecha, hora_inicio, hora_fin,
                 estado, origen, motivo
             ) VALUES (
                 :paciente_id, :medico_id, :especialidad_id, :fecha, :hora_inicio, :hora_fin,
                 'programada', 'web', :motivo
             )
             RETURNING id, codigo, paciente_id, medico_id, especialidad_id, fecha,
                       hora_inicio, hora_fin, estado, origen, motivo"
        );
        $statement->execute([
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'motivo' => $motivo !== '' ? $motivo : null,
        ]);

        return $statement->fetch() ?: [];
    }
}
