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
 * Flujo de negocio del kiosco (Spec 09).
 *
 * Cubre identificación por CI, alta rápida sin cuenta, catálogos con
 * restricciones de sexo/edad, disponibilidad con cupo kiosco y reserva.
 * La emisión de la ficha se resuelve en `FichaService` (M-25).
 */
final class KioscoService
{
    private const MAX_EDAD = 120;

    public function __construct(
        private ?PacienteModel $pacientes = null,
        private ?MedicoModel $medicos = null,
        private ?EspecialidadModel $especialidades = null,
        private ?CitaModel $citas = null,
        private ?DisponibilidadService $disponibilidad = null
    ) {
        $this->pacientes ??= new PacienteModel();
        $this->medicos ??= new MedicoModel();
        $this->especialidades ??= new EspecialidadModel();
        $this->citas ??= new CitaModel();
        $this->disponibilidad ??= new DisponibilidadService();
    }

    /**
     * @return array{existe: bool, requiere_registro: bool, paciente?: array<string, mixed>}
     */
    public function identificar(mixed $ci): array
    {
        $ciNormalizada = $this->normalizarCi($ci);

        if ($ciNormalizada === null) {
            throw new HttpException(
                422,
                'Revisa los datos del paciente.',
                [],
                'VALIDATION_ERROR',
                ['ci' => 'Ingrese una cédula válida.']
            );
        }

        $paciente = $this->pacientes->findByCi($ciNormalizada);

        if ($paciente === null) {
            return [
                'existe' => false,
                'requiere_registro' => true,
            ];
        }

        return [
            'existe' => true,
            'requiere_registro' => false,
            'paciente' => $this->normalizarPaciente($paciente),
        ];
    }

