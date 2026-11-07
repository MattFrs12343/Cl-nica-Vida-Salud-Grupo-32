<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Catálogo de exámenes de laboratorio e imagen con precios definidos por
 * recepción. El médico elige del catálogo al crear una orden y el precio se
 * copia al detalle para que un cambio posterior no altere órdenes ya emitidas.
 */
final class ExamenModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * @param array{tipo?: string|null, activo?: bool|null, q?: string|null} $filtros
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $page, int $perPage): array
    {
        $where = [];
        $parametros = [];

        if (($filtros['tipo'] ?? null) !== null) {
            $where[] = 'tipo = CAST(:tipo AS tipo_orden)';
            $parametros['tipo'] = (string) $filtros['tipo'];
        }

        if (($filtros['activo'] ?? null) !== null) {
            $where[] = 'activo = CAST(:activo AS boolean)';
            $parametros['activo'] = $filtros['activo'] ? 'true' : 'false';
        }

        if (($filtros['q'] ?? '') !== '') {
            $where[] = '(nombre ILIKE :q OR codigo ILIKE :q)';
            $parametros['q'] = '%' . $filtros['q'] . '%';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM examenes_laboratorio' . $whereSql);
        $count->execute($parametros);
        $total = (int) $count->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $statement = $this->pdo->prepare(
            'SELECT * FROM examenes_laboratorio'
            . $whereSql
            . ' ORDER BY activo DESC, nombre ASC
                LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $statement->execute($parametros);

        return [
            'data' => array_map([$this, 'normalizar'], $statement->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listarActivos(?string $tipo = null): array
    {
        $sql = 'SELECT * FROM examenes_laboratorio WHERE activo = TRUE';
        $parametros = [];

        if ($tipo !== null) {
            $sql .= ' AND tipo = CAST(:tipo AS tipo_orden)';
            $parametros['tipo'] = $tipo;
        }

        $sql .= ' ORDER BY tipo ASC, nombre ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parametros);

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function porId(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM examenes_laboratorio WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    /** @return array<string, mixed>|null */
    public function porNombre(string $nombre): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM examenes_laboratorio WHERE lower(nombre) = lower(:nombre) LIMIT 1'
        );
        $statement->execute(['nombre' => trim($nombre)]);
        $fila = $statement->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO examenes_laboratorio (codigo, nombre, tipo, precio, activo)
             VALUES (
                 :codigo,
                 :nombre,
                 CAST(:tipo AS tipo_orden),
                 :precio,
                 CAST(:activo AS boolean)
             )
             RETURNING id"
        );
        $statement->execute([
            'codigo' => $datos['codigo'] ?? null,
            'nombre' => (string) $datos['nombre'],
            'tipo' => (string) ($datos['tipo'] ?? 'laboratorio'),
            'precio' => number_format((float) ($datos['precio'] ?? 0), 2, '.', ''),
            'activo' => ($datos['activo'] ?? true) ? 'true' : 'false',
        ]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE examenes_laboratorio
             SET codigo = :codigo,
                 nombre = :nombre,
                 tipo = CAST(:tipo AS tipo_orden),
                 precio = :precio,
                 activo = CAST(:activo AS boolean),
                 updated_at = now()
             WHERE id = :id"
        );
        $statement->execute([
            'id' => $id,
            'codigo' => $datos['codigo'] ?? null,
            'nombre' => (string) $datos['nombre'],
            'tipo' => (string) ($datos['tipo'] ?? 'laboratorio'),
            'precio' => number_format((float) ($datos['precio'] ?? 0), 2, '.', ''),
            'activo' => ($datos['activo'] ?? true) ? 'true' : 'false',
        ]);
    }

    public function cambiarEstado(int $id, bool $activo): void
    {
        $this->pdo->prepare(
            'UPDATE examenes_laboratorio SET activo = CAST(:activo AS boolean), updated_at = now()
             WHERE id = :id'
        )->execute(['id' => $id, 'activo' => $activo ? 'true' : 'false']);
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function normalizar(array $fila): array
    {
        return [
            'id' => (int) $fila['id'],
            'codigo' => $fila['codigo'] !== null ? (string) $fila['codigo'] : null,
            'nombre' => (string) $fila['nombre'],
            'tipo' => (string) $fila['tipo'],
            'precio' => (float) $fila['precio'],
            'activo' => (bool) $fila['activo'],
            'created_at' => (string) ($fila['created_at'] ?? ''),
            'updated_at' => (string) ($fila['updated_at'] ?? ''),
        ];
    }
}
