<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Consultas médicas e historia clínica (Spec 10).
 *
 * La lectura queda sujeta a RLS: el médico solo ve sus propias consultas y el
 * paciente solo las suyas. Las escrituras (M-31) también corren bajo el
 * contexto RLS del médico autenticado.
 */
final class ConsultaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO consultas (
                 cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales,
                 examen_fisico, diagnostico, tratamiento, indicaciones
             ) VALUES (
                 :cita_id, :paciente_id, :medico_id, :motivo, :anamnesis,
                 CAST(:signos_vitales AS jsonb),
                 :examen_fisico, :diagnostico, :tratamiento, :indicaciones
             )
             RETURNING id'
        );
        $statement->execute([
            'cita_id' => $datos['cita_id'],
            'paciente_id' => $datos['paciente_id'],
            'medico_id' => $datos['medico_id'],
            'motivo' => $datos['motivo'] ?? null,
            'anamnesis' => $datos['anamnesis'] ?? null,
            'signos_vitales' => $datos['signos_vitales'] === null
                ? null
                : json_encode($datos['signos_vitales'], JSON_UNESCAPED_UNICODE),
            'examen_fisico' => $datos['examen_fisico'] ?? null,
            'diagnostico' => $datos['diagnostico'] ?? null,
            'tratamiento' => $datos['tratamiento'] ?? null,
            'indicaciones' => $datos['indicaciones'] ?? null,
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE co.id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /** @return array<string, mixed>|null */
    public function porCitaId(int $citaId): ?array
    {
        $statement = $this->pdo->prepare($this->baseSql() . ' WHERE co.cita_id = :cita_id LIMIT 1');
        $statement->execute(['cita_id' => $citaId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /**
     * Historia clínica cronológica de un paciente.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historiaPaciente(int $pacienteId): array
    {
        $statement = $this->pdo->prepare(
            $this->baseSql() . ' WHERE co.paciente_id = :paciente_id
             ORDER BY co.fecha DESC, co.id DESC'
        );
        $statement->execute(['paciente_id' => $pacienteId]);

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    private function baseSql(): string
    {
        return "SELECT co.id, co.cita_id, co.paciente_id, co.medico_id,
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
             LEFT JOIN especialidades e ON e.id = c.especialidad_id";
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
