<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class ReporteModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function citasPorMedico(string $desde, string $hasta): array
    {
        $statement = $this->pdo->prepare(
            "SELECT m.id AS medico_id, m.codigo, m.nombres, m.apellidos,
                    COUNT(c.id) AS total,
                    COUNT(c.id) FILTER (WHERE c.estado = 'programada') AS programadas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'confirmada') AS confirmadas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'atendida') AS atendidas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'cancelada') AS canceladas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'inasistencia') AS inasistencias
             FROM medicos m
             LEFT JOIN citas c
                ON c.medico_id = m.id
               AND c.fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)
             GROUP BY m.id, m.codigo, m.nombres, m.apellidos
             ORDER BY total DESC, m.apellidos ASC, m.nombres ASC"
        );
        $statement->execute(['desde' => $desde, 'hasta' => $hasta]);

        return array_map(static function (array $row): array {
            $row['medico_id'] = (int) $row['medico_id'];
            $row['total'] = (int) $row['total'];
            $row['programadas'] = (int) $row['programadas'];
            $row['confirmadas'] = (int) $row['confirmadas'];
            $row['atendidas'] = (int) $row['atendidas'];
            $row['canceladas'] = (int) $row['canceladas'];
            $row['inasistencias'] = (int) $row['inasistencias'];
            $row['nombre_completo'] = trim((string) $row['nombres'] . ' ' . (string) $row['apellidos']);

            return $row;
        }, $statement->fetchAll());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function citasPorEspecialidad(string $desde, string $hasta): array
    {
        $statement = $this->pdo->prepare(
            "SELECT e.id AS especialidad_id, e.nombre,
                    COUNT(c.id) AS total,
                    COUNT(c.id) FILTER (WHERE c.estado = 'programada') AS programadas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'confirmada') AS confirmadas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'atendida') AS atendidas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'cancelada') AS canceladas,
                    COUNT(c.id) FILTER (WHERE c.estado = 'inasistencia') AS inasistencias
             FROM especialidades e
             LEFT JOIN citas c
                ON c.especialidad_id = e.id
               AND c.fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)
             GROUP BY e.id, e.nombre
             ORDER BY total DESC, e.nombre ASC"
        );
        $statement->execute(['desde' => $desde, 'hasta' => $hasta]);

        return array_map(static function (array $row): array {
            $row['especialidad_id'] = (int) $row['especialidad_id'];
            $row['total'] = (int) $row['total'];
            $row['programadas'] = (int) $row['programadas'];
            $row['confirmadas'] = (int) $row['confirmadas'];
            $row['atendidas'] = (int) $row['atendidas'];
            $row['canceladas'] = (int) $row['canceladas'];
            $row['inasistencias'] = (int) $row['inasistencias'];

            return $row;
        }, $statement->fetchAll());
    }

    /**
     * @return array<string, int>
     */
    public function citasPorEstado(string $desde, string $hasta): array
    {
        $statement = $this->pdo->prepare(
            'SELECT estado, COUNT(*) AS total
             FROM citas
             WHERE fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)
             GROUP BY estado'
        );
        $statement->execute(['desde' => $desde, 'hasta' => $hasta]);

        $resultado = [
            'programada' => 0,
            'confirmada' => 0,
            'atendida' => 0,
            'cancelada' => 0,
            'inasistencia' => 0,
        ];

        foreach ($statement->fetchAll() as $row) {
            $estado = (string) $row['estado'];

            if (array_key_exists($estado, $resultado)) {
                $resultado[$estado] = (int) $row['total'];
            }
        }

        return $resultado;
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function pacientesRegistrados(string $desde, string $hasta): array
    {
        $parametros = ['desde' => $desde, 'hasta' => $hasta];

        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM pacientes
             WHERE created_at >= CAST(:desde AS date)
               AND created_at < (CAST(:hasta AS date) + INTERVAL \'1 day\')'
        );
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $statement = $this->pdo->prepare(
            'SELECT id, codigo, ci, nombres, apellidos, telefono, email, estado, created_at
             FROM pacientes
             WHERE created_at >= CAST(:desde AS date)
               AND created_at < (CAST(:hasta AS date) + INTERVAL \'1 day\')
             ORDER BY created_at DESC, id DESC'
        );
        $statement->execute($parametros);

        $data = array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['nombre_completo'] = trim((string) $row['nombres'] . ' ' . (string) $row['apellidos']);

            return $row;
        }, $statement->fetchAll());

        return ['data' => $data, 'total' => $total];
    }

    /**
     * @return array{total: int, programadas: int, confirmadas: int, atendidas: int, canceladas: int, inasistencias: int}
     */
    public function asistencia(string $desde, string $hasta): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE estado = 'programada') AS programadas,
                    COUNT(*) FILTER (WHERE estado = 'confirmada') AS confirmadas,
                    COUNT(*) FILTER (WHERE estado = 'atendida') AS atendidas,
                    COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
                    COUNT(*) FILTER (WHERE estado = 'inasistencia') AS inasistencias
             FROM citas
             WHERE fecha BETWEEN CAST(:desde AS date) AND CAST(:hasta AS date)"
        );
        $statement->execute(['desde' => $desde, 'hasta' => $hasta]);

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
     * @return array<string, int>
     */
    public function dashboardAdmin(string $fecha): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS citas_hoy,
                    COUNT(*) FILTER (WHERE estado = 'programada') AS programadas,
                    COUNT(*) FILTER (WHERE estado = 'confirmada') AS confirmadas,
                    COUNT(*) FILTER (WHERE estado = 'atendida') AS atendidas,
                    COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
                    COUNT(*) FILTER (WHERE estado = 'inasistencia') AS inasistencias,
                    COUNT(*) FILTER (WHERE origen = 'web') AS web,
                    COUNT(*) FILTER (WHERE origen = 'kiosco') AS kiosco,
                    COUNT(*) FILTER (WHERE origen = 'recepcion') AS recepcion
             FROM citas
             WHERE fecha = CAST(:fecha AS date)"
        );
        $statement->execute(['fecha' => $fecha]);
        $row = $statement->fetch() ?: [];

        $pacientes = (int) $this->pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();
        $medicos = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM medicos WHERE estado = 'activo'"
        )->fetchColumn();

        return [
            'citas_hoy' => (int) ($row['citas_hoy'] ?? 0),
            'programadas' => (int) ($row['programadas'] ?? 0),
            'confirmadas' => (int) ($row['confirmadas'] ?? 0),
            'atendidas' => (int) ($row['atendidas'] ?? 0),
            'canceladas' => (int) ($row['canceladas'] ?? 0),
            'inasistencias' => (int) ($row['inasistencias'] ?? 0),
            'por_origen' => [
                'web' => (int) ($row['web'] ?? 0),
                'kiosco' => (int) ($row['kiosco'] ?? 0),
                'recepcion' => (int) ($row['recepcion'] ?? 0),
            ],
            'total_pacientes' => $pacientes,
            'total_medicos' => $medicos,
        ];
    }
}
