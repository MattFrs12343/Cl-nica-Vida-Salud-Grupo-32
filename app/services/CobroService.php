<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\CitaModel;
use App\Models\ConfiguracionModel;
use App\Models\OrdenModel;
use App\Models\PagoModel;
use DateTimeImmutable;
use PDOException;
use Throwable;

/**
 * Cobros de la consulta y de las órdenes de laboratorio.
 *
 * Modelo de negocio:
 *  - Consulta con especialista: precio único configurable (300 Bs por defecto).
 *  - Al reservar se exige un adelanto (50% por defecto) por QR o pasarela.
 *  - Recepción confirma el adelanto; el saldo se cobra al terminar la consulta.
 *  - La inasistencia retiene el adelanto; la cancelación definitiva genera un
 *    reembolso con comprobante que recepción entrega.
 *
 * No hay pasarela real: el QR es estático con una referencia única y la
 * confirmación es manual en recepción. El servicio deja el punto de extensión
 * (`metodo = 'pasarela'`) para conectar un proveedor más adelante.
 */
final class CobroService
{
    public const METODOS = ['qr', 'efectivo', 'visa', 'mastercard', 'pasarela'];

    public function __construct(
        private ?ConfiguracionModel $configuracion = null,
        private ?CitaModel $citas = null,
        private ?PagoModel $pagos = null,
        private ?OrdenModel $ordenes = null,
        private ?MailService $mail = null
    ) {
        $this->configuracion ??= new ConfiguracionModel();
        $this->citas ??= new CitaModel();
        $this->pagos ??= new PagoModel();
        $this->ordenes ??= new OrdenModel();
        $this->mail ??= new MailService();
    }

