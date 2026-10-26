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
                'INSERT INTO orden_detalle (orden_id, estudio, observaciones)
                 VALUES (:orden_id, :estudio, :observaciones)'
            );

            foreach ($detalle as $item) {
                $detalleStatement->execute([
                    'orden_id' => $id,
                    'estudio' => $item['estudio'],
                    'observaciones' => $item['observaciones'] ?? null,
                ]);
            }

            return $id;
        });
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
            'SELECT id, estudio, observaciones
             FROM orden_detalle
             WHERE orden_id = :orden_id
             ORDER BY id'
        );
        $statement->execute(['orden_id' => $ordenId]);

        return array_map(
            static function (array $fila): array {
                $fila['id'] = (int) $fila['id'];

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
                    o.tipo, o.enviado_whatsapp, o.fecha,
                    p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos
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
