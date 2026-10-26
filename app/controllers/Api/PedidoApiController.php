<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\DocumentoModel;
use App\Models\OrdenModel;
use App\Models\RecetaModel;
use App\Services\PedidoService;

/**
 * Envío de pedidos por WhatsApp (Spec 11 / M-31).
 *
 * `recepcionista_medico` puede manejar varios médicos (`medico_ids`), por lo
 * que esta escritura corre con `comoPropietario()` (bypassa RLS) y verifica
 * el alcance explícitamente, igual que Ficha/Pago.
 */
final class PedidoApiController extends AdminApiController
{
    public function __construct(
        private ?RecetaModel $recetas = null,
        private ?DocumentoModel $documentos = null,
        private ?OrdenModel $ordenes = null,
        private ?PedidoService $pedidos = null
    ) {
        $this->recetas ??= new RecetaModel();
        $this->documentos ??= new DocumentoModel();
        $this->ordenes ??= new OrdenModel();
        $this->pedidos ??= new PedidoService();
    }

    /** @param array<string, string> $params */
    public function enviar(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles('medico', 'recepcionista_medico');
        $tipo = $this->pedidos->validarTipo($params['tipo'] ?? null);
        $id = $this->idParam($params);

        $pedido = $this->comoPropietario(function () use ($tipo, $id, $usuario): array {
            $pedido = $this->pedidoPorTipo($tipo, $id);

            if ($pedido === null) {
                throw new HttpException(404, 'Pedido no encontrado.', [], 'NOT_FOUND');
            }

            $medicoIds = $this->medicoIdsDe($usuario);
            $medicoId = (int) ($pedido['medico']['id'] ?? 0);

            if ($medicoIds !== null && !in_array($medicoId, $medicoIds, true)) {
                throw new HttpException(
                    403,
                    'No tienes permisos para enviar este pedido.',
                    [],
                    'FORBIDDEN'
                );
            }

            return $pedido;
        });

        // Se valida el teléfono (y se construye el mensaje) antes de marcar el
        // envío: si el paciente no tiene teléfono, el pedido debe quedar como
        // no enviado (RXW-B).
        $envio = $this->pedidos->construirEnvio($tipo, $pedido);

        $this->comoPropietario(function () use ($tipo, $id): void {
            $this->marcarEnviado($tipo, $id);
        });

        return json_ok($envio);
    }

    /** @return array<string, mixed>|null */
    private function pedidoPorTipo(string $tipo, int $id): ?array
    {
        return match ($tipo) {
            'receta' => $this->recetas->porId($id),
            'documento' => $this->documentos->porId($id),
            'orden' => $this->ordenes->porId($id),
            default => null,
        };
    }

    private function marcarEnviado(string $tipo, int $id): void
    {
        match ($tipo) {
            'receta' => $this->recetas->marcarEnviado($id),
            'documento' => $this->documentos->marcarEnviado($id),
            'orden' => $this->ordenes->marcarEnviado($id),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<int, int>|null Null cuando el rol puede ver todos los médicos.
     */
    private function medicoIdsDe(array $usuario): ?array
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if ($rol === 'medico') {
            $medicoId = (int) ($usuario['medico_id'] ?? 0);

            return $medicoId > 0 ? [$medicoId] : [];
        }

        if ($rol === 'recepcionista_medico') {
            return array_values(array_unique(array_filter(
                array_map('intval', (array) ($usuario['medico_ids'] ?? [])),
                static fn (int $id): bool => $id > 0
            )));
        }

        throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
    }
}