    /**
     * Datos de cobro de una cita: montos, estado, QR y movimientos.
     *
     * @return array<string, mixed>
     */
    public function resumen(int $citaId): array
    {
        $cita = $this->citas->findRawById($citaId);

        if ($cita === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        return $this->armarResumen($cita);
    }

    /**
     * Inicia (o recalcula) el adelanto de una cita. Se usa también desde la
     * reserva web y el kiosco antes de mostrar el QR.
     *
     * @return array<string, mixed>
     */
    public function iniciarAdelanto(int $citaId): array
    {
        return DB::transaction(function () use ($citaId): array {
            $cita = $this->citas->findRawById($citaId, true);

            if ($cita === null) {
                throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
            }

            if ((string) $cita['estado_pago'] === 'sin_pago') {
                return $this->armarResumen($cita);
            }

            $config = $this->configuracion->pago();
            $precio = (float) $cita['precio_total'] > 0
                ? (float) $cita['precio_total']
                : $config['precio_consulta'];
            $adelanto = round($precio * ($config['adelanto_porcentaje'] / 100), 2);
            $expira = (new DateTimeImmutable('now'))
                ->modify('+' . $config['pago_expira_minutos'] . ' minutes')
                ->format('Y-m-d H:i:sP');

            if ((string) $cita['estado_pago'] === 'pendiente' && (float) $cita['adelanto'] <= 0) {
                $this->citas->aplicarCobroInicial($citaId, $precio, $adelanto, $expira);
            } elseif ((string) $cita['estado_pago'] === 'pendiente' && $cita['pago_expira_en'] === null) {
                $this->citas->aplicarCobroInicial($citaId, $precio, $adelanto, $expira);
            }

            $cita = $this->citas->findRawById($citaId) ?? $cita;

            return $this->armarResumen($cita);
        });
    }

    /**
     * Transfiere el adelanto de una cita reprogramada a la nueva cita.
     *
     * El movimiento original queda en la cita de origen (auditoría) y se crea
     * uno nuevo ligado a la reconsulta, para que el saldo y los reportes de la
     * nueva cita cuadren sin tocar el historial de la anterior.
     *
     * @param array<string, mixed> $origen
     */
    public function transferirAdelanto(int $nuevaCitaId, array $origen): void
    {
        $precio = (float) ($origen['precio_total'] ?? 0);
        $adelanto = (float) ($origen['adelanto'] ?? 0);
        $this->citas->aplicarCobroInicial($nuevaCitaId, $precio, $adelanto, null);

        $original = null;

        foreach ($this->pagos->porCita((int) $origen['id']) as $pago) {
            if ((string) $pago['tipo'] === 'adelanto' && (string) $pago['estado'] === 'confirmado') {
                $original = $pago;
                break;
            }
        }

        if ($original === null) {
            $this->citas->aplicarCobroTransferido($nuevaCitaId, $origen);

            return;
        }

        $usuarioId = (int) ($original['registrado_por_id'] ?? 0);

        $pagoId = $this->pagos->crear([
            'paciente_id' => (int) $origen['paciente_id'],
            'medico_id' => (int) $origen['medico_id'],
            'registrado_por' => $usuarioId,
            'monto' => number_format((float) $original['monto'], 2, '.', ''),
            'metodo' => (string) $original['metodo'],
            'referencia_paciente' => $original['referencia_paciente'] ?? null,
            'cita_id' => $nuevaCitaId,
            'tipo' => 'adelanto',
            'estado' => 'confirmado',
            'referencia' => trim(
                (string) ($original['referencia'] ?? 'PAGO-' . (string) $origen['codigo'])
                . ' · reprogramado'
            ),
            'confirmado_por' => $usuarioId > 0 ? $usuarioId : null,
            'confirmado_en' => date('Y-m-d H:i:sP'),
        ]);

        $this->citas->marcarAdelantoPagado($nuevaCitaId, $pagoId);
    }

    /**
     * Recepción confirma el adelanto recibido (QR, efectivo o tarjeta).
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function confirmarAdelanto(int $citaId, array $datos, array $usuario): array
    {
        $metodo = $this->metodo($datos['metodo'] ?? 'qr');
        $referencia = $this->referencia($datos['referencia'] ?? null);
        $referenciaPaciente = $this->texto($datos['referencia_paciente'] ?? '', 150);

        try {
            return DB::transaction(function () use (
                $citaId,
                $datos,
                $usuario,
                $metodo,
                $referencia,
                $referenciaPaciente
            ): array {
                $cita = $this->citas->findRawById($citaId, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                if (in_array((string) $cita['estado'], ['cancelada', 'inasistencia'], true)) {
                    throw new HttpException(
                        409,
                        'No se puede confirmar el adelanto de una cita cancelada.',
                        [],
                        'INVALID_STATE'
                    );
                }

                if ((string) $cita['estado_pago'] === 'adelanto_pagado'
                    || (string) $cita['estado_pago'] === 'pagado') {
                    return $this->armarResumen($cita);
                }

                if ((string) $cita['estado_pago'] === 'expirado') {
                    throw new HttpException(
                        409,
                        'El plazo para pagar el adelanto venció.',
                        [],
                        'PAYMENT_EXPIRED'
                    );
                }

                $esperado = (float) $cita['adelanto'];

                if ($esperado <= 0) {
                    $config = $this->configuracion->pago();
                    $esperado = round(
                        (float) $cita['precio_total'] * ($config['adelanto_porcentaje'] / 100),
                        2
                    );
                }

                $monto = $this->monto($datos['monto'] ?? $esperado);

                if (abs($monto - $esperado) > 0.01) {
                    throw new HttpException(
                        422,
                        'El monto no coincide con el adelanto de la cita.',
                        [],
                        'VALIDATION_ERROR',
                        ['monto' => 'El adelanto es de ' . number_format($esperado, 2) . ' Bs.']
                    );
                }

                $usuarioId = (int) ($usuario['id'] ?? 0);

                $pagoId = $this->pagos->crear([
                    'paciente_id' => (int) $cita['paciente_id'],
                    'medico_id' => (int) $cita['medico_id'],
                    'registrado_por' => $usuarioId,
                    'monto' => number_format($monto, 2, '.', ''),
                    'metodo' => $metodo,
                    // La referencia digitada en recepción debe verse en la
                    // columna "Referencia" (pagos.referencia_paciente).
                    'referencia_paciente' => $referenciaPaciente !== ''
                        ? $referenciaPaciente
                        : $referencia,
                    'cita_id' => $citaId,
                    'tipo' => 'adelanto',
                    'estado' => 'confirmado',
                    'referencia' => $referencia ?? ('PAGO-' . (string) $cita['codigo']),
                    'confirmado_por' => $usuarioId,
                    'confirmado_en' => date('Y-m-d H:i:sP'),
                ]);

                $this->citas->marcarAdelantoPagado($citaId, $pagoId);

                if ((string) $cita['estado'] === 'programada') {
                    $this->citas->registrarHistorial(
                        $citaId,
                        'programada',
                        'confirmada',
                        $usuarioId ?: null,
                        'Adelanto confirmado en recepción'
                    );
                }

                $actualizada = $this->citas->findRawById($citaId) ?? $cita;

                return $this->armarResumen($actualizada);
            });
        } catch (PDOException $exception) {
            throw $this->traducir($exception);
        }
    }

    /**
     * Cobra el saldo restante al terminar la consulta.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function cobrarSaldo(int $citaId, array $datos, array $usuario): array
    {
        $metodo = $this->metodo($datos['metodo'] ?? 'efectivo');

        try {
            return DB::transaction(function () use ($citaId, $datos, $usuario, $metodo): array {
                $cita = $this->citas->findRawById($citaId, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                if ((string) $cita['estado_pago'] === 'pagado') {
                    return $this->armarResumen($cita);
                }

                if (!in_array((string) $cita['estado_pago'], ['adelanto_pagado', 'sin_pago'], true)) {
                    throw new HttpException(
                        409,
                        'Primero debe confirmarse el adelanto de la cita.',
                        [],
                        'PAYMENT_REQUIRED'
                    );
                }

                $saldo = (float) $cita['saldo'];

                if ($saldo <= 0) {
                    $this->citas->marcarSaldoPagado($citaId);

                    return $this->armarResumen($this->citas->findRawById($citaId) ?? $cita);
                }

                $monto = $this->monto($datos['monto'] ?? $saldo);

                if ($monto - $saldo > 0.01) {
                    throw new HttpException(
                        422,
                        'El monto supera el saldo pendiente.',
                        [],
                        'VALIDATION_ERROR',
                        ['monto' => 'El saldo es de ' . number_format($saldo, 2) . ' Bs.']
                    );
                }

                $usuarioId = (int) ($usuario['id'] ?? 0);

                $this->pagos->crear([
                    'paciente_id' => (int) $cita['paciente_id'],
                    'medico_id' => (int) $cita['medico_id'],
                    'registrado_por' => $usuarioId,
                    'monto' => number_format($monto, 2, '.', ''),
                    'metodo' => $metodo,
                    'referencia_paciente' => $datos['referencia_paciente'] ?? null,
                    'cita_id' => $citaId,
                    'tipo' => 'saldo',
                    'estado' => 'confirmado',
                    'referencia' => $this->referencia($datos['referencia'] ?? null),
                    'confirmado_por' => $usuarioId,
                    'confirmado_en' => date('Y-m-d H:i:sP'),
                ]);

                // Un abono parcial descuenta el saldo sin marcar la cita
                // como pagada; solo cuando el abono liquida la deuda se
                // pasa a estado_pago = 'pagado'.
                $this->citas->aplicarAbonoSaldo($citaId, $monto);

                return $this->armarResumen($this->citas->findRawById($citaId) ?? $cita);
            });
        } catch (PDOException $exception) {
            throw $this->traducir($exception);
        }
    }

    /**
     * Cobra una orden de laboratorio/imagen con el total del catálogo.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function pagarOrden(int $ordenId, array $datos, array $usuario): array
    {
        $metodo = $this->metodo($datos['metodo'] ?? 'efectivo');

        try {
            return DB::transaction(function () use ($ordenId, $datos, $usuario, $metodo): array {
                $orden = $this->ordenes->porId($ordenId);

                if ($orden === null) {
                    throw new HttpException(404, 'La orden no existe.', [], 'NOT_FOUND');
                }

                $pendiente = round((float) $orden['total'] - (float) $orden['pagado'], 2);

                if ($pendiente <= 0) {
                    return $orden;
                }

                $monto = $this->monto($datos['monto'] ?? $pendiente);

                if ($monto - $pendiente > 0.01) {
                    throw new HttpException(
                        422,
                        'El monto supera el saldo de la orden.',
                        [],
                        'VALIDATION_ERROR',
                        ['monto' => 'El saldo es de ' . number_format($pendiente, 2) . ' Bs.']
                    );
                }

                $usuarioId = (int) ($usuario['id'] ?? 0);

                $this->pagos->crear([
                    'paciente_id' => (int) $orden['paciente_id'],
                    'medico_id' => (int) $orden['medico_id'],
                    'registrado_por' => $usuarioId,
                    'monto' => number_format($monto, 2, '.', ''),
                    'metodo' => $metodo,
                    'referencia_paciente' => $datos['referencia_paciente'] ?? null,
                    'orden_id' => $ordenId,
                    'tipo' => 'laboratorio',
                    'estado' => 'confirmado',
                    'referencia' => $this->referencia($datos['referencia'] ?? null),
                    'confirmado_por' => $usuarioId,
                    'confirmado_en' => date('Y-m-d H:i:sP'),
                ]);

                return $this->ordenes->porId($ordenId) ?? $orden;
            });
        } catch (PDOException $exception) {
            throw $this->traducir($exception);
        }
    }

    /**
     * Solicita el reembolso del adelanto y cancela la cita. Genera el
     * comprobante que el paciente presenta en recepción.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function solicitarReembolso(int $citaId, array $datos, array $usuario): array
    {
        $motivo = $this->texto($datos['motivo'] ?? 'Cancelación solicitada por el paciente', 200);

        try {
            return DB::transaction(function () use ($citaId, $motivo, $usuario): array {
                $cita = $this->citas->findRawById($citaId, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                if (!in_array((string) $cita['estado'], ['programada', 'confirmada'], true)) {
                    throw new HttpException(
                        409,
                        'Esta cita no puede cancelarse en su estado actual.',
                        [],
                        'INVALID_STATE'
                    );
                }

                if ((string) $cita['estado_pago'] === 'reembolso_pendiente'
                    || (string) $cita['estado_pago'] === 'reembolsado') {
                    return $this->armarResumen($cita);
                }

                if ((string) $cita['estado_pago'] !== 'adelanto_pagado'
                    && (string) $cita['estado_pago'] !== 'pagado') {
                    throw new HttpException(
                        409,
                        'La cita no tiene un adelanto pagado para reembolsar.',
                        [],
                        'PAYMENT_REQUIRED'
                    );
                }

                $codigo = $this->codigoReembolso();
                $monto = (string) $cita['estado_pago'] === 'pagado'
                    ? (float) $cita['precio_total']
                    : (float) $cita['adelanto'];

                $this->citas->marcarReembolsoPendiente($citaId, $codigo, $monto);
                $this->citas->cambiarEstado($citaId, 'cancelada', $motivo);
                $this->citas->registrarHistorial(
                    $citaId,
                    (string) $cita['estado'],
                    'cancelada',
                    (int) ($usuario['id'] ?? 0) ?: null,
                    $motivo . ' — reembolso ' . $codigo
                );

                $this->notificarRecepcion('Reembolso solicitado', [
                    'codigo' => $codigo,
                    'cita' => (string) $cita['codigo'],
                    'paciente' => $this->nombrePaciente((int) $cita['paciente_id']),
                    'monto' => $monto,
                    'fecha' => (string) $cita['fecha'],
                ]);

                $actualizada = $this->citas->findRawById($citaId) ?? $cita;

                return $this->armarResumen($actualizada);
            });
        } catch (PDOException $exception) {
            throw $this->traducir($exception);
        }
    }

    /**
     * Recepción entrega el reembolso y deja el movimiento registrado.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function entregarReembolso(int $citaId, array $datos, array $usuario): array
    {
        try {
            return DB::transaction(function () use ($citaId, $datos, $usuario): array {
                $cita = $this->citas->findRawById($citaId, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                if ((string) $cita['estado_pago'] !== 'reembolso_pendiente') {
                    throw new HttpException(
                        409,
                        'La cita no tiene un reembolso pendiente.',
                        [],
                        'INVALID_STATE'
                    );
                }

                $usuarioId = (int) ($usuario['id'] ?? 0);
                $monto = (float) $cita['reembolso_monto'] > 0
                    ? (float) $cita['reembolso_monto']
                    : (float) $cita['adelanto'];

                $this->pagos->crear([
                    'paciente_id' => (int) $cita['paciente_id'],
                    'medico_id' => (int) $cita['medico_id'],
                    'registrado_por' => $usuarioId,
                    'monto' => number_format($monto, 2, '.', ''),
                    'metodo' => $this->metodo($datos['metodo'] ?? 'efectivo'),
                    'referencia_paciente' => $datos['referencia_paciente'] ?? null,
                    'cita_id' => $citaId,
                    'tipo' => 'reembolso',
                    'estado' => 'confirmado',
                    'referencia' => (string) $cita['reembolso_codigo'],
                    'confirmado_por' => $usuarioId,
                    'confirmado_en' => date('Y-m-d H:i:sP'),
                    'reembolso_de' => $cita['pago_adelanto_id'] !== null
                        ? (int) $cita['pago_adelanto_id']
                        : null,
                ]);

                $this->citas->marcarReembolsado($citaId, $usuarioId);

                return $this->armarResumen($this->citas->findRawById($citaId) ?? $cita);
            });
        } catch (PDOException $exception) {
            throw $this->traducir($exception);
        }
    }

    /**
     * Marca como perdido el adelanto de una cita con inasistencia.
     */
    public function retenerAdelanto(int $citaId): void
    {
        $cita = $this->citas->findRawById($citaId);

        if ($cita === null) {
            return;
        }

        if (in_array((string) $cita['estado_pago'], ['adelanto_pagado', 'pagado'], true)) {
            $this->citas->marcarPerdido($citaId);
        }
    }

    /**
     * Libera los cupos cuyo adelanto no se pagó a tiempo.
     *
     * @return int Número de citas expiradas.
     */
    public function expirarPendientes(): int
    {
        $expiradas = 0;

        foreach ($this->citas->pendientesExpiradas() as $cita) {
            try {
                DB::transaction(function () use ($cita): void {
                    $id = (int) $cita['id'];
                    $actual = $this->citas->findRawById($id, true);

                    if ($actual === null || (string) $actual['estado_pago'] !== 'pendiente') {
                        return;
                    }

                    $this->citas->marcarExpirado($id);
                    $this->citas->cambiarEstado($id, 'cancelada', 'Adelanto no pagado');
                    $this->citas->registrarHistorial(
                        $id,
                        (string) $actual['estado'],
                        'cancelada',
                        null,
                        'Adelanto no pagado dentro del plazo'
                    );
                });

                $expiradas++;
            } catch (Throwable $exception) {
                logger($exception, ['cita' => $cita['id'] ?? null, 'contexto' => 'expirar_adelanto']);
            }
        }

        return $expiradas;
    }

    /**
     * Adelantos pendientes, saldos por cobrar y reembolsos por entregar.
     *
     * @return array{
     *     adelantos: array<int, array<string, mixed>>,
     *     saldos: array<int, array<string, mixed>>,
     *     reembolsos: array<int, array<string, mixed>>
     * }
     */
    public function pendientes(): array
    {
        return [
            'adelantos' => array_map([$this, 'normalizarPendiente'], $this->citas->adelantosPendientes()),
            'saldos' => array_map([$this, 'normalizarPendiente'], $this->citas->saldosPendientes()),
            'reembolsos' => array_map([$this, 'normalizarPendiente'], $this->citas->reembolsosPendientes()),
        ];
    }

    /** @param array<string, mixed> $cita @return array<string, mixed> */
    private function armarResumen(array $cita): array
    {
        $citaId = (int) $cita['id'];
        $config = $this->configuracion->pago();
        $pagos = $this->pagos->porCita($citaId);
        $adelanto = (float) ($cita['adelanto'] ?? 0);
        $precio = (float) ($cita['precio_total'] ?? 0);
        $saldo = (float) ($cita['saldo'] ?? 0);
        $estado = (string) ($cita['estado_pago'] ?? 'pendiente');
        $referencia = 'PAGO-' . (string) $cita['codigo'];

        return [
            'cita_id' => $citaId,
            'cita_codigo' => (string) $cita['codigo'],
            'paciente_id' => (int) $cita['paciente_id'],
            'medico_id' => (int) $cita['medico_id'],
            'precio_total' => $precio,
            'adelanto' => $adelanto,
            'saldo' => $saldo,
            'pagado' => round((float) $this->pagos->totalConfirmadoPorCita($citaId), 2),
            'estado_pago' => $estado,
            'expira_en' => $cita['pago_expira_en'] ?? null,
            'requiere_adelanto' => $estado === 'pendiente' && $adelanto > 0,
            'qr' => [
                'contenido' => $config['contenido'],
                'titular' => $config['titular'],
                'referencia' => $referencia,
                'monto' => $adelanto,
            ],
            'reembolso' => [
                'codigo' => $cita['reembolso_codigo'] ?? null,
                'monto' => (float) ($cita['reembolso_monto'] ?? 0),
                'solicitado_en' => $cita['reembolso_solicitado_en'] ?? null,
                'entregado_en' => $cita['reembolso_entregado_en'] ?? null,
                'estado' => $estado,
            ],
            'pagos' => $pagos,
        ];
    }

    /** @param array<string, mixed> $cita @return array<string, mixed> */
    private function normalizarPendiente(array $cita): array
    {
        $nombre = trim(
            (string) ($cita['paciente_nombres'] ?? '') . ' ' . (string) ($cita['paciente_apellidos'] ?? '')
        );

        return [
            'cita_id' => (int) $cita['id'],
            'codigo' => (string) $cita['codigo'],
            'fecha' => (string) $cita['fecha'],
            'hora_inicio' => substr((string) $cita['hora_inicio'], 0, 5),
            'estado' => (string) $cita['estado'],
            'estado_pago' => (string) $cita['estado_pago'],
            'precio_total' => (float) $cita['precio_total'],
            'adelanto' => (float) $cita['adelanto'],
            'saldo' => (float) $cita['saldo'],
            'expira_en' => $cita['pago_expira_en'] ?? null,
            'reembolso_codigo' => $cita['reembolso_codigo'] ?? null,
            'reembolso_monto' => (float) ($cita['reembolso_monto'] ?? 0),
            'reembolso_solicitado_en' => $cita['reembolso_solicitado_en'] ?? null,
            'paciente' => [
                'id' => (int) $cita['paciente_id'],
                'nombre_completo' => $nombre,
                'ci' => (string) ($cita['paciente_ci'] ?? ''),
                'telefono' => $cita['paciente_telefono'] ?? null,
            ],
        ];
    }

    private function nombrePaciente(int $pacienteId): string
    {
        $statement = DB::connection()->prepare(
            'SELECT nombres, apellidos FROM pacientes WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $pacienteId]);
        $fila = $statement->fetch();

        if ($fila === false) {
            return '';
        }

        return trim((string) $fila['nombres'] . ' ' . (string) $fila['apellidos']);
    }

    /** @param array<string, mixed> $datos */
    private function notificarRecepcion(string $asunto, array $datos): void
    {
        $destinatario = (string) config('mail.from', 'no-reply@vidasalud.test');
        $cuerpo = $asunto . "\n\n"
            . 'Comprobante: ' . ($datos['codigo'] ?? '') . "\n"
            . 'Cita: ' . ($datos['cita'] ?? '') . "\n"
            . 'Paciente: ' . ($datos['paciente'] ?? '') . "\n"
            . 'Monto: ' . number_format((float) ($datos['monto'] ?? 0), 2) . " Bs\n"
            . 'Fecha de la cita: ' . ($datos['fecha'] ?? '') . "\n\n"
            . 'El paciente debe presentar este comprobante en recepción.';

        $this->mail->queue($destinatario, 'Recepción — ' . $asunto, $cuerpo);
    }

    private function codigoReembolso(): string
    {
        for ($intento = 0; $intento < 5; $intento++) {
            $codigo = 'REE-' . strtoupper(bin2hex(random_bytes(3)));
            $statement = DB::connection()->prepare(
                'SELECT 1 FROM citas WHERE reembolso_codigo = :codigo LIMIT 1'
            );
            $statement->execute(['codigo' => $codigo]);

            if ($statement->fetchColumn() === false) {
                return $codigo;
            }
        }

        return 'REE-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function metodo(mixed $valor): string
    {
        $metodo = is_scalar($valor) ? strtolower(trim((string) $valor)) : '';

        if (!in_array($metodo, self::METODOS, true)) {
            throw new HttpException(
                422,
                'Revisa el método de pago.',
                [],
                'VALIDATION_ERROR',
                ['metodo' => 'Usa qr, efectivo, visa, mastercard o pasarela.']
            );
        }

        return $metodo;
    }

    private function monto(mixed $valor): float
    {
        if (!is_numeric($valor)) {
            throw new HttpException(
                422,
                'Revisa el monto.',
                [],
                'VALIDATION_ERROR',
                ['monto' => 'Ingresa un monto válido.']
            );
        }

        $monto = round((float) $valor, 2);

        if ($monto <= 0) {
            throw new HttpException(
                422,
                'Revisa el monto.',
                [],
                'VALIDATION_ERROR',
                ['monto' => 'El monto debe ser mayor a cero.']
            );
        }

        return $monto;
    }

    private function referencia(mixed $valor): ?string
    {
        $texto = $this->texto($valor, 80);

        return $texto === '' ? null : $texto;
    }

    private function texto(mixed $valor, int $maximo): string
    {
        if (!is_scalar($valor)) {
            return '';
        }

        $texto = preg_replace('/\s+/u', ' ', trim((string) $valor)) ?? trim((string) $valor);

        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $maximo);
        }

        return substr($texto, 0, $maximo);
    }

    private function traducir(PDOException $exception): Throwable
    {
        if (in_array((string) $exception->getCode(), ['23505', '23503', '23P01'], true)) {
            return new HttpException(409, 'No se pudo registrar el cobro.', [], 'CONFLICT');
        }

        return $exception;
    }
}
