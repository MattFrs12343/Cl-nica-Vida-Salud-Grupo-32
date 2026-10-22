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

    /**
     * Médicos activos para la landing/kiosco, opcionalmente filtrados por especialidad.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActivos(?int $especialidadId = null): array
    {
        $sql = "SELECT m.id, m.codigo, m.nombres, m.apellidos, m.registro_profesional,
                       m.duracion_cita_min, m.qr_imagen, m.estado,
                       COALESCE(
                           json_agg(
                               json_build_object('id', e.id, 'nombre', e.nombre)
                               ORDER BY e.nombre
                           ) FILTER (WHERE e.id IS NOT NULL),
                           '[]'
                       ) AS especialidades
                FROM medicos m
                LEFT JOIN medico_especialidad me ON me.medico_id = m.id
                LEFT JOIN especialidades e
                       ON e.id = me.especialidad_id AND e.estado = 'activo'
                WHERE m.estado = 'activo'";

        $parametros = [];

        if ($especialidadId !== null) {
            $sql .= ' AND EXISTS (
                          SELECT 1
                          FROM medico_especialidad me_filtro
                          JOIN especialidades e_filtro
                            ON e_filtro.id = me_filtro.especialidad_id
                           AND e_filtro.estado = \'activo\'
                          WHERE me_filtro.medico_id = m.id
                            AND me_filtro.especialidad_id = :especialidad_id
                      )';
            $parametros['especialidad_id'] = $especialidadId;
        }

        $sql .= ' GROUP BY m.id ORDER BY m.apellidos ASC, m.nombres ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return array_map([$this, 'normalizarPublico'], $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findActivoById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT m.id, m.codigo, m.nombres, m.apellidos, m.registro_profesional,
                    m.duracion_cita_min, m.qr_imagen, m.estado,
                    COALESCE(
                        json_agg(
                            json_build_object('id', e.id, 'nombre', e.nombre)
                            ORDER BY e.nombre
                        ) FILTER (WHERE e.id IS NOT NULL),
                        '[]'
                    ) AS especialidades
             FROM medicos m
             LEFT JOIN medico_especialidad me ON me.medico_id = m.id
             LEFT JOIN especialidades e
                    ON e.id = me.especialidad_id AND e.estado = 'activo'
             WHERE m.id = :id AND m.estado = 'activo'
             GROUP BY m.id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarPublico($row);
    }

    public function tieneEspecialidadActiva(int $medicoId, int $especialidadId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM medico_especialidad me
             JOIN especialidades e ON e.id = me.especialidad_id
             WHERE me.medico_id = :medico_id
               AND me.especialidad_id = :especialidad_id
               AND e.estado = 'activo'
             LIMIT 1"
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarPublico(array $row): array
    {
        $especialidades = $row['especialidades'] ?? '[]';

        if (is_string($especialidades)) {
            $decoded = json_decode($especialidades, true);
            $especialidades = is_array($decoded) ? $decoded : [];
        }

        $row['id'] = (int) $row['id'];
        $row['duracion_cita_min'] = (int) $row['duracion_cita_min'];
        $row['nombre_completo'] = 'Dr. ' . $row['nombres'] . ' ' . $row['apellidos'];
        $row['especialidades'] = array_map(
            static fn (array $especialidad): array => [
                'id' => (int) $especialidad['id'],
                'nombre' => (string) $especialidad['nombre'],
            ],
            $especialidades
        );

        $qr = $row['qr_imagen'] !== null ? ltrim((string) $row['qr_imagen'], '/') : null;
        $row['qr_url'] = $qr === null
            ? null
            : (str_starts_with($qr, 'uploads/') ? '/' . $qr : '/uploads/' . $qr);

        unset($row['qr_imagen']);

        return $row;
    }
}
