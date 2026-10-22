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
     * Listado administrativo de médicos.
     *
     * @param array{q?: string, especialidad?: int|null, estado?: string|null} $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listarAdmin(array $filtros, int $page, int $perPage): array
    {
        $where = [];
        $parametros = [];

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(m.nombres ILIKE :q
                         OR m.apellidos ILIKE :q
                         OR m.codigo ILIKE :q
                         OR m.registro_profesional ILIKE :q
                         OR COALESCE(m.email, \'\') ILIKE :q)';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        if (($filtros['especialidad'] ?? null) !== null) {
            $where[] = 'EXISTS (
                SELECT 1
                FROM medico_especialidad me_filtro
                WHERE me_filtro.medico_id = m.id
                  AND me_filtro.especialidad_id = :especialidad_id
            )';
            $parametros['especialidad_id'] = (int) $filtros['especialidad'];
        }

        if (($filtros['estado'] ?? null) !== null) {
            $where[] = 'm.estado = CAST(:estado AS estado_paciente)';
            $parametros['estado'] = $filtros['estado'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM medicos m' . $whereSql);
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT m.id, m.codigo, m.usuario_id, m.registro_profesional,
                       m.nombres, m.apellidos, m.telefono, m.email,
                       m.duracion_cita_min, m.qr_imagen, m.estado,
                       m.created_at, m.updated_at,
                       COALESCE(
                           json_agg(
                               json_build_object(
                                   'id', e.id,
                                   'nombre', e.nombre,
                                   'estado', e.estado
                               )
                               ORDER BY e.nombre
                           ) FILTER (WHERE e.id IS NOT NULL),
                           '[]'
                       ) AS especialidades
                FROM medicos m
                LEFT JOIN medico_especialidad me ON me.medico_id = m.id
                LEFT JOIN especialidades e ON e.id = me.especialidad_id"
            . $whereSql
            . ' GROUP BY m.id
                ORDER BY m.apellidos ASC, m.nombres ASC, m.id ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

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
            "SELECT m.id, m.codigo, m.usuario_id, m.registro_profesional,
                    m.nombres, m.apellidos, m.telefono, m.email,
                    m.duracion_cita_min, m.qr_imagen, m.estado,
                    m.created_at, m.updated_at,
                    COALESCE(
                        json_agg(
                            json_build_object(
                                'id', e.id,
                                'nombre', e.nombre,
                                'estado', e.estado
                            )
                            ORDER BY e.nombre
                        ) FILTER (WHERE e.id IS NOT NULL),
                        '[]'
                    ) AS especialidades
             FROM medicos m
             LEFT JOIN medico_especialidad me ON me.medico_id = m.id
             LEFT JOIN especialidades e ON e.id = me.especialidad_id
             WHERE m.id = :id
             GROUP BY m.id
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarAdmin($row);
    }

    public function existeRegistro(string $registro, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM medicos WHERE lower(registro_profesional) = lower(:registro)';
        $parametros = ['registro' => $registro];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parametros['exclude_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function crearAdmin(array $datos): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO medicos (
                 usuario_id, registro_profesional, nombres, apellidos,
                 telefono, email, duracion_cita_min
             ) VALUES (
                 :usuario_id, :registro_profesional, :nombres, :apellidos,
                 :telefono, :email, :duracion_cita_min
             )
             RETURNING id'
        );
        $statement->execute([
            'usuario_id' => $datos['usuario_id'] ?? null,
            'registro_profesional' => $datos['registro_profesional'],
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
            'duracion_cita_min' => $datos['duracion_cita_min'],
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

        foreach ([
            'registro_profesional',
            'nombres',
            'apellidos',
            'telefono',
            'email',
            'duracion_cita_min',
        ] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = $campo . ' = :' . $campo;
                $parametros[$campo] = $datos[$campo];
            }
        }

        if ($asignaciones === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE medicos SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $statement->execute($parametros);
    }

    /** @param array<int, int> $especialidadIds */
    public function reemplazarEspecialidades(int $medicoId, array $especialidadIds): void
    {
        $delete = $this->pdo->prepare('DELETE FROM medico_especialidad WHERE medico_id = :medico_id');
        $delete->execute(['medico_id' => $medicoId]);

        if ($especialidadIds === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO medico_especialidad (medico_id, especialidad_id)
             VALUES (:medico_id, :especialidad_id)
             ON CONFLICT DO NOTHING'
        );

        foreach (array_values(array_unique($especialidadIds)) as $especialidadId) {
            $insert->execute([
                'medico_id' => $medicoId,
                'especialidad_id' => $especialidadId,
            ]);
        }
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE medicos SET estado = CAST(:estado AS estado_paciente) WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'estado' => $estado]);
    }

    public function tieneCitasFuturas(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1
             FROM citas
             WHERE medico_id = :id
               AND fecha >= CURRENT_DATE
               AND estado IN ('programada', 'confirmada')
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<int, array<string, mixed>> */
    public function recepcionistasDe(int $medicoId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.username, u.email, u.activo
             FROM recepcionista_medico rm
             JOIN usuarios u ON u.id = rm.usuario_id
             WHERE rm.medico_id = :medico_id
             ORDER BY u.username ASC'
        );
        $statement->execute(['medico_id' => $medicoId]);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'activo' => (bool) $row['activo'],
            ],
            $statement->fetchAll()
        );
    }

    public function asignarRecepcionista(int $medicoId, int $usuarioId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO recepcionista_medico (usuario_id, medico_id)
             VALUES (:usuario_id, :medico_id)
             ON CONFLICT DO NOTHING'
        );
        $statement->execute([
            'usuario_id' => $usuarioId,
            'medico_id' => $medicoId,
        ]);
    }

    public function actualizarQr(int $medicoId, string $ruta): void
    {
        $statement = $this->pdo->prepare('UPDATE medicos SET qr_imagen = :qr_imagen WHERE id = :id');
        $statement->execute(['id' => $medicoId, 'qr_imagen' => $ruta]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarAdmin(array $row): array
    {
        $especialidades = $row['especialidades'] ?? '[]';

        if (is_string($especialidades)) {
            $decoded = json_decode($especialidades, true);
            $especialidades = is_array($decoded) ? $decoded : [];
        }

        $row['id'] = (int) $row['id'];
        $row['usuario_id'] = $row['usuario_id'] === null ? null : (int) $row['usuario_id'];
        $row['duracion_cita_min'] = (int) $row['duracion_cita_min'];
        $row['especialidades'] = array_map(
            static fn (array $especialidad): array => [
                'id' => (int) $especialidad['id'],
                'nombre' => (string) $especialidad['nombre'],
                'estado' => (string) ($especialidad['estado'] ?? 'activo'),
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
