<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use PDO;

final class FichaService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Emite la ficha de una cita. El código FIC-#### lo genera el trigger.
     *
     * @return array{codigo: string, codigo_acceso: string, canal: string}
     */
    public function emitir(int $citaId, string $canal = 'web', bool $verificacionBiometrica = false): array
    {
        $codigoAcceso = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $statement = $this->pdo->prepare(
            'INSERT INTO fichas (cita_id, canal, codigo_acceso, verificacion_biometrica)
             VALUES (
                 :cita_id,
                 CAST(:canal AS canal_ficha),
                 :codigo_acceso,
                 :verificacion
             )
             RETURNING codigo, codigo_acceso, canal'
        );
        $statement->execute([
            'cita_id' => $citaId,
            'canal' => $canal,
            'codigo_acceso' => $codigoAcceso,
            'verificacion' => $verificacionBiometrica ? 'true' : 'false',
        ]);

        $row = $statement->fetch() ?: [];

        return [
            'codigo' => (string) ($row['codigo'] ?? ''),
            'codigo_acceso' => (string) ($row['codigo_acceso'] ?? $codigoAcceso),
            'canal' => (string) ($row['canal'] ?? $canal),
        ];
    }
}
