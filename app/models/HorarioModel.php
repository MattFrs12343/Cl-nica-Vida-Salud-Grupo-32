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

    /** @return array<int, array<string, mixed>> */
    public function listarPorMedico(int $medicoId, ?bool $activo = null): array
    {
        $sql = 'SELECT id, medico_id, dia_semana, hora_inicio, hora_fin,
                       cupos_web, cupos_kiosco, activo, created_at, updated_at
                FROM horarios
                WHERE medico_id = :medico_id';
        $parametros = ['medico_id' => $medicoId];

        if ($activo !== null) {
            $sql .= ' AND activo = CAST(:activo AS boolean)';
            $parametros['activo'] = $activo ? 1 : 0;
        }

        $sql .= ' ORDER BY dia_semana ASC, hora_inicio ASC, id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return array_map([$this, 'normalizarFranja'], $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, medico_id, dia_semana, hora_inicio, hora_fin,
                    cupos_web, cupos_kiosco, activo, created_at, updated_at
             FROM horarios
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarFranja($row);
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO horarios (
                 medico_id, dia_semana, hora_inicio, hora_fin,
                 cupos_web, cupos_kiosco, activo
             ) VALUES (
                 :medico_id, :dia_semana, CAST(:hora_inicio AS time),
                 CAST(:hora_fin AS time), :cupos_web, :cupos_kiosco,
                 CAST(:activo AS boolean)
             )
             RETURNING id'
        );
        $statement->execute([
            'medico_id' => $datos['medico_id'],
            'dia_semana' => $datos['dia_semana'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $datos['hora_fin'],
            'cupos_web' => $datos['cupos_web'],
            'cupos_kiosco' => $datos['cupos_kiosco'],
            'activo' => ($datos['activo'] ?? true) ? 1 : 0,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): void
    {
        $asignaciones = [];
        $parametros = ['id' => $id];

        foreach (['dia_semana', 'cupos_web', 'cupos_kiosco'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = $campo . ' = :' . $campo;
                $parametros[$campo] = $datos[$campo];
            }
        }

        if (array_key_exists('activo', $datos)) {
            $asignaciones[] = 'activo = CAST(:activo AS boolean)';
            $parametros['activo'] = $datos['activo'] ? 1 : 0;
        }

        foreach (['hora_inicio', 'hora_fin'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = $campo . ' = CAST(:' . $campo . ' AS time)';
                $parametros[$campo] = $datos[$campo];
            }
        }

        if ($asignaciones === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE horarios SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $statement->execute($parametros);
    }

    public function desactivar(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE horarios SET activo = FALSE WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function haySolape(
        int $medicoId,
        int $diaSemana,
        string $horaInicio,
        string $horaFin,
        ?int $excludeId = null
    ): bool {
        $sql = 'SELECT 1
                FROM horarios
                WHERE medico_id = :medico_id
                  AND dia_semana = :dia_semana
                  AND activo = TRUE
                  AND CAST(:hora_inicio AS time) < hora_fin
                  AND CAST(:hora_fin AS time) > hora_inicio';
        $parametros = [
            'medico_id' => $medicoId,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parametros['exclude_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarBloqueos(int $medicoId, ?string $desde = null, ?string $hasta = null): array
    {
        $sql = 'SELECT id, medico_id, fecha, hora_inicio, hora_fin, motivo, created_at
                FROM bloqueos
                WHERE medico_id = :medico_id';
        $parametros = ['medico_id' => $medicoId];

        if ($desde !== null) {
            $sql .= ' AND fecha >= CAST(:desde AS date)';
            $parametros['desde'] = $desde;
        }

        if ($hasta !== null) {
            $sql .= ' AND fecha <= CAST(:hasta AS date)';
            $parametros['hasta'] = $hasta;
        }

        $sql .= ' ORDER BY fecha ASC, hora_inicio ASC NULLS FIRST, id ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return array_map([$this, 'normalizarBloqueo'], $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findBloqueo(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, medico_id, fecha, hora_inicio, hora_fin, motivo, created_at
             FROM bloqueos
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarBloqueo($row);
    }

    /** @param array<string, mixed> $datos */
    public function crearBloqueo(array $datos): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO bloqueos (medico_id, fecha, hora_inicio, hora_fin, motivo)
             VALUES (
                 :medico_id, CAST(:fecha AS date), CAST(:hora_inicio AS time),
                 CAST(:hora_fin AS time), :motivo
             )
             RETURNING id'
        );
        $statement->execute([
            'medico_id' => $datos['medico_id'],
            'fecha' => $datos['fecha'],
            'hora_inicio' => $datos['hora_inicio'],
            'hora_fin' => $datos['hora_fin'],
            'motivo' => $datos['motivo'],
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarFranja(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['medico_id'] = (int) $row['medico_id'];
        $row['dia_semana'] = (int) $row['dia_semana'];
        $row['hora_inicio'] = substr((string) $row['hora_inicio'], 0, 5);
        $row['hora_fin'] = substr((string) $row['hora_fin'], 0, 5);
        $row['cupos_web'] = (int) $row['cupos_web'];
        $row['cupos_kiosco'] = (int) $row['cupos_kiosco'];
        $row['activo'] = (bool) $row['activo'];

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarBloqueo(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['medico_id'] = (int) $row['medico_id'];
        $row['fecha'] = (string) $row['fecha'];
        $row['hora_inicio'] = $row['hora_inicio'] === null
            ? null
            : substr((string) $row['hora_inicio'], 0, 5);
        $row['hora_fin'] = $row['hora_fin'] === null
            ? null
            : substr((string) $row['hora_fin'], 0, 5);

        return $row;
    }
}
