<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\CitaModel;
use App\Models\ConfiguracionModel;
use App\Models\MedicoModel;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

/**
 * Cancelación con reprogramación sugerida o reembolso.
 *
 * Al cancelar, el sistema propone mover la cita a la misma hora del mismo día
 * de la semana siguiente. La sugerencia nunca se confirma sola: el paciente o
 * recepción acepta ese hueco o elige otra fecha/hora libre. Si prefiere no
 * reprogramar, se genera un comprobante de reembolso que recepción entrega.
 */
final class CancelacionService
{
    public function __construct(
        private ?CitaModel $citas = null,
        private ?MedicoModel $medicos = null,
        private ?DisponibilidadService $disponibilidad = null,
        private ?CobroService $cobros = null,
        private ?ConfiguracionModel $configuracion = null
    ) {
        $this->citas ??= new CitaModel();
        $this->medicos ??= new MedicoModel();
        $this->disponibilidad ??= new DisponibilidadService();
        $this->cobros ??= new CobroService();
        $this->configuracion ??= new ConfiguracionModel();
    }

    /**
     * Opciones de cancelación: sugerencia a +7 días, huecos de una fecha y
     * datos del reembolso si corresponde.
     *
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function opciones(int $citaId, array $usuario, ?string $fecha = null): array
    {
        $cita = $this->citas->findRawById($citaId);

        if ($cita === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        $this->autorizar($cita, $usuario);

        if (!in_array((string) $cita['estado'], ['programada', 'confirmada'], true)) {
            throw new HttpException(
                409,
                'Esta cita no puede cancelarse en su estado actual.',
                [],
                'INVALID_STATE'
            );
        }

        $medicoId = (int) $cita['medico_id'];
        $canal = $this->canal($usuario);
        $hora = substr((string) $cita['hora_inicio'], 0, 5);
        $sugerida = (new DateTimeImmutable((string) $cita['fecha']))
            ->modify('+7 days')
            ->format('Y-m-d');
        $fechaConsulta = $fecha !== null && DisponibilidadService::normalizarFecha($fecha) !== null
            ? $fecha
            : $sugerida;

        $sugerenciaDisponible = $this->disponibilidad->slotDisponible(
            $medicoId,
            $sugerida,
            $hora,
            $canal,
            $citaId
        );

        $slots = array_values(array_filter(
            $this->disponibilidad->slots($medicoId, $fechaConsulta, $canal, $citaId),
            static fn (array $slot): bool => $slot['disponible'] === true
        ));

        $estadoPago = (string) $cita['estado_pago'];
        $adelanto = (float) $cita['adelanto'];
        $montoReembolso = $estadoPago === 'pagado'
            ? (float) $cita['precio_total']
            : $adelanto;

        return [
            'cita_id' => $citaId,
            'codigo' => (string) $cita['codigo'],
            'fecha' => (string) $cita['fecha'],
            'hora_inicio' => $hora,
            'medico_id' => $medicoId,
            'especialidad_id' => (int) $cita['especialidad_id'],
            'precio_total' => (float) $cita['precio_total'],
            'adelanto' => $adelanto,
            'saldo' => (float) $cita['saldo'],
            'estado_pago' => $estadoPago,
            'puede_reprogramar' => true,
            'puede_reembolsar' => in_array($estadoPago, ['adelanto_pagado', 'pagado'], true),
            'reembolso' => [
                'monto' => $montoReembolso,
                'codigo' => $cita['reembolso_codigo'] ?? null,
                'estado' => $estadoPago,
            ],
            'sugerencia' => [
                'fecha' => $sugerida,
                'hora' => $hora,
                'disponible' => $sugerenciaDisponible,
                'descripcion' => 'Mismo día de la semana, misma hora, la próxima semana.',
            ],
            'fecha_consulta' => $fechaConsulta,
            'slots' => $slots,
            'politica' => $this->politica(),
        ];
    }

    /**
     * Reprograma la cita como reconsulta ligada a la cita origen. Si no se
     * indican fecha/hora se usa la sugerencia de +7 días.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function reprogramar(int $citaId, array $datos, array $usuario): array
    {
        $origen = $this->citas->findRawById($citaId);
        if ($origen === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        $this->autorizar($origen, $usuario);

        $horaSugerida = substr((string) $origen['hora_inicio'], 0, 5);
        $fechaSugerida = (new DateTimeImmutable((string) $origen['fecha']))
            ->modify('+7 days')
            ->format('Y-m-d');

        $fecha = $this->fecha($datos['fecha'] ?? $fechaSugerida);
        $hora = $this->hora($datos['hora'] ?? $horaSugerida);
        $motivo = $this->texto(
            $datos['motivo'] ?? 'Reprogramación solicitada por el paciente',
            200
        );

        if ($fecha === (string) $origen['fecha'] && $hora === $horaSugerida) {
            throw new HttpException(
                422,
                'Elige una fecha u hora distinta a la cita original.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'La reprogramación debe mover la cita.']
            );
        }

        $medico = $this->medicos->findActivoById((int) $origen['medico_id']);

        if ($medico === null) {
            throw new HttpException(409, 'El médico de la cita no está activo.', [], 'INVALID_STATE');
        }

        $canal = $this->canal($usuario);

        try {
            return DB::transaction(function () use (
                $citaId,
                $fecha,
                $hora,
                $motivo,
                $medico,
                $usuario,
                $canal
            ): array {
                if (!$this->citas->bloquearMedico((int) $medico['id'])) {
                    throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
                }

                $origen = $this->citas->findRawById($citaId, true);

                if ($origen === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                $this->autorizar($origen, $usuario);

                if (!in_array((string) $origen['estado'], ['programada', 'confirmada'], true)) {
                    throw new HttpException(
                        409,
                        'Esta cita no puede reprogramarse en su estado actual.',
                        [],
                        'INVALID_STATE'
                    );
                }

                if ((string) $origen['estado_pago'] === 'reembolso_pendiente') {
                    throw new HttpException(
                        409,
                        'La cita tiene un reembolso pendiente; recepción debe entregarlo antes de reprogramar.',
                        [],
                        'REFUND_PENDING'
                    );
                }

                if (!$this->disponibilidad->slotDisponible(
                    (int) $origen['medico_id'],
                    $fecha,
                    $hora,
                    $canal,
                    $citaId
                )) {
                    throw new HttpException(
                        409,
                        'El horario elegido ya no está disponible. Elige otro.',
                        [],
                        'SLOT_TAKEN'
                    );
                }

                $horaFin = DisponibilidadService::sumarMinutos(
                    $hora,
                    (int) $medico['duracion_cita_min']
                );

                $nueva = $this->citas->crearRecepcion([
                    'paciente_id' => (int) $origen['paciente_id'],
                    'medico_id' => (int) $origen['medico_id'],
                    'especialidad_id' => (int) $origen['especialidad_id'],
                    'fecha' => $fecha,
                    'hora_inicio' => $hora,
                    'hora_fin' => $horaFin,
                    'motivo' => $motivo,
                    'es_prioritaria' => false,
                    'es_reconsulta' => true,
                    'reconsulta_tipo' => 'reprogramacion',
                    'cita_origen_id' => $citaId,
                    'origen' => $canal === 'web' ? 'web' : 'recepcion',
                ]);

                $nuevaId = (int) $nueva['id'];
                $estadoPago = (string) $origen['estado_pago'];

                if (in_array($estadoPago, ['adelanto_pagado', 'pagado'], true)) {
                    $this->cobros->transferirAdelanto($nuevaId, $origen);
                } else {
                    $config = $this->configuracion->pago();
                    $precio = (float) $origen['precio_total'] > 0
                        ? (float) $origen['precio_total']
                        : $config['precio_consulta'];
                    $adelanto = (float) $origen['adelanto'] > 0
                        ? (float) $origen['adelanto']
                        : round($precio * ($config['adelanto_porcentaje'] / 100), 2);
                    $expira = (new DateTimeImmutable('now'))
                        ->modify('+' . $config['pago_expira_minutos'] . ' minutes')
                        ->format('Y-m-d H:i:sP');
                    $this->citas->aplicarCobroInicial($nuevaId, $precio, $adelanto, $expira);
                }

                $this->citas->marcarTransferido($citaId);
                $this->citas->cambiarEstado(
                    $citaId,
                    'cancelada',
                    'Reprogramada a la cita ' . (string) $nueva['codigo']
                );
                $this->citas->registrarHistorial(
                    $citaId,
                    (string) $origen['estado'],
                    'cancelada',
                    (int) ($usuario['id'] ?? 0) ?: null,
                    'Reprogramación a ' . (string) $nueva['codigo'] . ' (' . $fecha . ' ' . $hora . ')'
                );
                $this->citas->registrarHistorial(
                    $nuevaId,
                    null,
                    'programada',
                    (int) ($usuario['id'] ?? 0) ?: null,
                    'Reconsulta por reprogramación de ' . (string) $origen['codigo']
                );

                $detalleOrigen = $this->citas->findAdminById($citaId);
                $detalleNueva = $this->citas->findAdminById($nuevaId);

                return [
                    'cita_origen' => $detalleOrigen,
                    'reconsulta' => $detalleNueva,
                    'pago' => $detalleNueva !== null
                        ? $this->cobros->resumen($nuevaId)
                        : null,
                ];
            });
        } catch (PDOException $exception) {
            if (in_array((string) $exception->getCode(), ['23505', '23P01'], true)) {
                throw new HttpException(
                    409,
                    'El horario elegido ya no está disponible. Elige otro.',
                    [],
                    'SLOT_TAKEN'
                );
            }

            throw $exception;
        }
    }

    /**
     * Cancela definitivamente. Si hay adelanto pagado genera el comprobante
     * de reembolso; si no se pagó, solo libera el cupo.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function cancelarDefinitivo(int $citaId, array $datos, array $usuario): array
    {
        $cita = $this->citas->findRawById($citaId);

        if ($cita === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        $this->autorizar($cita, $usuario);

        $motivo = $this->texto($datos['motivo'] ?? '', 200);

        if ($motivo === '') {
            throw new HttpException(
                422,
                'Revisa los datos enviados.',
                [],
                'VALIDATION_ERROR',
                ['motivo' => 'Indica el motivo de la cancelación.']
            );
        }

        if (in_array((string) $cita['estado_pago'], ['adelanto_pagado', 'pagado'], true)) {
            $this->cobros->solicitarReembolso($citaId, ['motivo' => $motivo], $usuario);
            $detalle = $this->citas->findAdminById($citaId) ?? [];

            if ($detalle !== []) {
                $detalle['comprobante'] = $this->comprobante($citaId);
            }

            return $detalle;
        }

        DB::transaction(function () use ($citaId, $motivo, $usuario): void {
            $actual = $this->citas->findRawById($citaId, true);

            if ($actual === null) {
                throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
            }

            if (!in_array((string) $actual['estado'], ['programada', 'confirmada'], true)) {
                throw new HttpException(
                    409,
                    'Esta cita no puede cancelarse en su estado actual.',
                    [],
                    'INVALID_STATE'
                );
            }

            if ((string) $actual['estado_pago'] === 'pendiente') {
                $this->citas->marcarExpirado($citaId);
            }

            $this->citas->cambiarEstado($citaId, 'cancelada', $motivo);
            $this->citas->registrarHistorial(
                $citaId,
                (string) $actual['estado'],
                'cancelada',
                (int) ($usuario['id'] ?? 0) ?: null,
                $motivo
            );
        });

        return $this->citas->findAdminById($citaId) ?? [];
    }

    /**
     * Comprobante de reembolso para imprimir o presentar en recepción.
     *
     * @return array<string, mixed>
     */
    public function comprobante(int $citaId): array
    {
        $cita = $this->citas->findAdminById($citaId);

        if ($cita === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        return [
            'codigo' => (string) ($cita['reembolso_codigo'] ?? ''),
            'cita_codigo' => (string) $cita['codigo'],
            'paciente' => $cita['paciente']['nombre_completo'] ?? '',
            'ci' => $cita['paciente']['ci'] ?? '',
            'medico' => $cita['medico']['nombre_completo'] ?? '',
            'fecha_cita' => (string) $cita['fecha'],
            'hora_cita' => (string) $cita['hora_inicio'],
            'monto' => (float) ($cita['reembolso_monto'] ?? 0),
            'solicitado_en' => $cita['reembolso_solicitado_en'] ?? null,
            'estado' => (string) ($cita['estado_pago'] ?? ''),
            'instrucciones' => 'Presenta este comprobante en recepción para recibir el reembolso.',
        ];
    }

    /** @return array<string, mixed> */
    private function politica(): array
    {
        return [
            'adelanto_porcentaje' => $this->configuracion->adelantoPorcentaje(),
            'precio_consulta' => $this->configuracion->precioConsulta(),
            'reglas' => [
                'La cancelación con adelanto pagado permite reprogramar sin perder el monto.',
                'La sugerencia es el mismo día y hora de la semana siguiente; el paciente puede elegir otro horario libre.',
                'Si no desea reprogramar, se emite un comprobante de reembolso para recepción.',
                'La inasistencia retiene el adelanto.',
            ],
        ];
    }

    /** @param array<string, mixed> $cita @param array<string, mixed> $usuario */
    private function autorizar(array $cita, array $usuario): void
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        $medicoId = (int) ($cita['medico_id'] ?? 0);

        if ($rol === 'medico' && $medicoId === (int) ($usuario['medico_id'] ?? 0)) {
            return;
        }

        if ($rol === 'recepcionista_medico'
            && in_array($medicoId, array_map('intval', (array) ($usuario['medico_ids'] ?? [])), true)) {
            return;
        }

        if ($rol === 'paciente'
            && (int) ($cita['paciente_id'] ?? 0) === (int) ($usuario['paciente_id'] ?? 0)) {
            return;
        }

        throw new HttpException(403, 'No tienes permisos para esta cita.', [], 'FORBIDDEN');
    }

    /** @param array<string, mixed> $usuario */
    private function canal(array $usuario): string
    {
        return (string) ($usuario['rol'] ?? '') === 'paciente' ? 'web' : 'recepcion';
    }

    private function fecha(mixed $valor): string
    {
        $fecha = is_scalar($valor) ? trim((string) $valor) : '';

        if (DisponibilidadService::normalizarFecha($fecha) === null) {
            throw new HttpException(
                422,
                'Revisa la fecha de la reprogramación.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'Usa el formato YYYY-MM-DD.']
            );
        }

        if ($fecha < (new DateTimeImmutable('today'))->format('Y-m-d')) {
            throw new HttpException(
                422,
                'No se puede reprogramar a una fecha pasada.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'La fecha no puede ser pasada.']
            );
        }

        return $fecha;
    }

    private function hora(mixed $valor): string
    {
        $hora = is_scalar($valor) ? trim((string) $valor) : '';

        if (DisponibilidadService::normalizarHora($hora) === null) {
            throw new HttpException(
                422,
                'Revisa la hora de la reprogramación.',
                [],
                'VALIDATION_ERROR',
                ['hora' => 'Usa el formato HH:MM.']
            );
        }

        return $hora;
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
}