    /**
     * Alta rápida de un paciente sin cuenta (RFK-A).
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function registrar(array $datos): array
    {
        $campos = [];

        $ci = $this->normalizarCi($datos['ci'] ?? null);

        if ($ci === null) {
            $campos['ci'] = 'Ingrese una cédula válida.';
        }

        $nombres = $this->validarNombre($datos['nombres'] ?? null, 'nombres', 'Ingresa los nombres.', $campos);
        $apellidos = $this->validarNombre($datos['apellidos'] ?? null, 'apellidos', 'Ingresa los apellidos.', $campos);
        $fechaNacimiento = $this->validarFechaNacimiento($datos['fecha_nacimiento'] ?? null, $campos);
        $sexo = $this->validarSexo($datos['sexo'] ?? null, $campos);
        $telefono = $this->validarTelefono($datos['telefono'] ?? null, $campos);

        if ($campos !== []) {
            throw new HttpException(
                422,
                'Revisa los datos del paciente.',
                [],
                'VALIDATION_ERROR',
                $campos
            );
        }

        if ($this->pacientes->findByCi((string) $ci) !== null) {
            throw new HttpException(
                409,
                'Ya existe un paciente con esa CI. Consulta en recepción.',
                ['ci' => 'La CI ya está registrada.'],
                'CI_TAKEN'
            );
        }

        try {
            $paciente = $this->pacientes->crear([
                'ci' => (string) $ci,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'fecha_nacimiento' => $fechaNacimiento,
                'sexo' => $sexo,
                'telefono' => $telefono,
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new HttpException(
                    409,
                    'Ya existe un paciente con esa CI. Consulta en recepción.',
                    ['ci' => 'La CI ya está registrada.'],
                    'CI_TAKEN'
                );
            }

            throw $exception;
        }

        return $this->normalizarPaciente($paciente);
    }

    /**
     * Especialidades activas. Si se conoce al paciente, se filtran las que
     * no cumplen sus restricciones de sexo/edad (RFK-C).
     *
     * @return array<int, array<string, mixed>>
     */
    public function especialidades(?int $pacienteId = null): array
    {
        $especialidades = $this->especialidades->listActivas();

        if ($pacienteId === null) {
            return $especialidades;
        }

        $paciente = $this->pacienteActivo($pacienteId);

        return array_values(array_filter(
            $especialidades,
            fn (array $especialidad): bool => $this->especialidades->esElegiblePaciente($especialidad, $paciente)
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function medicos(mixed $especialidadId, ?int $pacienteId = null): array
    {
        $especialidad = $this->especialidadDe($especialidadId);

        if ($pacienteId !== null) {
            $paciente = $this->pacienteActivo($pacienteId);

            if (!$this->especialidades->esElegiblePaciente($especialidad, $paciente)) {
                throw new HttpException(
                    403,
                    'La especialidad no está disponible para el paciente.',
                    [],
                    'NOT_ELIGIBLE'
                );
            }
        }

        return $this->medicos->listActivos((int) $especialidad['id']);
    }

    /**
     * Slots con cupo kiosco disponible (RFK-B).
     *
     * @return array<string, mixed>
     */
    public function disponibilidad(mixed $medicoId, mixed $fecha, ?int $pacienteId = null): array
    {
        $campos = [];

        if (!is_scalar($medicoId) || !ctype_digit((string) $medicoId) || (int) $medicoId <= 0) {
            $campos['medico'] = 'Selecciona un médico válido.';
        }

        $fecha = is_scalar($fecha) ? trim((string) $fecha) : '';
        $fechaValida = DisponibilidadService::normalizarFecha($fecha);

        if ($fechaValida === null) {
            $campos['fecha'] = 'Usa el formato YYYY-MM-DD.';
        } elseif (new DateTimeImmutable($fechaValida) < new DateTimeImmutable('today')) {
            $campos['fecha'] = 'La fecha no puede ser pasada.';
        }

        if ($campos !== []) {
            throw new HttpException(
                422,
                'Revisa los parámetros de disponibilidad.',
                [],
                'VALIDATION_ERROR',
                $campos
            );
        }

        $medico = $this->medicos->findActivoById((int) $medicoId);

        if ($medico === null) {
            throw new HttpException(
                404,
                'El médico no existe o está inactivo.',
                [],
                'NOT_FOUND'
            );
        }

        if ($pacienteId !== null) {
            $this->pacienteActivo($pacienteId);
        }

        return [
            'fecha' => $fechaValida,
            'medico_id' => (int) $medico['id'],
            'medico' => trim((string) $medico['nombres'] . ' ' . (string) $medico['apellidos']),
            'canal' => 'kiosco',
            'duracion_cita_min' => (int) $medico['duracion_cita_min'],
            'slots' => $this->disponibilidad->slots((int) $medico['id'], $fechaValida, 'kiosco'),
        ];
    }

    /**
     * Reserva una cita desde el kiosco (RFK-G). Revalida el cupo dentro de
     * una transacción y serializa al médico para evitar dobles reservas.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function reservar(array $datos, string $ip = ''): array
    {
        $pacienteId = (int) ($datos['paciente_id'] ?? 0);
        $medicoId = (int) ($datos['medico_id'] ?? 0);
        $especialidadId = (int) ($datos['especialidad_id'] ?? 0);
        $fecha = is_scalar($datos['fecha'] ?? null) ? trim((string) $datos['fecha']) : '';
        $hora = is_scalar($datos['hora'] ?? null) ? trim((string) $datos['hora']) : '';
        $motivo = is_scalar($datos['motivo'] ?? null) ? trim((string) $datos['motivo']) : '';

        $campos = [];

        if ($pacienteId <= 0) {
            $campos['paciente_id'] = 'Identifica al paciente antes de reservar.';
        }

        if ($medicoId <= 0) {
            $campos['medico_id'] = 'Selecciona un médico.';
        }

        if ($especialidadId <= 0) {
            $campos['especialidad_id'] = 'Selecciona una especialidad.';
        }

        $fechaValida = DisponibilidadService::normalizarFecha($fecha);

        if ($fechaValida === null) {
            $campos['fecha'] = 'La fecha no es válida.';
        }

        if (DisponibilidadService::normalizarHora($hora) === null) {
            $campos['hora'] = 'La hora no es válida.';
        }

        if ($campos !== []) {
            throw new HttpException(
                422,
                'Revisa los datos de la reserva.',
                [],
                'VALIDATION_ERROR',
                $campos
            );
        }

        if (new DateTimeImmutable($fechaValida) < new DateTimeImmutable('today')) {
            throw new HttpException(
                422,
                'No se pueden reservar citas en fechas pasadas.',
                [],
                'VALIDATION_ERROR',
                ['fecha' => 'La fecha no puede ser pasada.']
            );
        }

        $paciente = $this->pacienteActivo($pacienteId);
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
                'La especialidad no está disponible para el paciente.',
                [],
                'NOT_ELIGIBLE'
            );
        }

        $motivo = function_exists('mb_substr')
            ? mb_substr($motivo, 0, 200)
            : substr($motivo, 0, 200);

        DB::setAuditContext(0, $ip);

        try {
            $cita = DB::transaction(function () use (
                $pacienteId,
                $medicoId,
                $especialidadId,
                $fechaValida,
                $hora,
                $motivo,
                $medico
            ): array {
                if (!$this->citas->bloquearMedico($medicoId)) {
                    throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
                }

                if (!$this->disponibilidad->slotDisponible($medicoId, $fechaValida, $hora, 'kiosco')) {
                    throw new HttpException(
                        409,
                        'El horario ya no está disponible. Elige otro.',
                        [],
                        'SLOT_TAKEN'
                    );
                }

                $horaFin = DisponibilidadService::sumarMinutos(
                    $hora,
                    (int) $medico['duracion_cita_min']
                );

                return $this->citas->crearKiosco(
                    $pacienteId,
                    $medicoId,
                    $especialidadId,
                    $fechaValida,
                    $hora,
                    $horaFin,
                    $motivo
                );
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
        }

        return [
            'id' => (int) $cita['id'],
            'codigo' => (string) $cita['codigo'],
            'paciente_id' => $pacienteId,
            'medico_id' => $medicoId,
            'especialidad_id' => $especialidadId,
            'fecha' => (string) $cita['fecha'],
            'hora_inicio' => substr((string) $cita['hora_inicio'], 0, 5),
            'hora_fin' => substr((string) $cita['hora_fin'], 0, 5),
            'estado' => (string) $cita['estado'],
            'origen' => (string) $cita['origen'],
            'paciente' => $this->normalizarPaciente($paciente),
            'medico' => trim((string) $medico['nombres'] . ' ' . (string) $medico['apellidos']),
            'especialidad' => (string) $especialidad['nombre'],
        ];
    }

    /**
     * Acepta CIs de 5 a 10 dígitos con un complemento alfanumérico opcional
     * (por ejemplo `12345678-1A`). Se normaliza a mayúsculas.
     */
    private function normalizarCi(mixed $ci): ?string
    {
        $valor = is_scalar($ci) ? strtoupper(trim((string) $ci)) : '';

        return preg_match('/^\d{5,10}(-[A-Z0-9]{1,2})?$/', $valor) === 1 ? $valor : null;
    }

    /**
     * @param array<string, string> $campos
     */
    private function validarNombre(mixed $valor, string $campo, string $mensaje, array &$campos): string
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';

        if (preg_match("/^[\\p{L}][\\p{L}\\s'.-]{1,99}$/u", $texto) !== 1) {
            $campos[$campo] = $mensaje;

            return '';
        }

        return $texto;
    }

    /**
     * @param array<string, string> $campos
     */
    private function validarFechaNacimiento(mixed $valor, array &$campos): string
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);

        if ($fecha === false || $fecha->format('Y-m-d') !== $texto) {
            $campos['fecha_nacimiento'] = 'Usa el formato YYYY-MM-DD.';

            return '';
        }

        $hoy = new DateTimeImmutable('today');

        if ($fecha > $hoy) {
            $campos['fecha_nacimiento'] = 'La fecha no puede ser futura.';

            return '';
        }

        if ($fecha < $hoy->modify('-' . self::MAX_EDAD . ' years')) {
            $campos['fecha_nacimiento'] = 'La fecha no puede superar los ' . self::MAX_EDAD . ' años.';

            return '';
        }

        return $texto;
    }

