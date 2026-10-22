<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use DateTimeImmutable;
use PDO;

final class EspecialidadModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Especialidades activas para landing/kiosco. Las destacadas van primero.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActivas(): array
    {
        $statement = $this->pdo->query(
            "SELECT id, nombre, descripcion, sexo_permitido, edad_min, edad_max,
                    destacada, icono, estado
             FROM especialidades
             WHERE estado = 'activo'
             ORDER BY destacada DESC, nombre ASC"
        );

        return array_map([$this, 'normalizar'], $statement->fetchAll());
    }

    /** @return array<string, mixed>|null */
    public function findActivaById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, nombre, descripcion, sexo_permitido, edad_min, edad_max,
                    destacada, icono, estado
             FROM especialidades
             WHERE id = :id AND estado = 'activo'
             LIMIT 1"
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : $this->normalizar($row);
    }

    /**
     * Valida la restricción de sexo/edad de una especialidad (RX02/RX03).
     *
     * @param array<string, mixed> $especialidad
     * @param array<string, mixed> $paciente
     */
    public function esElegiblePaciente(array $especialidad, array $paciente): bool
    {
        $sexoPermitido = (string) ($especialidad['sexo_permitido'] ?? 'ambos');
        $sexoPaciente = (string) ($paciente['sexo'] ?? '');

        if ($sexoPermitido !== 'ambos' && $sexoPermitido !== $sexoPaciente) {
            return false;
        }

        $edad = $this->edadPaciente((string) ($paciente['fecha_nacimiento'] ?? ''));

        if ($edad === null) {
            return true;
        }

        $edadMin = $especialidad['edad_min'];
        $edadMax = $especialidad['edad_max'];

        if ($edadMin !== null && $edad < (int) $edadMin) {
            return false;
        }

        if ($edadMax !== null && $edad > (int) $edadMax) {
            return false;
        }

        return true;
    }

    private function edadPaciente(string $fechaNacimiento): ?int
    {
        if ($fechaNacimiento === '') {
            return null;
        }

        try {
            $nacimiento = new DateTimeImmutable($fechaNacimiento);
        } catch (\Throwable) {
            return null;
        }

        return $nacimiento->diff(new DateTimeImmutable('today'))->y;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizar(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['edad_min'] = $row['edad_min'] === null ? null : (int) $row['edad_min'];
        $row['edad_max'] = $row['edad_max'] === null ? null : (int) $row['edad_max'];
        $row['destacada'] = (bool) $row['destacada'];

        return $row;
    }
}
