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
                 paciente_id, medico_id, registrado_por, monto, metodo, referencia_paciente
             ) VALUES (
                 :paciente_id, :medico_id, :registrado_por, :monto,
                 CAST(:metodo AS metodo_pago), :referencia_paciente
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
        ]);

        return (int) $statement->fetchColumn();
    }

    public function pacienteExiste(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM pacientes WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
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
                       pac.codigo AS paciente_codigo, pac.ci AS paciente_ci,
                       pac.nombres AS paciente_nombres, pac.apellidos AS paciente_apellidos,
                       pac.telefono AS paciente_telefono,
                       m.codigo AS medico_codigo, m.nombres AS medico_nombres,
                       m.apellidos AS medico_apellidos,
                       u.username AS registrado_username
                FROM pagos p
                JOIN pacientes pac ON pac.id = p.paciente_id
                JOIN medicos m ON m.id = p.medico_id
                LEFT JOIN usuarios u ON u.id = p.registrado_por';
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
