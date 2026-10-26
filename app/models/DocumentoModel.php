<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Documentos médicos: certificados, informes, constancias e interconsultas
 * (Spec 10 / M-31).
 */
final class DocumentoModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    public function crear(
        int $consultaId,
        int $pacienteId,
        int $medicoId,
        string $tipo,
        ?string $contenido
    ): int {
        $codigoAcceso = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $statement = $this->pdo->prepare(
            'INSERT INTO documentos (
                 consulta_id, paciente_id, medico_id, tipo, contenido, codigo_acceso
             ) VALUES (
                 :consulta_id, :paciente_id, :medico_id, CAST(:tipo AS tipo_documento),
                 :contenido, :codigo_acceso
             )
             RETURNING id'
        );
        $statement->execute([
            'consulta_id' => $consultaId,
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'tipo' => $tipo,
            'contenido' => $contenido,
            'codigo_acceso' => $codigoAcceso,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE d.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /** @return array<string, mixed>|null */
    public function porCodigo(string $codigo): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE d.codigo = :codigo LIMIT 1');
        $statement->execute(['codigo' => $codigo]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    public function marcarEnviado(int $id): void
    {
        $this->pdo->prepare('UPDATE documentos SET enviado_whatsapp = TRUE WHERE id = :id')
            ->execute(['id' => $id]);
    }

    private function baseSql(): string
    {
        return "SELECT d.id, d.codigo, d.codigo_acceso, d.consulta_id, d.paciente_id, d.medico_id,
                    d.tipo, d.contenido, d.enviado_whatsapp, d.fecha,
                    p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                    p.ci AS paciente_ci, p.telefono AS paciente_telefono,
                    m.nombres AS medico_nombres, m.apellidos AS medico_apellidos
             FROM documentos d
             JOIN pacientes p ON p.id = d.paciente_id
             JOIN medicos m ON m.id = d.medico_id";
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
            'contenido' => $row['contenido'] !== null ? (string) $row['contenido'] : null,
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
        ];
    }
}
