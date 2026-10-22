<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\MedicoModel;
use App\Models\PacienteModel;
use App\Models\UsuarioModel;
use Throwable;

final class AuthService
{
    public function __construct(
        private ?UsuarioModel $usuarios = null,
        private ?PacienteModel $pacientes = null,
        private ?MedicoModel $medicos = null,
        private ?MailService $mail = null
    ) {
        $this->usuarios ??= new UsuarioModel();
        $this->pacientes ??= new PacienteModel();
        $this->medicos ??= new MedicoModel();
        $this->mail ??= new MailService();
    }

    /**
     * Intenta iniciar sesión y devuelve el usuario de sesión.
     *
     * @return array<string, mixed>
     */
    public function login(string $identificador, string $password): array
    {
        $identificador = trim($identificador);

        if ($identificador === '' || $password === '') {
            throw new HttpException(
                422,
                'Usuario/CI y contraseña son obligatorios.',
                [],
                'VALIDATION_ERROR'
            );
        }

        $usuario = null;
        $paciente = null;

        // El paciente se identifica por CI. Los usuarios internos usan usuario o email.
        if (!str_contains($identificador, '@')) {
            $paciente = $this->pacientes->findByCi($identificador);

            if ($paciente !== null) {
                if ($paciente['usuario_id'] === null) {
                    throw new HttpException(
                        403,
                        'Aún no activaste tu cuenta. Actívala desde /activar.',
                        [],
                        'ACCOUNT_NOT_ACTIVE'
                    );
                }

                $usuario = $this->usuarios->findById((int) $paciente['usuario_id']);
            }
        }

        $usuario ??= $this->usuarios->findInternalByIdentificador($identificador);

        if ($usuario === null) {
            throw new HttpException(401, 'CI/usuario o contraseña incorrectos.', [], 'INVALID_CREDENTIALS');
        }

        if ((bool) $usuario['bloqueado'] === true) {
            throw new HttpException(
                423,
                'Cuenta bloqueada por intentos fallidos. Contacte al administrador.',
                [],
                'ACCOUNT_LOCKED'
            );
        }

        if ((bool) $usuario['activo'] === false) {
            throw new HttpException(
                403,
                'Cuenta inactiva. Contacte al administrador.',
                [],
                'ACCOUNT_INACTIVE'
            );
        }

        if (!password_verify($password, (string) $usuario['password_hash'])) {
            $intentos = $this->usuarios->incrementarIntentos((int) $usuario['id']);

            if ($intentos >= 3) {
                throw new HttpException(
                    423,
                    'Cuenta bloqueada por intentos fallidos. Contacte al administrador.',
                    [],
                    'ACCOUNT_LOCKED'
                );
            }

            throw new HttpException(401, 'CI/usuario o contraseña incorrectos.', [], 'INVALID_CREDENTIALS');
        }

        $this->usuarios->registrarAcceso((int) $usuario['id']);

        if ($paciente === null && (string) $usuario['rol'] === 'paciente') {
            $paciente = $this->pacientes->findByUsuarioId((int) $usuario['id']);
        }

        return $this->construirSesion($usuario, $paciente);
    }

    /**
     * @param array<string, mixed> $usuario
     * @param array<string, mixed>|null $paciente
     * @return array<string, mixed>
     */
    private function construirSesion(array $usuario, ?array $paciente = null): array
    {
        $rol = (string) $usuario['rol'];
        $medicoId = null;
        $medicoIds = [];
        $pacienteId = null;
        $nombre = (string) ($usuario['username'] ?? $usuario['email'] ?? 'Usuario');

        if ($rol === 'medico') {
            $medico = $this->medicos->findByUsuarioId((int) $usuario['id']);

            if ($medico !== null) {
                $medicoId = (int) $medico['id'];
                $medicoIds = [$medicoId];
                $nombre = 'Dr. ' . $medico['nombres'] . ' ' . $medico['apellidos'];
            }
        } elseif ($rol === 'recepcionista_medico') {
            $medicoIds = $this->medicos->idsByRecepcionista((int) $usuario['id']);
            $medicoId = $medicoIds[0] ?? null;
        }

        if ($paciente !== null) {
            $pacienteId = (int) $paciente['id'];
            $nombre = $paciente['nombres'] . ' ' . $paciente['apellidos'];
        }

        return [
            'id' => (int) $usuario['id'],
            'rol' => $rol,
            'nombre' => $nombre,
            'medico_id' => $medicoId,
            'medico_ids' => $medicoIds,
            'paciente_id' => $pacienteId,
        ];
    }

