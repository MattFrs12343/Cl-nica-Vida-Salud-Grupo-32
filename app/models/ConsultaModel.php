<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Consultas médicas e historia clínica (Spec 10).
 *
 * La lectura queda sujeta a RLS: el médico solo ve sus propias consultas y el
 * paciente solo las suyas. Las escrituras se incorporan en M-31.
 */
final class ConsultaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Historia clínica cronológica de un paciente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historiaPaciente(int $pacienteId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT co.id, co.cita_id, co.paciente_id, co.medico_id,
                    co.motivo, co.anamnesis, co.signos_vitales, co.examen_fisico,
                    co.diagnostico, co.tratamiento, co.indicaciones, co.fecha,
                    c.codigo AS cita_codigo, c.fecha AS cita_fecha,
                    c.hora_inicio AS cita_hora_inicio, c.estado AS cita_estado,
                    m.codigo AS medico_codigo, m.nombres AS medico_nombres,
                    m.apellidos AS medico_apellidos,
                    e.id AS especialidad_id, e.nombre AS especialidad_nombre
             FROM consultas co
             JOIN citas c ON c.id = co.cita_id
             LEFT JOIN medicos m ON m.id = co.medico_id
             LEFT JOIN especialidades e ON e.id = c.especialidad_id
             WHERE co.paciente_id = :paciente_id
             ORDER BY co.fecha DESC, co.id DESC"
        );
        $statement->execute(['paciente_id' => $pacienteId]);

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizar(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['cita_id'] = (int) $row['cita_id'];
        $row['paciente_id'] = (int) $row['paciente_id'];
        $row['medico_id'] = (int) $row['medico_id'];

        $signos = $row['signos_vitales'] ?? null;

        if (is_string($signos)) {
            $decodificados = json_decode($signos, true);
            $signos = is_array($decodificados) ? $decodificados : null;
        }

        $row['signos_vitales'] = is_array($signos) ? $signos : null;
        $row['cita_hora_inicio'] = substr((string) ($row['cita_hora_inicio'] ?? ''), 0, 5);

        $medicoNombre = trim(
            (string) ($row['medico_nombres'] ?? '') . ' ' . (string) ($row['medico_apellidos'] ?? '')
        );

        $row['cita'] = [
            'id' => $row['cita_id'],
            'codigo' => (string) ($row['cita_codigo'] ?? ''),
            'fecha' => $row['cita_fecha'] ?? null,
            'hora_inicio' => $row['cita_hora_inicio'],
            'estado' => (string) ($row['cita_estado'] ?? ''),
        ];
        $row['medico'] = [
            'id' => $row['medico_id'],
            'codigo' => (string) ($row['medico_codigo'] ?? ''),
            'nombre_completo' => $medicoNombre !== '' ? 'Dr. ' . $medicoNombre : '',
        ];
        $row['especialidad'] = [
            'id' => (int) ($row['especialidad_id'] ?? 0),
            'nombre' => (string) ($row['especialidad_nombre'] ?? ''),
        ];

        unset(
            $row['cita_codigo'],
            $row['cita_fecha'],
            $row['cita_hora_inicio'],
            $row['cita_estado'],
            $row['medico_codigo'],
            $row['medico_nombres'],
            $row['medico_apellidos'],
            $row['especialidad_id'],
            $row['especialidad_nombre']
        );

        return $row;
    }
}
