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

    /**
     * Listado administrativo de citas. El alcance por rol se aplica con
     * `medico_ids`/`paciente_id` desde el controlador.
     *
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listarAdmin(array $filtros, int $page, int $perPage): array
    {
        $parametros = [];
        $where = $this->whereAdmin($filtros, $parametros);
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM citas c
             JOIN pacientes p ON p.id = c.paciente_id
             JOIN medicos m ON m.id = c.medico_id
             JOIN especialidades e ON e.id = c.especialidad_id'
            . $whereSql
        );
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $sortMap = [
            'fecha' => 'c.fecha',
            'hora' => 'c.hora_inicio',
            'codigo' => 'c.codigo',
            'estado' => 'c.estado',
            'created_at' => 'c.created_at',
        ];
        $sort = $sortMap[(string) ($filtros['sort'] ?? 'fecha')] ?? 'c.fecha';
        $direction = strtoupper((string) ($filtros['direction'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ($page - 1) * $perPage;

        $sql = $this->selectAdminSql()
            . $whereSql
            . ' ORDER BY ' . $sort . ' ' . $direction
            . ', c.hora_inicio ' . $direction
            . ', c.id ' . $direction
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return [
            'data' => array_map([$this, 'normalizarCita'], $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findAdminById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            $this->selectAdminSql() . ' WHERE c.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizarCita($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function agenda(int $medicoId, string $desde, string $hasta, ?string $estado = null): array
    {
        $parametros = [];
        $where = $this->whereAdmin([
            'medico_id' => $medicoId,
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $estado,
        ], $parametros);

        $statement = $this->pdo->prepare(
            $this->selectAdminSql()
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY c.fecha ASC, c.hora_inicio ASC, c.id ASC'
        );
        $statement->execute($parametros);

        return array_map([$this, 'normalizarCita'], $statement->fetchAll());
    }

    /**
     * @return array{total: int, programadas: int, confirmadas: int, atendidas: int, canceladas: int, inasistencias: int}
     */
    public function contadoresAgenda(int $medicoId, string $desde, string $hasta): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE estado = 'programada') AS programadas,
                    COUNT(*) FILTER (WHERE estado = 'confirmada') AS confirmadas,
                    COUNT(*) FILTER (WHERE estado = 'atendida') AS atendidas,
                    COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
                    COUNT(*) FILTER (WHERE estado = 'inasistencia') AS inasistencias
             FROM citas
             WHERE medico_id = :medico_id
               AND fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)"
        );
        $statement->execute([
            'medico_id' => $medicoId,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'programadas' => (int) ($row['programadas'] ?? 0),
            'confirmadas' => (int) ($row['confirmadas'] ?? 0),
            'atendidas' => (int) ($row['atendidas'] ?? 0),
            'canceladas' => (int) ($row['canceladas'] ?? 0),
            'inasistencias' => (int) ($row['inasistencias'] ?? 0),
        ];
    }

    /**
     * Historial de un paciente, incluyendo canceladas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historialPaciente(
        int $pacienteId,
        ?string $desde = null,
        ?string $hasta = null,
        ?string $estado = null
    ): array {
        $resultado = $this->listarAdmin([
            'paciente_id' => $pacienteId,
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $estado,
            'sort' => 'fecha',
            'direction' => 'desc',
        ], 1, 200);

        return $resultado['data'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historialEstados(int $citaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT h.id, h.cita_id, h.estado_anterior, h.estado_nuevo,
                    h.usuario_id, h.observacion, h.fecha,
                    u.username, u.email, u.rol
             FROM citas_estado_historial h
             LEFT JOIN usuarios u ON u.id = h.usuario_id
             WHERE h.cita_id = :cita_id
             ORDER BY h.fecha DESC, h.id DESC'
        );
        $statement->execute(['cita_id' => $citaId]);

        return array_map([$this, 'normalizarHistorial'], $statement->fetchAll());
    }

    /**
     * Verifica si un paciente tiene alguna cita con alguno de los médicos
     * indicados. Se usa para autorizar el historial a médicos/recepción.
     *
     * @param array<int, int> $medicoIds
     */
    public function tieneCitaConPaciente(int $pacienteId, array $medicoIds): bool
    {
        $medicoIds = array_values(array_unique(array_filter(
            array_map('intval', $medicoIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($medicoIds === []) {
            return false;
        }

        $parametros = ['paciente_id' => $pacienteId];
        $placeholders = $this->placeholders($medicoIds, 'medico_cita', $parametros);

        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM citas
             WHERE paciente_id = :paciente_id
               AND medico_id IN (' . $placeholders . ')
             LIMIT 1'
        );
        $statement->execute($parametros);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $filtros
     * @param array<string, mixed> $parametros
     * @return array<int, string>
     */
    private function whereAdmin(array $filtros, array &$parametros): array
    {
        $where = [];

        if (($filtros['fecha'] ?? null) !== null) {
            $where[] = 'c.fecha = CAST(:fecha AS date)';
            $parametros['fecha'] = $filtros['fecha'];
        }

        if (($filtros['desde'] ?? null) !== null) {
            $where[] = 'c.fecha >= CAST(:desde AS date)';
            $parametros['desde'] = $filtros['desde'];
        }

        if (($filtros['hasta'] ?? null) !== null) {
            $where[] = 'c.fecha <= CAST(:hasta AS date)';
            $parametros['hasta'] = $filtros['hasta'];
        }

        if (($filtros['medico_id'] ?? null) !== null) {
            $where[] = 'c.medico_id = :medico_id';
            $parametros['medico_id'] = $filtros['medico_id'];
        }

        if (array_key_exists('medico_ids', $filtros) && $filtros['medico_ids'] !== null) {
            $ids = array_values(array_unique(array_filter(
                array_map('intval', (array) $filtros['medico_ids']),
                static fn (int $id): bool => $id > 0
            )));

            if ($ids === []) {
                $where[] = '1 = 0';
            } else {
                $placeholders = $this->placeholders($ids, 'scope_medico', $parametros);
                $where[] = 'c.medico_id IN (' . $placeholders . ')';
            }
        }

        if (($filtros['paciente_id'] ?? null) !== null) {
            $where[] = 'c.paciente_id = :paciente_id';
            $parametros['paciente_id'] = $filtros['paciente_id'];
        }

        if (($filtros['especialidad_id'] ?? null) !== null) {
            $where[] = 'c.especialidad_id = :especialidad_id';
            $parametros['especialidad_id'] = $filtros['especialidad_id'];
        }

        if (($filtros['estado'] ?? null) !== null) {
            $where[] = 'c.estado = CAST(:estado AS estado_cita)';
            $parametros['estado'] = $filtros['estado'];
        }

        if (($filtros['origen'] ?? null) !== null) {
            $where[] = 'c.origen = CAST(:origen AS origen_cita)';
            $parametros['origen'] = $filtros['origen'];
        }

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(c.codigo ILIKE :q
                         OR p.ci ILIKE :q
                         OR p.nombres ILIKE :q
                         OR p.apellidos ILIKE :q
                         OR m.nombres ILIKE :q
                         OR m.apellidos ILIKE :q)';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        return $where;
    }

    /** @param array<string, mixed> $parametros @param array<int, int> $ids */
    private function placeholders(array $ids, string $prefix, array &$parametros): string
    {
        $placeholders = [];

        foreach (array_values($ids) as $index => $id) {
            $name = $prefix . '_' . $index;
            $placeholders[] = ':' . $name;
            $parametros[$name] = $id;
        }

        return implode(', ', $placeholders);
    }

    private function selectAdminSql(): string
    {
        return "SELECT c.id, c.codigo, c.paciente_id, c.medico_id, c.especialidad_id,
                       c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.origen,
                       c.es_prioritaria, c.es_reconsulta, c.reconsulta_tipo,
                       c.cita_origen_id, c.motivo, c.motivo_cancelacion,
                       c.fecha_registro, c.created_at, c.updated_at,
                       p.codigo AS paciente_codigo, p.ci AS paciente_ci,
                       p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                       p.telefono AS paciente_telefono, p.email AS paciente_email,
                       m.codigo AS medico_codigo, m.nombres AS medico_nombres,
                       m.apellidos AS medico_apellidos,
                       e.nombre AS especialidad_nombre
                FROM citas c
                JOIN pacientes p ON p.id = c.paciente_id
                JOIN medicos m ON m.id = c.medico_id
                JOIN especialidades e ON e.id = c.especialidad_id";
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarCita(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['paciente_id'] = (int) $row['paciente_id'];
        $row['medico_id'] = (int) $row['medico_id'];
        $row['especialidad_id'] = (int) $row['especialidad_id'];
        $row['hora_inicio'] = substr((string) $row['hora_inicio'], 0, 5);
        $row['hora_fin'] = substr((string) $row['hora_fin'], 0, 5);
        $row['es_prioritaria'] = (bool) $row['es_prioritaria'];
        $row['es_reconsulta'] = (bool) $row['es_reconsulta'];
        $row['cita_origen_id'] = $row['cita_origen_id'] === null
            ? null
            : (int) $row['cita_origen_id'];

        $pacienteNombre = trim((string) $row['paciente_nombres'] . ' ' . (string) $row['paciente_apellidos']);
        $medicoNombre = trim((string) $row['medico_nombres'] . ' ' . (string) $row['medico_apellidos']);

        $row['paciente'] = [
            'id' => $row['paciente_id'],
            'codigo' => (string) $row['paciente_codigo'],
            'ci' => (string) $row['paciente_ci'],
            'nombres' => (string) $row['paciente_nombres'],
            'apellidos' => (string) $row['paciente_apellidos'],
            'nombre_completo' => $pacienteNombre,
            'telefono' => $row['paciente_telefono'],
            'email' => $row['paciente_email'],
        ];
        $row['medico'] = [
            'id' => $row['medico_id'],
            'codigo' => (string) $row['medico_codigo'],
            'nombres' => (string) $row['medico_nombres'],
            'apellidos' => (string) $row['medico_apellidos'],
            'nombre_completo' => $medicoNombre !== '' ? 'Dr. ' . $medicoNombre : '',
        ];
        $row['especialidad'] = [
            'id' => $row['especialidad_id'],
            'nombre' => (string) $row['especialidad_nombre'],
        ];

        unset(
            $row['paciente_codigo'],
            $row['paciente_ci'],
            $row['paciente_nombres'],
            $row['paciente_apellidos'],
            $row['paciente_telefono'],
            $row['paciente_email'],
            $row['medico_codigo'],
            $row['medico_nombres'],
            $row['medico_apellidos'],
            $row['especialidad_nombre']
        );

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizarHistorial(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['cita_id'] = (int) $row['cita_id'];
        $row['usuario_id'] = $row['usuario_id'] === null ? null : (int) $row['usuario_id'];
        $row['usuario'] = $row['usuario_id'] === null
            ? null
            : [
                'id' => $row['usuario_id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'rol' => $row['rol'],
            ];

        unset($row['username'], $row['email'], $row['rol']);

        return $row;
    }
}
