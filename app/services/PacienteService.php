<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Models\PacienteModel;
use DateTimeImmutable;
use PDOException;

/**
 * Registro, búsqueda y edición de pacientes (Spec 02 / M-27).
 *
 * Las validaciones se hacen en el servidor y la CI se contrasta con la base
 * antes de insertar; el índice UNIQUE sigue siendo el respaldo ante carreras.
 */
final class PacienteService
{
    private const MAX_EDAD = 120;
    private const CAMPOS_PERFIL = [
        'telefono',
        'email',
        'direccion',
        'tipo_sangre',
        'alergias',
        'enfermedades_cronicas',
        'medicamentos',
        'antecedentes',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
    ];

    public function __construct(private ?PacienteModel $pacientes = null)
    {
        $this->pacientes ??= new PacienteModel();
    }

    /**
     * @param array{q?: string, estado?: string|null, atendidos?: bool} $filtros
     * @param array<int, int>|null $medicoIds
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function listar(array $filtros, int $page, int $perPage, ?array $medicoIds = null): array
    {
        $filtros['medico_ids'] = $medicoIds;
        $resultado = $this->pacientes->listarAdmin($filtros, $page, $perPage);

        return [
            'data' => array_map([$this, 'normalizar'], $resultado['data']),
            'total' => $resultado['total'],
        ];
    }

    /** @return array<string, mixed>|null */
    public function obtener(int $id): ?array
    {
        $paciente = $this->pacientes->findById($id);

        return $paciente === null ? null : $this->normalizar($paciente);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function crear(array $datos): array
    {
        $validado = $this->validarEntidad($datos, null);

        if ($this->pacientes->existeCi((string) $validado['ci'])) {
            throw new HttpException(
                409,
                'Ya existe un paciente con la CI ingresada.',
                [],
                'DUPLICATE_CI',
                ['ci' => 'La CI ya está registrada.']
            );
        }

        try {
            $paciente = $this->pacientes->crearAdmin($validado);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new HttpException(
                    409,
                    'Ya existe un paciente con la CI ingresada.',
                    [],
                    'DUPLICATE_CI',
                    ['ci' => 'La CI ya está registrada.']
                );
            }

            throw $exception;
        }

        return $this->normalizar($paciente);
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function actualizar(int $id, array $datos): array
    {
        $existente = $this->pacientes->findById($id);

        if ($existente === null) {
            throw new HttpException(404, 'Paciente no encontrado.', [], 'NOT_FOUND');
        }

        $validado = $this->validarEntidad($datos, $existente);

        if ($this->pacientes->existeCi((string) $validado['ci'], $id)) {
            throw new HttpException(
                409,
                'Ya existe un paciente con la CI ingresada.',
                [],
                'DUPLICATE_CI',
                ['ci' => 'La CI ya está registrada.']
            );
        }

        try {
            $this->pacientes->actualizarAdmin($id, $validado);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new HttpException(
                    409,
                    'Ya existe un paciente con la CI ingresada.',
                    [],
                    'DUPLICATE_CI',
                    ['ci' => 'La CI ya está registrada.']
                );
            }

            throw $exception;
        }

        $actualizado = $this->pacientes->findById($id);

        return $this->normalizar($actualizado ?? $existente);
    }

    /**
     * Edición del propio perfil: solo contacto, datos médicos y emergencia.
     *
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public function actualizarPerfil(int $id, array $datos): array
    {
        $existente = $this->pacientes->findById($id);

        if ($existente === null) {
            throw new HttpException(404, 'Paciente no encontrado.', [], 'NOT_FOUND');
        }

        $datos = array_intersect_key($datos, array_flip(self::CAMPOS_PERFIL));
        $validado = $this->validarEntidad($datos, $existente);
        $cambios = array_intersect_key($validado, array_flip(self::CAMPOS_PERFIL));

        if ($cambios !== []) {
            $this->pacientes->actualizarAdmin($id, $cambios);
        }

        $actualizado = $this->pacientes->findById($id);

        return $this->normalizar($actualizado ?? $existente);
    }

    /**
     * @return array{paciente: array<string, mixed>, citas_futuras: int}
     */
    public function cambiarEstado(int $id, string $estado): array
    {
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            throw new HttpException(
                422,
                'Revisa los datos enviados.',
                [],
                'VALIDATION_ERROR',
                ['estado' => 'Usa activo o inactivo.']
            );
        }

