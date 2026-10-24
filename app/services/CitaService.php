<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\CitaModel;
use App\Models\EspecialidadModel;
use App\Models\MedicoModel;
use App\Models\PacienteModel;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

/**
 * Operaciones de recepción sobre citas (Specs 06 y 07 / M-27).
 *
 * Toda escritura ocurre dentro de una transacción, bloquea al médico para
 * serializar reservas y revalida el cupo/horario en el servidor.
 */
final class CitaService
{
    private const ESTADOS = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];
    private const TIPOS_RECONSULTA = ['ausencia_medico', 'seguimiento'];

    public function __construct(
        private ?CitaModel $citas = null,
        private ?PacienteModel $pacientes = null,
        private ?MedicoModel $medicos = null,
        private ?EspecialidadModel $especialidades = null,
        private ?DisponibilidadService $disponibilidad = null
    ) {
        $this->citas ??= new CitaModel();
        $this->pacientes ??= new PacienteModel();
        $this->medicos ??= new MedicoModel();
        $this->especialidades ??= new EspecialidadModel();
        $this->disponibilidad ??= new DisponibilidadService();
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function crear(array $datos, array $usuario): array
    {
        $pacienteId = $this->idPositivo($datos['paciente_id'] ?? null, 'paciente_id');
        $medicoId = $this->idPositivo($datos['medico_id'] ?? null, 'medico_id');
        $especialidadId = $this->idPositivo($datos['especialidad_id'] ?? null, 'especialidad_id');
        $fecha = $this->fecha($datos['fecha'] ?? null);
        $hora = $this->hora($datos['hora'] ?? null);
        $motivo = $this->motivo($datos['motivo'] ?? null);
        $esPrioritaria = $this->booleano($datos['es_prioritaria'] ?? false);

        $this->autorizarMedico($medicoId, $usuario);
        $this->asegurarFechaNoPasada($fecha);

        $paciente = $this->pacientes->findById($pacienteId);

        if ($paciente === null) {
            $this->validacion(['paciente_id' => 'El paciente no existe.']);
        }

        if ((string) $paciente['estado'] !== 'activo') {
            throw new HttpException(
                409,
                'El paciente está inactivo y no puede agendar nuevas citas.',
                [],
                'PATIENT_INACTIVE'
            );
        }

        $medico = $this->medicos->findActivoById($medicoId);

        if ($medico === null) {
            $this->validacion(['medico_id' => 'Médico inactivo o inexistente.']);
        }

        $especialidad = $this->especialidades->findActivaById($especialidadId);

        if ($especialidad === null) {
            $this->validacion(['especialidad_id' => 'Especialidad inactiva o inexistente.']);
        }

        if (!$this->medicos->tieneEspecialidadActiva($medicoId, $especialidadId)) {
            $this->validacion(['especialidad_id' => 'El médico no tiene asignada la especialidad.']);
        }

        if (!$this->especialidades->esElegiblePaciente($especialidad, $paciente)) {
            throw new HttpException(
                403,
                'La especialidad no está disponible para este paciente.',
                [],
                'NOT_ELIGIBLE'
            );
        }

        try {
            $cita = DB::transaction(function () use (
                $pacienteId,
                $medicoId,
                $especialidadId,
                $fecha,
                $hora,
                $motivo,
                $esPrioritaria,
                $medico,
                $usuario
            ): array {
                if (!$this->citas->bloquearMedico($medicoId)) {
                    throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
                }

                $this->asegurarSlot($medicoId, $fecha, $hora);
                $horaFin = DisponibilidadService::sumarMinutos($hora, (int) $medico['duracion_cita_min']);

                $nueva = $this->citas->crearRecepcion([
                    'paciente_id' => $pacienteId,
                    'medico_id' => $medicoId,
                    'especialidad_id' => $especialidadId,
                    'fecha' => $fecha,
                    'hora_inicio' => $hora,
                    'hora_fin' => $horaFin,
                    'motivo' => $motivo,
                    'es_prioritaria' => $esPrioritaria,
                ]);

                $this->citas->registrarHistorial(
                    (int) $nueva['id'],
                    null,
                    'programada',
                    (int) ($usuario['id'] ?? 0) ?: null,
                    'Cita registrada en recepción'
                );

                return $nueva;
            });
        } catch (PDOException $exception) {
            $this->traducirConflicto($exception);
        }

        return $this->detalle((int) $cita['id']);
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function reprogramar(int $id, array $datos, array $usuario): array
    {
        $fecha = $this->fecha($datos['fecha'] ?? null);
        $hora = $this->hora($datos['hora'] ?? null);
        $motivo = array_key_exists('motivo', $datos) ? $this->motivo($datos['motivo']) : null;

        $this->asegurarFechaNoPasada($fecha);

        $existente = $this->citas->findRawById($id);

        if ($existente === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        $this->autorizarCita($existente, $usuario);

        if (!in_array((string) $existente['estado'], ['programada', 'confirmada'], true)) {
            throw new HttpException(
                409,
                'Esta cita no puede reprogramarse en su estado actual.',
                [],
                'INVALID_STATE'
            );
        }

        $medico = $this->medicos->findActivoById((int) $existente['medico_id']);

        if ($medico === null) {
            throw new HttpException(
                409,
                'El médico de la cita no está activo.',
                [],
                'INVALID_STATE'
            );
        }

        try {
            DB::transaction(function () use (
                $id,
                $fecha,
                $hora,
                $motivo,
                $medico,
                $usuario
            ): void {
                if (!$this->citas->bloquearMedico((int) $medico['id'])) {
                    throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
                }

                $cita = $this->citas->findRawById($id, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                $this->autorizarCita($cita, $usuario);

                if (!in_array((string) $cita['estado'], ['programada', 'confirmada'], true)) {
                    throw new HttpException(
                        409,
                        'Esta cita no puede reprogramarse en su estado actual.',
                        [],
                        'INVALID_STATE'
                    );
                }

                $this->asegurarSlot((int) $cita['medico_id'], $fecha, $hora, $id);
                $horaFin = DisponibilidadService::sumarMinutos($hora, (int) $medico['duracion_cita_min']);

                $this->citas->actualizarSlot($id, $fecha, $hora, $horaFin, $motivo);
            });
        } catch (PDOException $exception) {
            $this->traducirConflicto($exception);
        }

        return $this->detalle($id);
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function cancelar(int $id, array $datos, array $usuario): array
    {
        $motivo = $this->motivo($datos['motivo'] ?? null);

        if ($motivo === null) {
            $this->validacion(['motivo' => 'Indica el motivo de la cancelación.']);
        }

        try {
            DB::transaction(function () use ($id, $motivo, $usuario): void {
                $cita = $this->citas->findRawById($id, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                $this->autorizarCita($cita, $usuario);

                if (!in_array((string) $cita['estado'], ['programada', 'confirmada'], true)) {
                    throw new HttpException(
                        409,
                        'Esta cita no puede cancelarse en su estado actual.',
                        [],
                        'INVALID_STATE'
                    );
                }

                if ((string) $cita['fecha'] <= date('Y-m-d')) {
                    throw new HttpException(
                        422,
                        'Solo puede cancelar hasta el día anterior a la cita.',
                        [],
                        'CANCELLATION_TOO_LATE'
                    );
                }

                $this->citas->cambiarEstado($id, 'cancelada', $motivo);
                $this->citas->registrarHistorial(
                    $id,
                    (string) $cita['estado'],
                    'cancelada',
                    (int) ($usuario['id'] ?? 0) ?: null,
                    $motivo
                );
            });
        } catch (PDOException $exception) {
            $this->traducirConflicto($exception);
        }

        return $this->detalle($id);
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function atender(int $id, array $usuario): array
    {
        return $this->cambiarEstadoAsistencia($id, 'atendida', $usuario, 'Consulta realizada');
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function inasistencia(int $id, array $usuario): array
    {
        return $this->cambiarEstadoAsistencia($id, 'inasistencia', $usuario, 'Paciente no se presentó');
    }

    /**
     * Crea una reconsulta por ausencia del médico o por seguimiento.
     *
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array{cita_origen: array<string, mixed>, reconsulta: array<string, mixed>}
     */
    public function reconsulta(int $id, array $datos, array $usuario): array
    {
        $tipo = $this->tipoReconsulta($datos['tipo'] ?? null);
        $origen = $this->citas->findRawById($id);

        if ($origen === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        $this->autorizarCita($origen, $usuario);

        if ($tipo === 'ausencia_medico' && (string) ($usuario['rol'] ?? '') === 'medico') {
            throw new HttpException(
                403,
                'El médico solo puede crear reconsultas de seguimiento.',
                [],
                'FORBIDDEN'
            );
        }

        if ((int) ($origen['medico_id'] ?? 0) <= 0) {
            throw new HttpException(
                422,
                'La cita origen no tiene médico asignado.',
                [],
                'VALIDATION_ERROR'
            );
        }

        $fecha = array_key_exists('fecha', $datos) && $datos['fecha'] !== null && $datos['fecha'] !== ''
            ? $this->fecha($datos['fecha'])
            : (new DateTimeImmutable((string) $origen['fecha']))->modify('+7 days')->format('Y-m-d');
        $hora = array_key_exists('hora', $datos) && $datos['hora'] !== null && $datos['hora'] !== ''
            ? $this->hora($datos['hora'])
            : substr((string) $origen['hora_inicio'], 0, 5);
        $motivo = $this->motivo($datos['motivo'] ?? null);

        if ($motivo === null) {
            $motivo = $tipo === 'ausencia_medico'
                ? 'Reconsulta por ausencia del médico'
                : 'Reconsulta de seguimiento';
        }

        $this->asegurarFechaNoPasada($fecha);

        $medico = $this->medicos->findActivoById((int) $origen['medico_id']);

        if ($medico === null) {
            throw new HttpException(
                409,
                'El médico de la cita origen no está activo.',
                [],
                'INVALID_STATE'
            );
        }

        try {
            $resultado = DB::transaction(function () use (
                $id,
                $tipo,
                $fecha,
                $hora,
                $motivo,
                $medico,
                $usuario
            ): array {
                if (!$this->citas->bloquearMedico((int) $medico['id'])) {
                    throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
                }

                $origen = $this->citas->findRawById($id, true);

                if ($origen === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                $this->autorizarCita($origen, $usuario);
                $estadoOrigen = (string) $origen['estado'];

                if ($tipo === 'ausencia_medico') {
                    if (!in_array($estadoOrigen, ['programada', 'confirmada'], true)) {
                        throw new HttpException(
                            409,
                            'Esta cita no puede generar una reconsulta por ausencia en su estado actual.',
                            [],
                            'INVALID_STATE'
                        );
                    }

                    $this->citas->cambiarEstado($id, 'cancelada', 'Ausencia del médico');
                    $this->citas->registrarHistorial(
                        $id,
                        $estadoOrigen,
                        'cancelada',
                        (int) ($usuario['id'] ?? 0) ?: null,
                        'Ausencia del médico'
                    );
                } elseif ($estadoOrigen !== 'atendida') {
                    throw new HttpException(
                        409,
                        'La reconsulta de seguimiento requiere una cita atendida.',
                        [],
                        'INVALID_STATE'
                    );
                }

                $this->asegurarSlot((int) $origen['medico_id'], $fecha, $hora);
                $horaFin = DisponibilidadService::sumarMinutos($hora, (int) $medico['duracion_cita_min']);

                $nueva = $this->citas->crearRecepcion([
                    'paciente_id' => (int) $origen['paciente_id'],
                    'medico_id' => (int) $origen['medico_id'],
                    'especialidad_id' => (int) $origen['especialidad_id'],
                    'fecha' => $fecha,
                    'hora_inicio' => $hora,
                    'hora_fin' => $horaFin,
                    'motivo' => $motivo,
                    'es_prioritaria' => true,
                    'es_reconsulta' => true,
                    'reconsulta_tipo' => $tipo,
                    'cita_origen_id' => $id,
                ]);

                $this->citas->registrarHistorial(
                    (int) $nueva['id'],
                    null,
                    'programada',
                    (int) ($usuario['id'] ?? 0) ?: null,
                    $tipo === 'ausencia_medico'
                        ? 'Reconsulta por ausencia del médico'
                        : 'Reconsulta de seguimiento'
                );

                return [
                    'cita_origen' => $this->detalle($id),
                    'reconsulta' => $this->detalle((int) $nueva['id']),
                ];
            });
        } catch (PDOException $exception) {
            $this->traducirConflicto($exception);
        }

        return $resultado;
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function cambiarEstadoAsistencia(int $id, string $nuevo, array $usuario, string $observacion): array
    {
        try {
            DB::transaction(function () use ($id, $nuevo, $usuario, $observacion): void {
                $cita = $this->citas->findRawById($id, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                $this->autorizarCita($cita, $usuario);
                $anterior = (string) $cita['estado'];
                $permitidos = $nuevo === 'atendida'
                    ? ['confirmada']
                    : ['programada', 'confirmada'];

                if (!in_array($anterior, $permitidos, true)) {
                    throw new HttpException(
                        409,
                        'No se puede cambiar de ' . $anterior . ' a ' . $nuevo . '.',
                        [],
                        'INVALID_TRANSITION'
                    );
                }

                $this->citas->cambiarEstado($id, $nuevo);
                $this->citas->registrarHistorial(
                    $id,
                    $anterior,
                    $nuevo,
                    (int) ($usuario['id'] ?? 0) ?: null,
                    $observacion
                );
            });
        } catch (PDOException $exception) {
            $this->traducirConflicto($exception);
        }

        return $this->detalle($id);
    }

    /** @return array<string, mixed> */
    private function detalle(int $id): array
    {
        $cita = $this->citas->findAdminById($id);

        if ($cita === null) {
            throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
        }

        return $cita;
    }

    /**
     * @param array<string, mixed> $cita
     * @param array<string, mixed> $usuario
     */
    private function autorizarCita(array $cita, array $usuario): void
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        $medicoId = (int) ($cita['medico_id'] ?? 0);

        if ($rol === 'medico' && $medicoId === (int) ($usuario['medico_id'] ?? 0)) {
            return;
        }

        if ($rol === 'recepcionista_medico') {
            $medicoIds = array_map('intval', (array) ($usuario['medico_ids'] ?? []));

            if (in_array($medicoId, $medicoIds, true)) {
                return;
            }
        }

        if ($rol === 'paciente'
            && (int) ($cita['paciente_id'] ?? 0) === (int) ($usuario['paciente_id'] ?? 0)) {
            return;
        }

        throw new HttpException(403, 'No tienes permisos para esta cita.', [], 'FORBIDDEN');
    }

    /** @param array<string, mixed> $usuario */
    private function autorizarMedico(int $medicoId, array $usuario): void
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        if ($rol === 'recepcionista_medico'
            && in_array($medicoId, array_map('intval', (array) ($usuario['medico_ids'] ?? [])), true)) {
            return;
        }

        throw new HttpException(
            403,
            'No tienes permisos para agendar con este médico.',
            [],
            'FORBIDDEN'
        );
    }

    /**
     * Valida que el médico tenga la franja libre y que el horario exista.
     */
    private function asegurarSlot(int $medicoId, string $fecha, string $hora, ?int $excluirCitaId = null): void
    {
        if ($this->esPasado($fecha, $hora)) {
            throw new HttpException(
                422,
                'No se pueden programar citas en fechas pasadas.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'La fecha y hora ya pasaron.']
            );
        }

        if ($this->disponibilidad->slotDisponible($medicoId, $fecha, $hora, 'recepcion', $excluirCitaId)) {
            return;
        }

        foreach ($this->disponibilidad->slots($medicoId, $fecha, 'recepcion', $excluirCitaId) as $slot) {
            if ($slot['hora'] === $hora) {
                throw new HttpException(
                    409,
                    'El médico ya tiene una cita en ese horario.',
                    [],
                    'SLOT_TAKEN'
                );
            }
        }

        throw new HttpException(
            422,
            'El horario no está dentro de la atención del médico.',
            [],
            'SCHEDULE_OUTSIDE',
            ['hora' => 'Selecciona un horario de atención disponible.']
        );
    }

    private function esPasado(string $fecha, string $hora): bool
    {
        $zona = new DateTimeZone((string) config('app.timezone', 'America/La_Paz'));
        $inicio = new DateTimeImmutable($fecha . ' ' . $hora . ':00', $zona);

        return $inicio <= new DateTimeImmutable('now', $zona);
    }

    private function asegurarFechaNoPasada(string $fecha): void
    {
        if ($fecha < date('Y-m-d')) {
            throw new HttpException(
                422,
                'No se pueden programar citas en fechas pasadas.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'La fecha no puede ser pasada.']
            );
        }
    }

    private function idPositivo(mixed $value, string $campo): int
    {
        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
            $this->validacion([$campo => 'Selecciona un valor válido.']);
        }

        return (int) $value;
    }

    private function fecha(mixed $value): string
    {
        $fecha = is_scalar($value) ? trim((string) $value) : '';

        if (DisponibilidadService::normalizarFecha($fecha) === null) {
            $this->validacion(['fecha' => 'Usa el formato YYYY-MM-DD.']);
        }

        return $fecha;
    }

    private function hora(mixed $value): string
    {
        $hora = is_scalar($value) ? trim((string) $value) : '';

        if (DisponibilidadService::normalizarHora($hora) === null) {
            $this->validacion(['hora' => 'Usa el formato HH:MM.']);
        }

        return $hora;
    }

    private function motivo(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $texto = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? trim((string) $value);

        if ($texto === '') {
            return null;
        }

        if (function_exists('mb_strlen') && mb_strlen($texto) > 200) {
            $this->validacion(['motivo' => 'El motivo no puede superar 200 caracteres.']);
        }

        if (!function_exists('mb_strlen') && strlen($texto) > 200) {
            $this->validacion(['motivo' => 'El motivo no puede superar 200 caracteres.']);
        }

        return $texto;
    }

    private function booleano(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private function tipoReconsulta(mixed $value): string
    {
        $tipo = is_scalar($value) ? strtolower(trim((string) $value)) : '';

        if (!in_array($tipo, self::TIPOS_RECONSULTA, true)) {
            $this->validacion(['tipo' => 'Usa ausencia_medico o seguimiento.']);
        }

        return $tipo;
    }

    /** @param array<string, string> $campos */
    private function validacion(array $campos): never
    {
        throw new HttpException(
            422,
            'Revisa los datos de la cita.',
            [],
            'VALIDATION_ERROR',
            $campos
        );
    }

    private function traducirConflicto(PDOException $exception): never
    {
        if (in_array((string) $exception->getCode(), ['23505', '23P01'], true)) {
            throw new HttpException(
                409,
                'El médico ya tiene una cita en ese horario.',
                [],
                'SLOT_TAKEN'
            );
        }

        throw $exception;
    }
}
