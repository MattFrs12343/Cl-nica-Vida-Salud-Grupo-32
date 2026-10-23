<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

final class AuditoriaModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $page, int $perPage): array
    {
        $parametros = [];
        $where = $this->where($filtros, $parametros);
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id'
            . $whereSql
        );
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.usuario_id, a.ip, a.tabla_afectada, a.accion,
                    a.registro_id, a.fecha,
                    u.username, u.email, u.rol
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id'
            . $whereSql
            . ' ORDER BY a.fecha DESC, a.id DESC
                LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $statement->execute($parametros);

        return [
            'data' => array_map([$this, 'normalizar'], $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT a.id, a.usuario_id, a.ip, a.tabla_afectada, a.accion,
                    a.registro_id, a.datos_antes, a.datos_despues, a.fecha,
                    u.username, u.email, u.rol
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /**
     * @param array<string, mixed> $filtros
     * @param array<string, mixed> $parametros
     * @return array<int, string>
     */
    private function where(array $filtros, array &$parametros): array
    {
        $where = [];

        if (($filtros['tabla'] ?? null) !== null) {
            $where[] = 'a.tabla_afectada = :tabla';
            $parametros['tabla'] = $filtros['tabla'];
        }

        if (($filtros['accion'] ?? null) !== null) {
            $where[] = 'a.accion = CAST(:accion AS accion_auditoria)';
            $parametros['accion'] = $filtros['accion'];
        }

        if (($filtros['usuario_id'] ?? null) !== null) {
            $where[] = 'a.usuario_id = :usuario_id';
            $parametros['usuario_id'] = $filtros['usuario_id'];
        } elseif (($filtros['usuario'] ?? '') !== '') {
            $where[] = '(u.username ILIKE :usuario OR u.email ILIKE :usuario)';
            $parametros['usuario'] = '%' . $filtros['usuario'] . '%';
        }

        if (($filtros['desde'] ?? null) !== null) {
            $where[] = 'a.fecha >= CAST(:desde AS date)';
            $parametros['desde'] = $filtros['desde'];
        }

        if (($filtros['hasta'] ?? null) !== null) {
            $where[] = 'a.fecha < (CAST(:hasta AS date) + INTERVAL \'1 day\')';
            $parametros['hasta'] = $filtros['hasta'];
        }

        return $where;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizar(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['usuario_id'] = $row['usuario_id'] === null ? null : (int) $row['usuario_id'];
        $row['registro_id'] = $row['registro_id'] === null ? null : (int) $row['registro_id'];
        $row['usuario'] = $row['usuario_id'] === null
            ? null
            : [
                'id' => $row['usuario_id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'rol' => $row['rol'],
            ];

        if (array_key_exists('datos_antes', $row)) {
            $row['datos_antes'] = $this->decodificarJson($row['datos_antes']);
        }

        if (array_key_exists('datos_despues', $row)) {
            $row['datos_despues'] = $this->decodificarJson($row['datos_despues']);
        }

        unset($row['username'], $row['email'], $row['rol']);

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodificarJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