        $existente = $this->pacientes->findById($id);

        if ($existente === null) {
            throw new HttpException(404, 'Paciente no encontrado.', [], 'NOT_FOUND');
        }

        $this->pacientes->cambiarEstado($id, $estado);
        $actualizado = $this->pacientes->findById($id);

        return [
            'paciente' => $this->normalizar($actualizado ?? $existente),
            'citas_futuras' => $this->pacientes->contarCitasFuturas($id),
        ];
    }

    public function contarCitasFuturas(int $id): int
    {
        return $this->pacientes->contarCitasFuturas($id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function normalizar(array $row): array
    {
        $fechaNacimiento = (string) ($row['fecha_nacimiento'] ?? '');
        $nombres = (string) ($row['nombres'] ?? '');
        $apellidos = (string) ($row['apellidos'] ?? '');
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

        $row['id'] = (int) ($row['id'] ?? 0);
        $row['usuario_id'] = ($row['usuario_id'] ?? null) === null
            ? null
            : (int) $row['usuario_id'];
        $row['nombre_completo'] = trim($nombres . ' ' . $apellidos);
        $row['edad'] = $edad;
        $row['ultima_cita'] = $row['ultima_cita'] ?? null;
        $row['ultima_atencion'] = $row['ultima_atencion'] ?? null;

        return $row;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed>|null $existente
     * @return array<string, mixed>
     */
    private function validarEntidad(array $body, ?array $existente): array
    {
        $campos = [];
        $esCreacion = $existente === null;

        $ci = $this->textoDe($body, 'ci', $existente, $esCreacion);
        $nombres = $this->textoDe($body, 'nombres', $existente, $esCreacion);
        $apellidos = $this->textoDe($body, 'apellidos', $existente, $esCreacion);
        $fechaNacimiento = $this->textoDe($body, 'fecha_nacimiento', $existente, $esCreacion);
        $sexo = strtolower($this->textoDe($body, 'sexo', $existente, $esCreacion));
        $telefono = $this->textoOpcionalDe($body, 'telefono', $existente);
        $email = $this->textoOpcionalDe($body, 'email', $existente);
        $direccion = $this->textoOpcionalDe($body, 'direccion', $existente);
        $tipoSangre = $this->textoOpcionalDe($body, 'tipo_sangre', $existente);
        $alergias = $this->textoOpcionalDe($body, 'alergias', $existente);
        $enfermedades = $this->textoOpcionalDe($body, 'enfermedades_cronicas', $existente);
        $medicamentos = $this->textoOpcionalDe($body, 'medicamentos', $existente);
        $antecedentes = $this->textoOpcionalDe($body, 'antecedentes', $existente);
        $emergenciaNombre = $this->textoOpcionalDe($body, 'emergencia_nombre', $existente);
        $emergenciaParentesco = $this->textoOpcionalDe($body, 'emergencia_parentesco', $existente);
        $emergenciaTelefono = $this->textoOpcionalDe($body, 'emergencia_telefono', $existente);

        $ci = strtoupper($ci);
        $tipoSangre = $tipoSangre === null ? null : strtoupper($tipoSangre);

        if (preg_match('/^\d{5,10}(-[A-Z0-9]{1,2})?$/', $ci) !== 1) {
            $campos['ci'] = 'Ingresa una cédula válida.';
        }

        if (!$this->nombreValido($nombres)) {
            $campos['nombres'] = 'Ingresa los nombres (2 a 100 letras).';
        }

        if (!$this->nombreValido($apellidos)) {
            $campos['apellidos'] = 'Ingresa los apellidos (2 a 100 letras).';
        }

        if (!$this->fechaNacimientoValida($fechaNacimiento)) {
            $campos['fecha_nacimiento'] = 'Usa una fecha válida, no futura y menor a 120 años.';
        }

        if (!in_array($sexo, ['masculino', 'femenino'], true)) {
            $campos['sexo'] = 'Selecciona masculino o femenino.';
        }

        if ($telefono !== null && preg_match('/^\+?\d{7,15}$/', $telefono) !== 1) {
            $campos['telefono'] = 'Ingresa un teléfono de 7 a 15 dígitos.';
        }

        if ($email !== null && (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 120)) {
            $campos['email'] = 'Ingresa un correo válido.';
        }

        if ($direccion !== null && $this->longitud($direccion) > 200) {
            $campos['direccion'] = 'La dirección no puede superar 200 caracteres.';
        }

        if ($tipoSangre !== null && preg_match('/^(A|B|AB|O)[+-]$/', $tipoSangre) !== 1) {
            $campos['tipo_sangre'] = 'Usa un tipo de sangre válido (A+, O-, ...).';
        }

        foreach ([
            'alergias' => $alergias,
            'enfermedades_cronicas' => $enfermedades,
            'medicamentos' => $medicamentos,
            'antecedentes' => $antecedentes,
        ] as $campo => $valor) {
            if ($valor !== null && $this->longitud($valor) > 5000) {
                $campos[$campo] = 'El texto no puede superar 5000 caracteres.';
            }
        }

        if ($emergenciaNombre !== null && $this->longitud($emergenciaNombre) > 100) {
            $campos['emergencia_nombre'] = 'El nombre no puede superar 100 caracteres.';
        }

        if ($emergenciaParentesco !== null && $this->longitud($emergenciaParentesco) > 50) {
            $campos['emergencia_parentesco'] = 'El parentesco no puede superar 50 caracteres.';
        }

        if ($emergenciaTelefono !== null
            && (preg_match('/^\+?\d{7,15}$/', $emergenciaTelefono) !== 1
                || strlen($emergenciaTelefono) > 20)) {
            $campos['emergencia_telefono'] = 'Ingresa un teléfono de emergencia válido.';
        }

        if ($campos !== []) {
            throw new HttpException(
                422,
                'Revisa los datos del paciente.',
                [],
                'VALIDATION_ERROR',
                $campos
            );
        }

        return [
            'ci' => $ci,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'fecha_nacimiento' => $fechaNacimiento,
            'sexo' => $sexo,
            'telefono' => $telefono,
            'email' => $email,
            'direccion' => $direccion,
            'tipo_sangre' => $tipoSangre,
            'alergias' => $alergias,
            'enfermedades_cronicas' => $enfermedades,
            'medicamentos' => $medicamentos,
            'antecedentes' => $antecedentes,
            'emergencia_nombre' => $emergenciaNombre,
            'emergencia_parentesco' => $emergenciaParentesco,
            'emergencia_telefono' => $emergenciaTelefono,
        ];
    }

    /** @param array<string, mixed> $body @param array<string, mixed>|null $existente */
    private function textoDe(array $body, string $campo, ?array $existente, bool $esCreacion): string
    {
        if (array_key_exists($campo, $body) || $esCreacion) {
            return $this->limpiar($body[$campo] ?? '');
        }

        return $this->limpiar($existente[$campo] ?? '');
    }

    /** @param array<string, mixed> $body @param array<string, mixed>|null $existente */
    private function textoOpcionalDe(array $body, string $campo, ?array $existente): ?string
    {
        if (array_key_exists($campo, $body)) {
            return $this->limpiarOpcional($body[$campo] ?? null);
        }

        return $this->limpiarOpcional($existente[$campo] ?? null);
    }

    private function limpiar(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $texto = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    private function limpiarOpcional(mixed $value): ?string
    {
        $texto = $this->limpiar($value);

        return $texto === '' ? null : $texto;
    }

    private function nombreValido(string $nombre): bool
    {
        $longitud = $this->longitud($nombre);

        return $longitud >= 2
            && $longitud <= 100
            && preg_match("/^[\\p{L}][\\p{L}\\s'.-]*$/u", $nombre) === 1;
    }

    private function fechaNacimientoValida(string $fecha): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if ($valor === false || $valor->format('Y-m-d') !== $fecha) {
            return false;
        }

        $hoy = new DateTimeImmutable('today');

        return $valor <= $hoy
            && $valor >= $hoy->modify('-' . self::MAX_EDAD . ' years')
            && $valor >= new DateTimeImmutable('1900-01-01');
    }

    private function longitud(string $texto): int
    {
        return function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
    }
}
