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
use PDOException;

/**
 * Reserva web de una cita (M-16).
 *
 * Usa la conexión propietaria durante la transacción porque el rol `paciente`
 * solo puede leer sus propias citas y necesita ver toda la agenda del médico
 * para no duplicar horarios. Las validaciones de negocio se hacen aquí.
 */
final class ReservaWebService
{
    public function __construct(
        private ?PacienteModel $pacientes = null,
        private ?MedicoModel $medicos = null,
        private ?EspecialidadModel $especialidades = null,
        private ?CitaModel $citas = null,
        private ?DisponibilidadService $disponibilidad = null,
        private ?FichaService $fichas = null,
        private ?MailService $mail = null
    ) {
        $this->pacientes ??= new PacienteModel();
        $this->medicos ??= new MedicoModel();
        $this->especialidades ??= new EspecialidadModel();
        $this->citas ??= new CitaModel();
        $this->disponibilidad ??= new DisponibilidadService();
        $this->fichas ??= new FichaService();
        $this->mail ??= new MailService();
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed>|null $usuario
     * @return array{cita: array<string, mixed>, ficha: array<string, mixed>}
     */
    public function reservar(array $datos, ?array $usuario, string $ip = ''): array
    {
        if ($usuario === null || (string) ($usuario['rol'] ?? '') !== 'paciente') {
            throw new HttpException(401, 'Inicia sesión como paciente para reservar.', [], 'UNAUTHENTICATED');
        }

        $pacienteId = (int) ($usuario['paciente_id'] ?? 0);

        if ($pacienteId <= 0) {
            throw new HttpException(403, 'La cuenta no tiene un perfil de paciente asociado.', [], 'FORBIDDEN');
        }

        $medicoId = (int) ($datos['medico_id'] ?? 0);
        $especialidadId = (int) ($datos['especialidad_id'] ?? 0);
        $fecha = trim((string) ($datos['fecha'] ?? ''));
        $hora = trim((string) ($datos['hora'] ?? ''));
        $motivo = trim((string) ($datos['motivo'] ?? ''));

        $campos = [];

        if ($medicoId <= 0) {
            $campos['medico_id'] = 'Selecciona un médico.';
        }

        if ($especialidadId <= 0) {
            $campos['especialidad_id'] = 'Selecciona una especialidad.';
        }

        if (DisponibilidadService::normalizarFecha($fecha) === null) {
            $campos['fecha'] = 'La fecha no es válida.';
        }

        if (DisponibilidadService::normalizarHora($hora) === null) {
            $campos['hora'] = 'La hora no es válida.';
        }

        if ($campos !== []) {
            throw new HttpException(422, 'Revisa los datos de la reserva.', [], 'VALIDATION_ERROR', $campos);
        }

        $motivo = function_exists('mb_substr') ? mb_substr($motivo, 0, 200) : substr($motivo, 0, 200);

        $hoy = new DateTimeImmutable('today');

        if (new DateTimeImmutable($fecha) < $hoy) {
            throw new HttpException(
                422,
                'No se pueden reservar citas en fechas pasadas.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'La fecha no puede ser pasada.']
            );
        }

        $paciente = $this->pacientes->findById($pacienteId);

        if ($paciente === null || (string) $paciente['estado'] !== 'activo') {
            throw new HttpException(403, 'El paciente no está activo.', [], 'FORBIDDEN');
        }

        $medico = $this->medicos->findActivoById($medicoId);

        if ($medico === null) {
            throw new HttpException(
                422,
                'El médico no está disponible para nuevas citas.',
                [],
                'VALIDATION_ERROR',
                ['medico_id' => 'Médico inactivo o inexistente.']
            );
        }

        $especialidad = $this->especialidades->findActivaById($especialidadId);

        if ($especialidad === null) {
            throw new HttpException(
                422,
                'La especialidad no está disponible.',
                [],
                'VALIDATION_ERROR',
                ['especialidad_id' => 'Especialidad inactiva o inexistente.']
            );
        }

        if (!$this->medicos->tieneEspecialidadActiva($medicoId, $especialidadId)) {
            throw new HttpException(
                422,
                'El médico no atiende esa especialidad.',
                [],
                'VALIDATION_ERROR',
                ['especialidad_id' => 'El médico no tiene asignada la especialidad.']
            );
        }

        if (!$this->especialidades->esElegiblePaciente($especialidad, $paciente)) {
            throw new HttpException(
                403,
                'La especialidad no está disponible para este paciente.',
                [],
                'NOT_ELIGIBLE'
            );
        }

        $usuarioSesion = $usuario;
        DB::clearContext();

        try {
            DB::setAuditContext((int) ($usuarioSesion['id'] ?? 0), $ip);

            $resultado = DB::transaction(function () use (
                $pacienteId,
                $medicoId,
                $especialidadId,
                $fecha,
                $hora,
                $motivo,
                $medico
            ): array {
                if (!$this->citas->bloquearMedico($medicoId)) {
                    throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
                }

                if (!$this->disponibilidad->slotDisponible($medicoId, $fecha, $hora, 'web')) {
                    throw new HttpException(
                        409,
                        'El horario ya no está disponible. Elige otro.',
                        [],
                        'SLOT_TAKEN'
                    );
                }

                $horaFin = DisponibilidadService::sumarMinutos($hora, (int) $medico['duracion_cita_min']);

                $cita = $this->citas->crearWeb(
                    $pacienteId,
                    $medicoId,
                    $especialidadId,
                    $fecha,
                    $hora,
                    $horaFin,
                    $motivo
                );

                $ficha = $this->fichas->emitir((int) $cita['id'], 'web', false);

                return ['cita' => $cita, 'ficha' => $ficha];
            });
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new HttpException(
                    409,
                    'El horario ya no está disponible. Elige otro.',
                    [],
                    'SLOT_TAKEN'
                );
            }

            throw $exception;
        } finally {
            DB::setContext($usuarioSesion);
        }

        $cita = $resultado['cita'];
        $ficha = $resultado['ficha'];

        if (!empty($paciente['email'])) {
            $this->mail->sendConfirmacionCita((string) $paciente['email'], [
                'fecha' => (string) $cita['fecha'],
                'hora' => substr((string) $cita['hora_inicio'], 0, 5),
                'medico' => (string) $medico['nombre_completo'],
                'especialidad' => (string) $especialidad['nombre'],
                'codigo' => (string) $cita['codigo'],
            ]);
        }

        return [
            'cita' => [
                'id' => (int) $cita['id'],
                'codigo' => (string) $cita['codigo'],
                'fecha' => (string) $cita['fecha'],
                'hora_inicio' => substr((string) $cita['hora_inicio'], 0, 5),
                'hora_fin' => substr((string) $cita['hora_fin'], 0, 5),
                'estado' => (string) $cita['estado'],
                'origen' => (string) $cita['origen'],
            ],
            'ficha' => [
                'codigo' => (string) $ficha['codigo'],
                'codigo_acceso' => (string) $ficha['codigo_acceso'],
            ],
        ];
    }
}
