<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use DateTimeImmutable;
use PDO;

final class EspecialidadModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Especialidades activas para landing/kiosco. Las destacadas van primero.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActivas(): array
    {
        $statement = $this->pdo->query(
            "SELECT id, nombre, descripcion, sexo_permitido, edad_min, edad_max,
                    destacada, icono, estado
             FROM especialidades
             WHERE estado = 'activo'
             ORDER BY destacada DESC, nombre ASC"
        );

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findActivaById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, nombre, descripcion, sexo_permitido, edad_min, edad_max,
                    destacada, icono, estado
             FROM especialidades
             WHERE id = :id AND estado = 'activo'
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /**
     * Valida la restricción de sexo/edad de una especialidad (RX02/RX03).
     *
     * @param array<string, mixed> $especialidad
     * @param array<string, mixed> $paciente
     */
    public function esElegiblePaciente(array $especialidad, array $paciente): bool
    {
        $sexoPermitido = (string) ($especialidad['sexo_permitido'] ?? 'ambos');
        $sexoPaciente = (string) ($paciente['sexo'] ?? '');

        if ($sexoPermitido !== 'ambos' && $sexoPermitido !== $sexoPaciente) {
            return false;
        }

        $edad = $this->edadPaciente((string) ($paciente['fecha_nacimiento'] ?? ''));

        if ($edad === null) {
            return true;
        }

        $edadMin = $especialidad['edad_min'];
        $edadMax = $especialidad['edad_max'];

        if ($edadMin !== null && $edad < (int) $edadMin) {
            return false;
        }

        if ($edadMax !== null && $edad > (int) $edadMax) {
            return false;
        }

        return true;
    }

    /**
     * @param array{q?: string, estado?: string|null, destacada?: bool|null} $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listarAdmin(array $filtros, int $page, int $perPage): array
    {
        $where = [];
        $parametros = [];

        if (($filtros['q'] ?? '') !== '') {
            $where[] = 'e.nombre ILIKE :q';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        if (($filtros['estado'] ?? null) !== null) {
            $where[] = 'e.estado = CAST(:estado AS estado_paciente)';
            $parametros['estado'] = $filtros['estado'];
        }

        if (array_key_exists('destacada', $filtros) && $filtros['destacada'] !== null) {
            $where[] = 'e.destacada = CAST(:destacada AS boolean)';
            $parametros['destacada'] = $filtros['destacada'] ? 1 : 0;
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM especialidades e' . $whereSql);
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT e.id, e.nombre, e.descripcion, e.sexo_permitido,
                       e.edad_min, e.edad_max, e.destacada, e.icono, e.estado,
                       e.created_at, e.updated_at,
                       (SELECT COUNT(*)
                        FROM medico_especialidad me
                        WHERE me.especialidad_id = e.id) AS medicos_count
                FROM especialidades e'
            . $whereSql
            . ' ORDER BY e.destacada DESC, e.nombre ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return [
            'data' => array_map([$this, 'normalizar'], $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findAdminById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT e.id, e.nombre, e.descripcion, e.sexo_permitido,
                    e.edad_min, e.edad_max, e.destacada, e.icono, e.estado,
                    e.created_at, e.updated_at,
                    (SELECT COUNT(*)
                     FROM medico_especialidad me
                     WHERE me.especialidad_id = e.id) AS medicos_count
             FROM especialidades e
             WHERE e.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    public function existeNombre(string $nombre, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM especialidades WHERE lower(nombre) = lower(:nombre)';
        $parametros = ['nombre' => $nombre];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parametros['exclude_id'] = $excludeId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO especialidades (
                 nombre, descripcion, sexo_permitido, edad_min, edad_max,
                 destacada, icono, estado
             ) VALUES (
                 :nombre, :descripcion, CAST(:sexo_permitido AS sexo_permitido),
                 :edad_min, :edad_max, CAST(:destacada AS boolean), :icono,
                 CAST(:estado AS estado_paciente)
             )
             RETURNING id'
        );
        $statement->execute([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'],
            'sexo_permitido' => $datos['sexo_permitido'],
            'edad_min' => $datos['edad_min'],
            'edad_max' => $datos['edad_max'],
            'destacada' => $datos['destacada'] ? 1 : 0,
            'icono' => $datos['icono'],
            'estado' => $datos['estado'],
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): void
    {
        $asignaciones = [];
        $parametros = ['id' => $id];

        foreach (['nombre', 'descripcion', 'edad_min', 'edad_max', 'icono'] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = $campo . ' = :' . $campo;
                $parametros[$campo] = $datos[$campo];
            }
        }

        if (array_key_exists('destacada', $datos)) {
            $asignaciones[] = 'destacada = CAST(:destacada AS boolean)';
            $parametros['destacada'] = $datos['destacada'] ? 1 : 0;
        }

        if (array_key_exists('sexo_permitido', $datos)) {
            $asignaciones[] = 'sexo_permitido = CAST(:sexo_permitido AS sexo_permitido)';
            $parametros['sexo_permitido'] = $datos['sexo_permitido'];
        }

        if (array_key_exists('estado', $datos)) {
            $asignaciones[] = 'estado = CAST(:estado AS estado_paciente)';
            $parametros['estado'] = $datos['estado'];
        }

        if ($asignaciones === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE especialidades SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $statement->execute($parametros);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE especialidades SET estado = CAST(:estado AS estado_paciente) WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'estado' => $estado]);
    }

    private function edadPaciente(string $fechaNacimiento): ?int
    {
        if ($fechaNacimiento === '') {
            return null;
        }

        try {
            $nacimiento = new DateTimeImmutable($fechaNacimiento);
        } catch (\Throwable) {
            return null;
        }

        return $nacimiento->diff(new DateTimeImmutable('today'))->y;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizar(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['edad_min'] = $row['edad_min'] === null ? null : (int) $row['edad_min'];
        $row['edad_max'] = $row['edad_max'] === null ? null : (int) $row['edad_max'];
        $row['destacada'] = (bool) $row['destacada'];

        if (array_key_exists('medicos_count', $row)) {
            $row['medicos_count'] = (int) $row['medicos_count'];
        }

        return $row;
    }
}
