<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CitaModel;
use App\Models\OrdenModel;
use App\Services\CancelacionService;
use App\Services\CobroService;

/**
 * Cobros de consulta, cancelaciones con reprogramación y reembolsos.
 *
 * Los endpoints de lectura toleran al paciente dueño de la cita; las
 * confirmaciones de dinero y la entrega de reembolsos son exclusivas de
 * recepción/administración.
 */
final class CobroApiController extends AdminApiController
{
    public function __construct(
        private ?CobroService $cobros = null,
        private ?CancelacionService $cancelaciones = null,
        private ?CitaModel $citas = null,
        private ?OrdenModel $ordenes = null
    ) {
        $this->cobros ??= new CobroService();
        $this->cancelaciones ??= new CancelacionService();
        $this->citas ??= new CitaModel();
        $this->ordenes ??= new OrdenModel();
    }

    /** @param array<string, string> $params */
    public function resumen(Request $request, array $params): Response
    {
        $usuario = $this->usuarioActual();
        $id = $this->idParam($params);
        $this->autorizarCita($id, $usuario);

        $resumen = $this->comoPropietario(fn (): array => $this->cobros->resumen($id));

        return json_ok($resumen);
    }

    /** @param array<string, string> $params */
    public function iniciarAdelanto(Request $request, array $params): Response
    {
        $usuario = $this->usuarioActual();
        $id = $this->idParam($params);
        $this->autorizarCita($id, $usuario);

        $resumen = $this->comoPropietario(fn (): array => $this->cobros->iniciarAdelanto($id));

        return json_ok($resumen);
    }

    /** @param array<string, string> $params */
    public function confirmarAdelanto(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico'
        );
        $id = $this->idParam($params);

        $resumen = $this->comoPropietario(
            fn (): array => $this->cobros->confirmarAdelanto($id, $request->body(), $usuario)
        );

        return json_ok($resumen, [], 'Adelanto confirmado.');
    }

    /** @param array<string, string> $params */
    public function cobrarSaldo(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico'
        );
        $id = $this->idParam($params);

        $resumen = $this->comoPropietario(
            fn (): array => $this->cobros->cobrarSaldo($id, $request->body(), $usuario)
        );

        return json_ok($resumen, [], 'Saldo cobrado.');
    }

    /** @param array<string, string> $params */
    public function opcionesCancelacion(Request $request, array $params): Response
    {
        $usuario = $this->usuarioActual();
        $id = $this->idParam($params);
        $fecha = $request->query('fecha');
        $fecha = is_scalar($fecha) && trim((string) $fecha) !== '' ? trim((string) $fecha) : null;

        $opciones = $this->comoPropietario(
            fn (): array => $this->cancelaciones->opciones($id, $usuario, $fecha)
        );

        return json_ok($opciones);
    }

    /** @param array<string, string> $params */
    public function reprogramar(Request $request, array $params): Response
    {
        $usuario = $this->usuarioActual();
        $id = $this->idParam($params);

        $resultado = $this->comoPropietario(
            fn (): array => $this->cancelaciones->reprogramar($id, $request->body(), $usuario)
        );

        return json_ok($resultado, [], 'Cita reprogramada.', 201);
    }

    /** @param array<string, string> $params */
    public function cancelarDefinitivo(Request $request, array $params): Response
    {
        $usuario = $this->usuarioActual();
        $id = $this->idParam($params);

        $resultado = $this->comoPropietario(
            fn (): array => $this->cancelaciones->cancelarDefinitivo($id, $request->body(), $usuario)
        );

        return json_ok($resultado, [], 'Cita cancelada.');
    }

    /** @param array<string, string> $params */
    public function entregarReembolso(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico'
        );
        $id = $this->idParam($params);

        $resumen = $this->comoPropietario(
            fn (): array => $this->cobros->entregarReembolso($id, $request->body(), $usuario)
        );

        return json_ok($resumen, [], 'Reembolso entregado.');
    }

    public function pendientes(Request $request): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico'
        );

        $data = $this->comoPropietario(fn (): array => $this->cobros->pendientes());

        return json_ok($data);
    }

    public function ordenesPendientes(Request $request): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico'
        );

        $data = $this->comoPropietario(fn (): array => $this->ordenes->pendientesCobro());

        return json_ok($data, [
            'page' => 1,
            'per_page' => count($data),
            'total' => count($data),
        ]);
    }

    /** @param array<string, string> $params */
    public function pagarOrden(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico'
        );
        $id = $this->idParam($params);

        $orden = $this->comoPropietario(
            fn (): array => $this->cobros->pagarOrden($id, $request->body(), $usuario)
        );

        return json_ok($orden, [], 'Orden cobrada.');
    }

    /**
     * Expira adelantos vencidos. Lo puede disparar recepción o un cron; es
     * idempotente.
     */
    public function expirar(Request $request): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista');

        $total = $this->comoPropietario(fn (): int => $this->cobros->expirarPendientes());

        return json_ok(['expiradas' => $total], [], 'Adelantos vencidos liberados.');
    }

    /** @param array<string, mixed> $usuario */
    private function autorizarCita(int $citaId, array $usuario): void
    {
        $cita = $this->comoPropietario(fn (): ?array => $this->citas->findRawById($citaId));

        if ($cita === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        $rol = (string) ($usuario['rol'] ?? '');
        $medicoId = (int) ($cita['medico_id'] ?? 0);

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        if ($rol === 'paciente'
            && (int) ($cita['paciente_id'] ?? 0) === (int) ($usuario['paciente_id'] ?? 0)) {
            return;
        }

        if ($rol === 'medico' && $medicoId === (int) ($usuario['medico_id'] ?? 0)) {
            return;
        }

        if ($rol === 'recepcionista_medico'
            && in_array($medicoId, array_map('intval', (array) ($usuario['medico_ids'] ?? [])), true)) {
            return;
        }

        throw new HttpException(403, 'No tienes permisos para esta cita.', [], 'FORBIDDEN');
    }
}
