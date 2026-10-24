<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Consulta de fichas emitidas para el panel de recepción (M-28).
 *
 * La emisión sigue viviendo en `FichaService` (kiosco y web). Este modelo solo
 * resuelve búsqueda, detalle y el contador de reimpresiones.
 */
final class FichaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array{q?: string, fecha?: string|null, medico_ids?: array<int, int>|null} $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $page, int $perPage): array
    {
        [$where, $parametros] = $this->condiciones($filtros);

        $conteo = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM fichas f
             JOIN citas c ON c.id = f.cita_id
             JOIN pacientes p ON p.id = c.paciente_id
             JOIN medicos m ON m.id = c.medico_id
             JOIN especialidades e ON e.id = c.especialidad_id'
            . $where
        );
        $conteo->execute($parametros);
        $total = (int) $conteo->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        $statement = $this->pdo->prepare(
            $this->sqlSelect()
            . $where
            . ' ORDER BY f.emitida_at DESC, f.id DESC
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
    public function porCodigo(string $codigo): ?array
    {
        $statement = $this->pdo->prepare(
            $this->sqlSelect() . ' WHERE f.codigo = :codigo LIMIT 1'
        );
        $statement->execute(['codigo' => strtoupper(trim($codigo))]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            $this->sqlSelect() . ' WHERE f.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    /**
     * Incrementa el contador de reimpresiones y devuelve la ficha actualizada.
     *
     * @return array<string, mixed>|null
     */
    public function registrarReimpresion(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'UPDATE fichas
             SET reimpresiones = reimpresiones + 1,
                 ultima_reimpresion_at = now()
             WHERE id = :id
             RETURNING id'
        );
        $statement->execute(['id' => $id]);

        if ($statement->fetchColumn() === false) {
            return null;
        }

        return $this->porId($id);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function condiciones(array $filtros): array
    {
        $where = [];
        $parametros = [];

        $q = trim((string) ($filtros['q'] ?? ''));

        if ($q !== '') {
            $where[] = '(f.codigo ILIKE :q
                         OR c.codigo ILIKE :q
                         OR p.ci ILIKE :q
                         OR p.codigo ILIKE :q
                         OR p.nombres ILIKE :q
                         OR p.apellidos ILIKE :q
                         OR (p.nombres || \' \' || p.apellidos) ILIKE :q)';
            $parametros['q'] = '%' . $q . '%';
        }

        if (($filtros['fecha'] ?? null) !== null && $filtros['fecha'] !== '') {
            $where[] = 'c.fecha = CAST(:fecha AS date)';
            $parametros['fecha'] = (string) $filtros['fecha'];
        }

        $medicoIds = $filtros['medico_ids'] ?? null;

        if (is_array($medicoIds)) {
            if ($medicoIds === []) {
                $where[] = 'FALSE';
            } else {
                $marcadores = [];

                foreach (array_values($medicoIds) as $indice => $medicoId) {
                    $clave = 'medico_' . $indice;
                    $marcadores[] = ':' . $clave;
                    $parametros[$clave] = (int) $medicoId;
                }

                $where[] = 'c.medico_id IN (' . implode(', ', $marcadores) . ')';
            }
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $parametros];
    }

    private function sqlSelect(): string
    {
        return 'SELECT f.id, f.codigo, f.cita_id, f.canal, f.codigo_acceso,
                       f.verificacion_biometrica, f.emitida_at,
                       f.reimpresiones, f.ultima_reimpresion_at,
                       c.codigo AS cita_codigo, c.fecha, c.hora_inicio, c.hora_fin,
                       c.estado AS cita_estado, c.origen,
                       p.id AS paciente_id, p.codigo AS paciente_codigo,
                       p.ci AS paciente_ci, p.nombres AS paciente_nombres,
                       p.apellidos AS paciente_apellidos, p.telefono AS paciente_telefono,
                       m.id AS medico_id, m.codigo AS medico_codigo,
                       m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                       e.id AS especialidad_id, e.nombre AS especialidad_nombre
                FROM fichas f
                JOIN citas c ON c.id = f.cita_id
                JOIN pacientes p ON p.id = c.paciente_id
                JOIN medicos m ON m.id = c.medico_id
                JOIN especialidades e ON e.id = c.especialidad_id';
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

        return [
            'id' => (int) $fila['id'],
            'codigo' => (string) $fila['codigo'],
            'cita_id' => (int) $fila['cita_id'],
            'cita_codigo' => (string) $fila['cita_codigo'],
            'cita_estado' => (string) $fila['cita_estado'],
            'fecha' => (string) $fila['fecha'],
            'hora_inicio' => substr((string) $fila['hora_inicio'], 0, 5),
            'hora_fin' => substr((string) $fila['hora_fin'], 0, 5),
            'origen' => (string) $fila['origen'],
            'canal' => (string) $fila['canal'],
            'codigo_acceso' => (string) $fila['codigo_acceso'],
            'verificacion_biometrica' => (bool) $fila['verificacion_biometrica'],
            'emitida_at' => (string) $fila['emitida_at'],
            'reimpresiones' => (int) ($fila['reimpresiones'] ?? 0),
            'ultima_reimpresion_at' => $fila['ultima_reimpresion_at'] === null
                ? null
                : (string) $fila['ultima_reimpresion_at'],
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
            'especialidad' => [
                'id' => (int) $fila['especialidad_id'],
                'nombre' => (string) $fila['especialidad_nombre'],
            ],
        ];
    }
}
