<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

/**
 * Construcción del mensaje y enlace de WhatsApp para pedidos (recetas,
 * documentos y órdenes) y resolución de su tipo por prefijo de código
 * (Spec 11 / M-31).
 */
final class PedidoService
{
    private const TIPOS = ['receta', 'documento', 'orden'];

    /** @var array<string, string> */
    private const ETIQUETAS = [
        'receta' => 'receita',
        'documento' => 'documento',
        'orden' => 'exames',
    ];

    /** @return array<int, string> */
    public static function tipos(): array
    {
        return self::TIPOS;
    }

    public function validarTipo(mixed $tipo): string
    {
        $tipo = is_scalar($tipo) ? strtolower(trim((string) $tipo)) : '';

        if (!in_array($tipo, self::TIPOS, true)) {
            throw new HttpException(404, 'Pedido no encontrado.', [], 'NOT_FOUND');
        }

        return $tipo;
    }

    /**
     * Resuelve el tipo de pedido a partir del prefijo de su código visible
     * (`RSV-`, `DOC-`, `ORD-`).
     */
    public function tipoDesdeCodigo(string $codigo): ?string
    {
        $prefijo = strtoupper(substr(trim($codigo), 0, 3));

        return match ($prefijo) {
            'RSV' => 'receta',
            'DOC' => 'documento',
            'ORD' => 'orden',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $pedido
     * @return array{whatsapp_url: string, mensaje: string}
     */
    public function construirEnvio(string $tipo, array $pedido): array
    {
        $telefono = $pedido['paciente']['telefono'] ?? null;

        if (!is_string($telefono) || trim($telefono) === '') {
            throw new HttpException(
                409,
                'El paciente no tiene teléfono registrado. Regístrelo para enviar.',
                [],
                'NOT_ELIGIBLE'
            );
        }

        $urlPublica = url('/pedido/' . (string) $pedido['codigo']);
        $mensaje = $this->mensaje($tipo, $pedido, $urlPublica);

        return [
            'whatsapp_url' => 'https://wa.me/' . $this->normalizarTelefono($telefono)
                . '?text=' . rawurlencode($mensaje),
            'mensaje' => $mensaje,
        ];
    }

    /** @param array<string, mixed> $pedido */
    private function mensaje(string $tipo, array $pedido, string $urlPublica): string
    {
        $paciente = (string) ($pedido['paciente']['nombre_completo'] ?? '');
        $medico = (string) ($pedido['medico']['nombre_completo'] ?? '');
        $etiqueta = self::ETIQUETAS[$tipo] ?? $tipo;

        return "Olá {$paciente}, seu pedido de {$etiqueta} de {$medico} está disponível.\n\n"
            . "Acesse pelo link:\n{$urlPublica}\n\n"
            . "Código da prescrição: {$pedido['codigo']}\n"
            . "Código de acesso: {$pedido['codigo_acceso']}";
    }

    private function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', $telefono) ?? '';

        if ($digitos !== '' && !str_starts_with($digitos, '591')) {
            $digitos = '591' . $digitos;
        }

        return $digitos;
    }
}
