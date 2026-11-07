<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Órdenes de laboratorio/imagen, su detalle de estudios y resultados
 * (Spec 10 / M-31).
 */
final class OrdenModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array<int, array<string, mixed>> $detalle
     */
    public function crear(int $consultaId, int $pacienteId, int $medicoId, string $tipo, array $detalle): int
    {
        return DB::transaction(function () use ($consultaId, $pacienteId, $medicoId, $tipo, $detalle): int {
            $codigoAcceso = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $examenes = new ExamenModel($this->pdo);

            $statement = $this->pdo->prepare(
                'INSERT INTO ordenes (consulta_id, paciente_id, medico_id, tipo, codigo_acceso)
                 VALUES (:consulta_id, :paciente_id, :medico_id, CAST(:tipo AS tipo_orden), :codigo_acceso)
                 RETURNING id'
            );
            $statement->execute([
                'consulta_id' => $consultaId,
                'paciente_id' => $pacienteId,
                'medico_id' => $medicoId,
                'tipo' => $tipo,
                'codigo_acceso' => $codigoAcceso,
            ]);
            $id = (int) $statement->fetchColumn();

            $detalleStatement = $this->pdo->prepare(
                'INSERT INTO orden_detalle (orden_id, examen_id, estudio, observaciones, precio)
                 VALUES (:orden_id, :examen_id, :estudio, :observaciones, :precio)'
            );

            $total = 0.0;

            foreach ($detalle as $item) {
                $examen = null;

                if (!empty($item['examen_id'])) {
                    $examen = $examenes->porId((int) $item['examen_id']);
                }

                if ($examen === null && !empty($item['estudio'])) {
                    $examen = $examenes->porNombre((string) $item['estudio']);
                }

                $estudio = trim((string) ($item['estudio'] ?? ($examen['nombre'] ?? '')));
                $precio = $examen !== null
                    ? (float) $examen['precio']
                    : round(max(0.0, (float) ($item['precio'] ?? 0)), 2);
                $total += $precio;

                $detalleStatement->execute([
                    'orden_id' => $id,
                    'examen_id' => $examen !== null ? (int) $examen['id'] : null,
                    'estudio' => $estudio,
                    'observaciones' => $item['observaciones'] ?? null,
                    'precio' => number_format($precio, 2, '.', ''),
                ]);
            }

            $this->pdo->prepare('UPDATE ordenes SET total = :total WHERE id = :id')
                ->execute(['id' => $id, 'total' => number_format($total, 2, '.', '')]);

            return $id;
        });
    }

    /**
     * Órdenes pendientes de cobro en recepción.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendientesCobro(int $limite = 100): array
    {
        $statement = $this->pdo->prepare(
            $this->baseSql()
            . ' WHERE o.total > COALESCE((
                    SELECT SUM(pg.monto) FROM pagos pg
                    WHERE pg.orden_id = o.id AND pg.estado = \'confirmado\'
                ), 0)
              ORDER BY o.fecha DESC, o.id DESC
              LIMIT ' . max(1, min(500, $limite))
        );
        $statement->execute();

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }


    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE o.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /** @return array<string, mixed>|null */
    public function porCodigo(string $codigo): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE o.codigo = :codigo LIMIT 1');
        $statement->execute(['codigo' => $codigo]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    public function marcarEnviado(int $id): void
    {
        $this->pdo->prepare('UPDATE ordenes SET enviado_whatsapp = TRUE WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    private function detalleDe(int $ordenId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, examen_id, estudio, observaciones, precio
             FROM orden_detalle
             WHERE orden_id = :orden_id
             ORDER BY id'
        );
        $statement->execute(['orden_id' => $ordenId]);

        return array_map(
            static function (array $fila): array {
                $fila['id'] = (int) $fila['id'];
                $fila['examen_id'] = $fila['examen_id'] === null ? null : (int) $fila['examen_id'];
                $fila['precio'] = (float) $fila['precio'];

                return $fila;
            },
            $statement->fetchAll()
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function resultadosDe(int $ordenId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, archivo, texto, revisado, fecha
             FROM resultados
             WHERE orden_id = :orden_id
             ORDER BY fecha DESC, id DESC'
        );
        $statement->execute(['orden_id' => $ordenId]);

        return array_map(
            static function (array $fila): array {
                $fila['id'] = (int) $fila['id'];
                $fila['revisado'] = (bool) $fila['revisado'];
                $fila['fecha'] = (string) $fila['fecha'];

                return $fila;
            },
            $statement->fetchAll()
        );
    }

    private function baseSql(): string
    {
        return "SELECT o.id, o.codigo, o.codigo_acceso, o.consulta_id, o.paciente_id, o.medico_id,
                    o.tipo, o.enviado_whatsapp, o.fecha, o.total,
                    p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                    COALESCE((
                        SELECT SUM(pg.monto) FROM pagos pg
                        WHERE pg.orden_id = o.id AND pg.estado = 'confirmado'
                    ), 0) AS pagado
             FROM ordenes o
             JOIN pacientes p ON p.id = o.paciente_id
             JOIN medicos m ON m.id = o.medico_id";
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizar(array $row): array
    {
        $pacienteNombre = trim(
            (string) ($row['paciente_nombres'] ?? '') . ' ' . (string) ($row['paciente_apellidos'] ?? '')
        );
        $medicoNombre = trim(
            (string) ($row['medico_nombres'] ?? '') . ' ' . (string) ($row['medico_apellidos'] ?? '')
        );

        return [
            'id' => (int) $row['id'],
            'codigo' => (string) $row['codigo'],
            'codigo_acceso' => (string) $row['codigo_acceso'],
            'consulta_id' => (int) $row['consulta_id'],
            'paciente_id' => (int) $row['paciente_id'],
            'medico_id' => (int) $row['medico_id'],
            'tipo' => (string) $row['tipo'],
            'enviado_whatsapp' => (bool) $row['enviado_whatsapp'],
            'fecha' => (string) $row['fecha'],
            'total' => (float) ($row['total'] ?? 0),
            'pagado' => (float) ($row['pagado'] ?? 0),
            'saldo' => round(max(0.0, (float) ($row['total'] ?? 0) - (float) ($row['pagado'] ?? 0)), 2),
            'estado_pago' => ((float) ($row['total'] ?? 0) <= (float) ($row['pagado'] ?? 0))
                ? 'pagado'
                : ((float) ($row['pagado'] ?? 0) > 0 ? 'parcial' : 'pendiente'),
            'paciente' => [
                'id' => (int) $row['paciente_id'],
                'nombre_completo' => $pacienteNombre,
                'ci' => (string) ($row['paciente_ci'] ?? ''),
                'telefono' => $row['paciente_telefono'] !== null ? (string) $row['paciente_telefono'] : null,
            ],
            'medico' => [
                'id' => (int) $row['medico_id'],
                'nombre_completo' => $medicoNombre !== '' ? 'Dr(a). ' . $medicoNombre : '',
            ],
            'detalle' => $this->detalleDe((int) $row['id']),
            'resultados' => $this->resultadosDe((int) $row['id']),
        ];
    }
}