    public function solicitarReset(string $email): void
    {
        $email = trim($email);

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $usuario = $this->usuarios->findByEmail($email);

        if ($usuario === null || (bool) $usuario['activo'] === false) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->usuarios->guardarTokenReset((int) $usuario['id'], $token, 30);

        $enlace = url('/reset?token=' . urlencode($token));
        $this->mail->sendReset((string) $usuario['email'], $enlace);
    }

    public function resetPassword(string $token, string $password, string $passwordConfirmation): void
    {
        $token = trim($token);

        if ($token === '') {
            throw new HttpException(422, 'El enlace expiró. Solicite uno nuevo.', [], 'TOKEN_INVALID');
        }

        $usuario = $this->usuarios->findByResetToken($token);

        if ($usuario === null) {
            throw new HttpException(422, 'El enlace expiró. Solicite uno nuevo.', [], 'TOKEN_INVALID');
        }

        $this->validarPassword($password, $passwordConfirmation);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->usuarios->actualizarPassword((int) $usuario['id'], $hash);
    }

    /**
     * Activa la cuenta de un paciente registrado en kiosco.
     *
     * @return array<string, mixed>
     */
    public function activarPaciente(
        string $ci,
        string $telefono,
        string $password,
        string $passwordConfirmation,
        string $email = ''
    ): array {
        $ci = trim($ci);
        $telefono = trim($telefono);
        $email = trim($email);

        if ($ci === '' || $telefono === '') {
            throw new HttpException(
                422,
                'CI y teléfono son obligatorios.',
                [],
                'VALIDATION_ERROR',
                ['ci' => 'Requerido', 'telefono' => 'Requerido']
            );
        }

        $this->validarPassword($password, $passwordConfirmation);

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new HttpException(
                422,
                'El correo no tiene un formato válido.',
                [],
                'VALIDATION_ERROR',
                ['email' => 'Formato inválido']
            );
        }

        $paciente = $this->pacientes->findPendienteActivacion($ci, $telefono);

        if ($paciente === null) {
            $existente = $this->pacientes->findByCi($ci);

            if ($existente !== null && $existente['usuario_id'] !== null) {
                throw new HttpException(
                    409,
                    'La cuenta ya está activa. Inicie sesión.',
                    [],
                    'ACCOUNT_ALREADY_ACTIVE'
                );
            }

            throw new HttpException(
                404,
                'No encontramos un registro con esos datos.',
                [],
                'ACTIVATION_NOT_FOUND'
            );
        }

        if ($email !== '' && $this->usuarios->findByEmail($email) !== null) {
            throw new HttpException(
                409,
                'El correo ya está registrado.',
                ['email' => 'Ya registrado'],
                'EMAIL_TAKEN'
            );
        }

        if ($this->usuarios->findByUsername($ci) !== null) {
            throw new HttpException(
                409,
                'La CI ya está asociada a una cuenta.',
                ['ci' => 'Ya registrada'],
                'IDENTIFIER_TAKEN'
            );
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $usuarioId = DB::transaction(function () use ($ci, $email, $hash, $paciente): int {
                $usuarioId = $this->usuarios->crearPaciente(
                    $ci,
                    $email === '' ? null : $email,
                    $hash
                );

                $this->pacientes->vincularUsuario((int) $paciente['id'], $usuarioId);

                return $usuarioId;
            });
        } catch (Throwable $exception) {
            logger($exception, ['activacion_ci' => $ci]);

            throw new HttpException(
                409,
                'No se pudo activar la cuenta. Intente nuevamente.',
                [],
                'ACTIVATION_CONFLICT'
            );
        }

        return [
            'usuario_id' => $usuarioId,
            'redirect' => '/login',
        ];
    }

    private function validarPassword(string $password, string $passwordConfirmation): void
    {
        $fields = [];

        if (strlen($password) < 8) {
            $fields['password'] = 'Mínimo 8 caracteres.';
        } elseif (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
            $fields['password'] = 'Debe incluir al menos una letra y un número.';
        }

        if ($password !== $passwordConfirmation) {
            $fields['password_confirmation'] = 'Las contraseñas no coinciden.';
        }

        if ($fields !== []) {
            throw new HttpException(
                422,
                'Revisa los datos enviados.',
                [],
                'VALIDATION_ERROR',
                $fields
            );
        }
    }
}