    /**
     * @param array<string, string> $campos
     */
    private function validarSexo(mixed $valor, array &$campos): string
    {
        $texto = is_scalar($valor) ? strtolower(trim((string) $valor)) : '';

        if (!in_array($texto, ['masculino', 'femenino'], true)) {
            $campos['sexo'] = 'Selecciona masculino o femenino.';

            return '';
        }

        return $texto;
    }

    /**
     * @param array<string, string> $campos
     */
    private function validarTelefono(mixed $valor, array &$campos): string
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';

        if (preg_match('/^\+?\d{7,15}$/', $texto) !== 1) {
            $campos['telefono'] = 'Ingresa un teléfono de 7 a 15 dígitos.';

            return '';
        }

        return $texto;
    }

    /** @return array<string, mixed> */
    private function pacienteActivo(int $pacienteId): array
    {
        $paciente = $this->pacientes->findById($pacienteId);

        if ($paciente === null) {
            throw new HttpException(404, 'Paciente no encontrado.', [], 'NOT_FOUND');
        }

        if ((string) $paciente['estado'] !== 'activo') {
            throw new HttpException(
                403,
                'El paciente no está activo. Consulta en recepción.',
                [],
                'PATIENT_INACTIVE'
            );
        }

        return $paciente;
    }

    /** @return array<string, mixed> */
    private function especialidadDe(mixed $especialidadId): array
    {
        if (!is_scalar($especialidadId) || !ctype_digit((string) $especialidadId) || (int) $especialidadId <= 0) {
            throw new HttpException(
                422,
                'Revisa los filtros de búsqueda.',
                [],
                'VALIDATION_ERROR',
                ['especialidad' => 'Selecciona una especialidad válida.']
            );
        }

        $especialidad = $this->especialidades->findActivaById((int) $especialidadId);

        if ($especialidad === null) {
            throw new HttpException(
                404,
                'La especialidad no existe o está inactiva.',
                [],
                'NOT_FOUND'
            );
        }

        return $especialidad;
    }

    /**
     * Datos mínimos que el kiosco necesita mostrar. No expone información
     * clínica ni de contacto completa.
     *
     * @param array<string, mixed> $paciente
     * @return array<string, mixed>
     */
    private function normalizarPaciente(array $paciente): array
    {
        $fechaNacimiento = (string) ($paciente['fecha_nacimiento'] ?? '');
        $nombres = (string) ($paciente['nombres'] ?? '');
        $apellidos = (string) ($paciente['apellidos'] ?? '');
        $edad = null;

        if ($fechaNacimiento !== '') {
            try {
                $edad = (new DateTimeImmutable($fechaNacimiento))
                    ->diff(new DateTimeImmutable('today'))
                    ->y;
            } catch (\Throwable) {
                $edad = null;
            }
        }

        return [
            'id' => (int) ($paciente['id'] ?? 0),
            'codigo' => (string) ($paciente['codigo'] ?? ''),
            'ci' => (string) ($paciente['ci'] ?? ''),
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'nombre_completo' => trim($nombres . ' ' . $apellidos),
            'fecha_nacimiento' => $fechaNacimiento,
            'edad' => $edad,
            'sexo' => (string) ($paciente['sexo'] ?? ''),
            'telefono' => $paciente['telefono'] === null ? null : (string) $paciente['telefono'],
            'estado' => (string) ($paciente['estado'] ?? ''),
        ];
    }
}
