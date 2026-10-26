<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Recetas y su detalle de medicamentos (Spec 10 / M-31).
 */
final class RecetaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array<int, array<string, mixed>> $detalle
     */
    public function crear(int $consultaId, int $pacienteId, int $medicoId, array $detalle): int
    {
        return DB::transaction(function () use ($consultaId, $pacienteId, $medicoId, $detalle): int {
            $codigoAcceso = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $statement = $this->pdo->prepare(
                'INSERT INTO recetas (consulta_id, paciente_id, medico_id, codigo_acceso)
                 VALUES (:consulta_id, :paciente_id, :medico_id, :codigo_acceso)
                 RETURNING id'
            );
            $statement->execute([
                'consulta_id' => $consultaId,
                'paciente_id' => $pacienteId,
                'medico_id' => $medicoId,
                'codigo_acceso' => $codigoAcceso,
            ]);
            $id = (int) $statement->fetchColumn();

            $detalleStatement = $this->pdo->prepare(
                'INSERT INTO receta_detalle (
                     receta_id, medicamento, dosis, via, frecuencia, duracion, instrucciones
                 ) VALUES (
                     :receta_id, :medicamento, :dosis, :via, :frecuencia, :duracion, :instrucciones
                 )'
            );

            foreach ($detalle as $item) {
                $detalleStatement->execute([
                    'receta_id' => $id,
                    'medicamento' => $item['medicamento'],
                    'dosis' => $item['dosis'] ?? null,
                    'via' => $item['via'] ?? null,
                    'frecuencia' => $item['frecuencia'] ?? null,
                    'duracion' => $item['duracion'] ?? null,
                    'instrucciones' => $item['instrucciones'] ?? null,
                ]);
            }

            return $id;
        });
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE r.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /** @return array<string, mixed>|null */
    public function porCodigo(string $codigo): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE r.codigo = :codigo LIMIT 1');
        $statement->execute(['codigo' => $codigo]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    public function marcarEnviado(int $id): void
    {
        $this->pdo->prepare('UPDATE recetas SET enviado_whatsapp = TRUE WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /** @return array<int, array<string, mixed>> */
    private function detalleDe(int $recetaId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, medicamento, dosis, via, frecuencia, duracion, instrucciones
             FROM receta_detalle
             WHERE receta_id = :receta_id
             ORDER BY id'
        );
        $statement->execute(['receta_id' => $recetaId]);

        return array_map(
            static function (array $fila): array {
                $fila['id'] = (int) $fila['id'];

                return $fila;
            },
            $statement->fetchAll()
        );
    }

    private function baseSql(): string
    {
        return "SELECT r.id, r.codigo, r.codigo_acceso, r.consulta_id, r.paciente_id, r.medico_id,
                    r.enviado_whatsapp, r.fecha,
                    p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                    co.diagnostico AS consulta_diagnostico
             FROM recetas r
             JOIN pacientes p ON p.id = r.paciente_id
             JOIN medicos m ON m.id = r.medico_id
             JOIN consultas co ON co.id = r.consulta_id";
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
            'enviado_whatsapp' => (bool) $row['enviado_whatsapp'],
            'fecha' => (string) $row['fecha'],
            'diagnostico' => (string) ($row['consulta_diagnostico'] ?? ''),
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
        ];
    }
}
