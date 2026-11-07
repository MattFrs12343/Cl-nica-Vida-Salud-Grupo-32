<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use PDO;

/**
 * Parámetros de negocio editables (precio de consulta, porcentaje de adelanto,
 * vigencia del pago y datos del QR). Se guardan como texto y se convierten
 * según el tipo esperado.
 */
final class ConfiguracionModel
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT clave, valor FROM configuracion ORDER BY clave');
        $filas = $statement === false ? [] : $statement->fetchAll();
        $datos = [];

        foreach ($filas as $fila) {
            $datos[(string) $fila['clave']] = (string) $fila['valor'];
        }

        return $datos;
    }

    public function get(string $clave, ?string $default = null): ?string
    {
        $statement = $this->pdo->prepare('SELECT valor FROM configuracion WHERE clave = :clave LIMIT 1');
        $statement->execute(['clave' => $clave]);
        $valor = $statement->fetchColumn();

        return $valor === false ? $default : (string) $valor;
    }

    public function set(string $clave, string $valor): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO configuracion (clave, valor, updated_at)
             VALUES (:clave, :valor, now())
             ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor, updated_at = now()'
        );
        $statement->execute(['clave' => $clave, 'valor' => $valor]);
    }

    public function precioConsulta(): float
    {
        return round(max(0.0, (float) ($this->get('precio_consulta', '300') ?? '300')), 2);
    }

    public function adelantoPorcentaje(): float
    {
        $valor = (float) ($this->get('adelanto_porcentaje', '50') ?? '50');

        return min(100.0, max(0.0, $valor));
    }

    public function pagoExpiraMinutos(): int
    {
        return max(5, (int) ($this->get('pago_expira_minutos', '120') ?? '120'));
    }

    /** @return array{contenido: string, titular: string, precio_consulta: float, adelanto_porcentaje: float, pago_expira_minutos: int} */
    public function pago(): array
    {
        return [
            'contenido' => (string) ($this->get('qr_pago_contenido', 'VIDASALUD|PAGO|CUENTA-CLINICA-001') ?? ''),
            'titular' => (string) ($this->get('qr_pago_titular', 'Clínica Vida Salud') ?? ''),
            'precio_consulta' => $this->precioConsulta(),
            'adelanto_porcentaje' => $this->adelantoPorcentaje(),
            'pago_expira_minutos' => $this->pagoExpiraMinutos(),
        ];
    }
}
