<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Movimientos de caja (M-28).
 *
 * Los pagos se asocian a un paciente y al mAcdico de la atenciA3n. El alcance
 * por rol se aplica en el controlador mediante `medico_ids`; este modelo solo
 * traduce filtros y arma la presentaciA3n que consume el panel.
 */
final class PagoModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array{
     *     medico?: int|null,
     *     desde?: string|null,
     *     hasta?: string|null,
     *     medico_ids?: array<int, int>|null
     * } $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $page, int $perPage): array
    {
        [$where, $parametros] = $this->condiciones($filtros);

        $conteo = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM pagos p
             JOIN pacientes pac ON pac.id = p.paciente_id
             JOIN medicos m ON m.id = p.medico_id'
            . $where
        );
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        $statement = $this->pdo->prepare(
            $this->sqlSelect()
            . $where
            . ' ORDER BY p.fecha DESC, p.id DESC
                LIMIT :limit OFFSET :offset'
        );

        foreach ($parametros as $clave => $valor) {
            $statement->bindValue(':' . $clave, $valor);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $filas = $statement->fetchAll() ?: [];

        return [
            'data' => array_map(fn (array $fila): array => $this->normalizar($fila), $filas),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            $this->sqlSelect() . ' WHERE p.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO pagos (
                 paciente_id, medico_id, registrado_por, monto, metodo, referencia_paciente,
                 cita_id, orden_id, tipo, estado, referencia, confirmado_por, confirmado_en,
                 reembolso_de
             ) VALUES (
                 :paciente_id, :medico_id, :registrado_por, :monto,
                 CAST(:metodo AS metodo_pago), :referencia_paciente,
                 :cita_id, :orden_id, :tipo, :estado, :referencia, :confirmado_por,
                 CAST(:confirmado_en AS timestamptz), :reembolso_de
             )
             RETURNING id'
        );
        $statement->execute([
            'paciente_id' => (int) $datos['paciente_id'],
            'medico_id' => (int) $datos['medico_id'],
            'registrado_por' => (int) $datos['registrado_por'],
            'monto' => (string) $datos['monto'],
            'metodo' => (string) $datos['metodo'],
            'referencia_paciente' => $datos['referencia_paciente'] ?? null,
            'cita_id' => isset($datos['cita_id']) && $datos['cita_id'] !== null ? (int) $datos['cita_id'] : null,
            'orden_id' => isset($datos['orden_id']) && $datos['orden_id'] !== null ? (int) $datos['orden_id'] : null,
            'tipo' => (string) ($datos['tipo'] ?? 'consulta'),
            'estado' => (string) ($datos['estado'] ?? 'confirmado'),
            'referencia' => $datos['referencia'] ?? null,
            'confirmado_por' => isset($datos['confirmado_por']) && $datos['confirmado_por'] !== null
                ? (int) $datos['confirmado_por']
                : null,
            'confirmado_en' => $datos['confirmado_en'] ?? null,
            'reembolso_de' => isset($datos['reembolso_de']) && $datos['reembolso_de'] !== null
                ? (int) $datos['reembolso_de']
                : null,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function porCita(int $citaId): array
    {
        $statement = $this->pdo->prepare(
            $this->sqlSelect() . ' WHERE p.cita_id = :cita_id ORDER BY p.fecha ASC, p.id ASC'
        );
        $statement->execute(['cita_id' => $citaId]);

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    /** @return array<int, array<string, mixed>> */
    public function porOrden(int $ordenId): array
    {
        $statement = $this->pdo->prepare(
            $this->sqlSelect() . ' WHERE p.orden_id = :orden_id ORDER BY p.fecha ASC, p.id ASC'
        );
        $statement->execute(['orden_id' => $ordenId]);

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    public function totalConfirmadoPorCita(int $citaId, ?string $tipo = null): float
    {
        $sql = "SELECT COALESCE(SUM(monto), 0) FROM pagos
                WHERE cita_id = :cita_id AND estado = 'confirmado'";
        $parametros = ['cita_id' => $citaId];

        if ($tipo !== null) {
            $sql .= ' AND tipo = :tipo';
            $parametros['tipo'] = $tipo;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return (float) $statement->fetchColumn();
    }

    public function totalConfirmadoPorOrden(int $ordenId): float
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(SUM(monto), 0) FROM pagos
             WHERE orden_id = :orden_id AND estado = 'confirmado'"
        );
        $statement->execute(['orden_id' => $ordenId]);

        return (float) $statement->fetchColumn();
    }


    public function pacienteExiste(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM pacientes WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function ultimoPorCita(int $citaId): ?array
    {
        $statement = $this->pdo->prepare(
            $this->sqlSelect()
            . ' WHERE p.cita_id = :cita_id ORDER BY p.fecha DESC, p.id DESC LIMIT 1'
        );
        $statement->execute(['cita_id' => $citaId]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    public function medicoActivo(int $id): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM medicos WHERE id = :id AND estado = 'activo' LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function condiciones(array $filtros): array
    {
        $where = [];
        $parametros = [];

        $medicoId = $filtros['medico'] ?? null;

        if ($medicoId !== null) {
            $where[] = 'p.medico_id = :medico';
            $parametros['medico'] = (int) $medicoId;
        }

        if (($filtros['cita_id'] ?? null) !== null) {
            $where[] = 'p.cita_id = :cita_id';
            $parametros['cita_id'] = (int) $filtros['cita_id'];
        }

        if (($filtros['orden_id'] ?? null) !== null) {
            $where[] = 'p.orden_id = :orden_id';
            $parametros['orden_id'] = (int) $filtros['orden_id'];
        }

        if (($filtros['tipo'] ?? null) !== null) {
            $where[] = 'p.tipo = :tipo';
            $parametros['tipo'] = (string) $filtros['tipo'];
        }

        if (($filtros['estado'] ?? null) !== null) {
            $where[] = 'p.estado = :estado';
            $parametros['estado'] = (string) $filtros['estado'];
        }

        if (($filtros['desde'] ?? null) !== null) {
            $where[] = 'p.fecha >= CAST(:desde AS date)';
            $parametros['desde'] = (string) $filtros['desde'];
        }

        if (($filtros['hasta'] ?? null) !== null) {
            $where[] = "p.fecha < CAST(:hasta AS date) + INTERVAL '1 day'";
            $parametros['hasta'] = (string) $filtros['hasta'];
        }

        $medicoIds = $filtros['medico_ids'] ?? null;

        if (is_array($medicoIds)) {
            if ($medicoIds === []) {
                $where[] = 'FALSE';
            } else {
                $marcadores = [];

                foreach (array_values($medicoIds) as $indice => $medico) {
                    $clave = 'scope_medico_' . $indice;
                    $marcadores[] = ':' . $clave;
                    $parametros[$clave] = (int) $medico;
                }

                $where[] = 'p.medico_id IN (' . implode(', ', $marcadores) . ')';
            }
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $parametros];
    }

    private function sqlSelect(): string
    {
        return 'SELECT p.id, p.paciente_id, p.medico_id, p.registrado_por,
                       p.monto, p.metodo, p.referencia_paciente, p.fecha,
                       p.cita_id, p.orden_id, p.tipo, p.estado, p.referencia,
                       p.confirmado_por, p.confirmado_en, p.reembolso_de,
                       pac.codigo AS paciente_codigo, pac.ci AS paciente_ci,
                       pac.nombres AS paciente_nombres, pac.apellidos AS paciente_apellidos,
                       pac.telefono AS paciente_telefono,
                       m.codigo AS medico_codigo, m.nombres AS medico_nombres,
                       m.apellidos AS medico_apellidos,
                       u.username AS registrado_username,
                       c.codigo AS cita_codigo,
                       o.codigo AS orden_codigo
                FROM pagos p
                JOIN pacientes pac ON pac.id = p.paciente_id
                JOIN medicos m ON m.id = p.medico_id
                LEFT JOIN usuarios u ON u.id = p.registrado_por
                LEFT JOIN citas c ON c.id = p.cita_id
                LEFT JOIN ordenes o ON o.id = p.orden_id';
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function normalizar(array $fila): array
    {
        $pacienteNombre = trim(
            (string) ($fila['paciente_nombres'] ?? '') . ' ' . (string) ($fila['paciente_apellidos'] ?? '')
        );
        $medicoNombre = trim(
            (string) ($fila['medico_nombres'] ?? '') . ' ' . (string) ($fila['medico_apellidos'] ?? '')
        );
        $fecha = (string) $fila['fecha'];

        return [
            'id' => (int) $fila['id'],
            'paciente_id' => (int) $fila['paciente_id'],
            'medico_id' => (int) $fila['medico_id'],
            'registrado_por_id' => (int) $fila['registrado_por'],
            'monto' => (float) $fila['monto'],
            'metodo' => (string) $fila['metodo'],
            'referencia_paciente' => $fila['referencia_paciente'],
            'fecha' => substr($fecha, 0, 10),
            'creado_at' => $fecha,
            'cita_id' => $fila['cita_id'] !== null ? (int) $fila['cita_id'] : null,
            'orden_id' => $fila['orden_id'] !== null ? (int) $fila['orden_id'] : null,
            'tipo' => (string) ($fila['tipo'] ?? 'consulta'),
            'estado' => (string) ($fila['estado'] ?? 'confirmado'),
            'referencia' => $fila['referencia'] ?? null,
            'confirmado_por_id' => $fila['confirmado_por'] !== null ? (int) $fila['confirmado_por'] : null,
            'confirmado_en' => $fila['confirmado_en'] ?? null,
            'reembolso_de' => $fila['reembolso_de'] !== null ? (int) $fila['reembolso_de'] : null,
            'cita' => $fila['cita_id'] !== null
                ? ['id' => (int) $fila['cita_id'], 'codigo' => $fila['cita_codigo'] ?? null]
                : null,
            'orden' => $fila['orden_id'] !== null
                ? ['id' => (int) $fila['orden_id'], 'codigo' => $fila['orden_codigo'] ?? null]
                : null,
            'paciente' => [
                'id' => (int) $fila['paciente_id'],
                'codigo' => (string) $fila['paciente_codigo'],
                'ci' => (string) $fila['paciente_ci'],
                'nombre_completo' => $pacienteNombre,
                'telefono' => $fila['paciente_telefono'],
            ],
            'medico' => [
                'id' => (int) $fila['medico_id'],
                'codigo' => (string) $fila['medico_codigo'],
                'nombre_completo' => $medicoNombre !== '' ? 'Dr(a). ' . $medicoNombre : '',
            ],
            'registrado_por' => [
                'id' => (int) $fila['registrado_por'],
                'username' => $fila['registrado_username'],
                'nombre_completo' => $fila['registrado_username'],
            ],
            'especialidad' => null,
        ];
    }
}
