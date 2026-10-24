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

    public function existeCi(string $ci, ?int $excluirId = null): bool
    {
        $sql = 'SELECT 1 FROM pacientes WHERE lower(ci) = lower(:ci)';
        $parametros = ['ci' => $ci];

        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $parametros['excluir_id'] = $excluirId;
        }

        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Listado administrativo de pacientes. `medico_ids` restringe a pacientes
     * con alguna cita con esos profesionales (alcance de médico/recepción
     * médica).
     *
     * @param array{q?: string, estado?: string|null, medico_ids?: array<int, int>|null} $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listarAdmin(array $filtros, int $page, int $perPage): array
    {
        $where = [];
        $parametros = [];

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(p.ci ILIKE :q
                         OR p.codigo ILIKE :q
                         OR p.nombres ILIKE :q
                         OR p.apellidos ILIKE :q
                         OR (p.nombres || \' \' || p.apellidos) ILIKE :q)';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        if (($filtros['estado'] ?? null) !== null) {
            $where[] = 'p.estado = CAST(:estado AS estado_paciente)';
            $parametros['estado'] = $filtros['estado'];
        }

        if (array_key_exists('medico_ids', $filtros) && $filtros['medico_ids'] !== null) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) $filtros['medico_ids']),
                static fn (int $id): bool => $id > 0
            )));

            if ($ids === []) {
                $where[] = '1 = 0';
            } else {
                $placeholders = [];
                foreach ($ids as $index => $id) {
                    $name = 'scope_medico_' . $index;
                    $placeholders[] = ':' . $name;
                    $parametros[$name] = $id;
                }

                $where[] = 'EXISTS (
                    SELECT 1
                    FROM citas c
                    WHERE c.paciente_id = p.id
                      AND c.medico_id IN (' . implode(', ', $placeholders) . ')
                )';
            }
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM pacientes p' . $whereSql);
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT p.*,
                       (SELECT MAX(c.fecha) FROM citas c WHERE c.paciente_id = p.id) AS ultima_cita
                FROM pacientes p'
            . $whereSql
            . ' ORDER BY p.apellidos ASC, p.nombres ASC, p.id ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return [
            'data' => $statement->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Alta completa desde recepción. El código PAC-#### lo genera el trigger.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function crearAdmin(array $datos): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO pacientes (
                 ci, nombres, apellidos, fecha_nacimiento, sexo, telefono, email,
                 direccion, tipo_sangre, alergias, enfermedades_cronicas, medicamentos,
                 antecedentes, emergencia_nombre, emergencia_parentesco, emergencia_telefono
             ) VALUES (
                 :ci, :nombres, :apellidos, CAST(:fecha_nacimiento AS date),
                 CAST(:sexo AS sexo), :telefono, :email, :direccion, :tipo_sangre,
                 :alergias, :enfermedades_cronicas, :medicamentos, :antecedentes,
                 :emergencia_nombre, :emergencia_parentesco, :emergencia_telefono
             )
             RETURNING *'
        );
        $statement->execute([
            'ci' => $datos['ci'],
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'sexo' => $datos['sexo'],
            'telefono' => $datos['telefono'] ?? null,
            'email' => $datos['email'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'tipo_sangre' => $datos['tipo_sangre'] ?? null,
            'alergias' => $datos['alergias'] ?? null,
            'enfermedades_cronicas' => $datos['enfermedades_cronicas'] ?? null,
            'medicamentos' => $datos['medicamentos'] ?? null,
            'antecedentes' => $datos['antecedentes'] ?? null,
            'emergencia_nombre' => $datos['emergencia_nombre'] ?? null,
            'emergencia_parentesco' => $datos['emergencia_parentesco'] ?? null,
            'emergencia_telefono' => $datos['emergencia_telefono'] ?? null,
        ]);

        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    /** @param array<string, mixed> $datos */
    public function actualizarAdmin(int $id, array $datos): void
    {
        $asignaciones = [];
        $parametros = ['id' => $id];

        foreach ([
            'ci',
            'nombres',
            'apellidos',
            'telefono',
            'email',
            'direccion',
            'tipo_sangre',
            'alergias',
            'enfermedades_cronicas',
            'medicamentos',
            'antecedentes',
            'emergencia_nombre',
            'emergencia_parentesco',
            'emergencia_telefono',
        ] as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = $campo . ' = :' . $campo;
                $parametros[$campo] = $datos[$campo];
            }
        }

        if (array_key_exists('fecha_nacimiento', $datos)) {
            $asignaciones[] = 'fecha_nacimiento = CAST(:fecha_nacimiento AS date)';
            $parametros['fecha_nacimiento'] = $datos['fecha_nacimiento'];
        }

        if (array_key_exists('sexo', $datos)) {
            $asignaciones[] = 'sexo = CAST(:sexo AS sexo)';
            $parametros['sexo'] = $datos['sexo'];
        }

        if ($asignaciones === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE pacientes SET ' . implode(', ', $asignaciones) . ' WHERE id = :id'
        );
        $statement->execute($parametros);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE pacientes SET estado = CAST(:estado AS estado_paciente) WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'estado' => $estado]);
    }

    public function contarCitasFuturas(int $id): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM citas
             WHERE paciente_id = :id
               AND fecha >= CURRENT_DATE
               AND estado IN ('programada', 'confirmada')"
        );
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
    }

    public function contarCitas(int $id): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM citas WHERE paciente_id = :id'
        );
        $statement->execute(['id' => $id]);

        return (int) $statement->fetchColumn();
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

    /**
     * Alta de paciente sin cuenta (kiosco/recepción). El código PAC-#### lo
     * genera el trigger de base de datos.
     *
     * @param array{ci: string, nombres: string, apellidos: string, fecha_nacimiento: string, sexo: string, telefono: ?string} $datos
     * @return array<string, mixed>
     */
    public function crear(array $datos): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO pacientes (
                 ci, nombres, apellidos, fecha_nacimiento, sexo, telefono
             ) VALUES (
                 :ci, :nombres, :apellidos,
                 CAST(:fecha_nacimiento AS date),
                 CAST(:sexo AS sexo),
                 :telefono
             )
             RETURNING *'
        );
        $statement->execute([
            'ci' => $datos['ci'],
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
            'sexo' => $datos['sexo'],
            'telefono' => $datos['telefono'],
        ]);

        $row = $statement->fetch();

        return $row === false ? [] : $row;
    }

    public function vincularUsuario(int $pacienteId, int $usuarioId): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE pacientes SET usuario_id = :usuario_id WHERE id = :id'
        );
        $statement->execute(['id' => $pacienteId, 'usuario_id' => $usuarioId]);
    }
}
