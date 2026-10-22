<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\UsuarioModel;

final class UsuarioApiController extends AdminApiController
{
    private const ROLES = [
        'super_admin',
        'admin',
        'recepcionista',
        'recepcionista_medico',
        'medico',
        'paciente',
    ];

    public function __construct(private ?UsuarioModel $usuarios = null)
    {
        $this->usuarios ??= new UsuarioModel();
    }

    public function index(Request $request): Response
    {
        $rol = $this->queryText($request, 'rol');
        $estado = $this->queryText($request, 'estado');

        if ($rol !== '' && !in_array($rol, self::ROLES, true)) {
            $this->validacion(['rol' => 'Selecciona un rol válido.']);
        }

        if ($estado !== '' && !in_array($estado, ['activo', 'inactivo'], true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        $paginacion = $this->pagination($request);
        $resultado = $this->usuarios->listarAdmin([
            'q' => $this->queryText($request, 'q'),
            'rol' => $rol,
            'estado' => $estado === '' ? null : $estado,
            'bloqueado' => $this->queryBool($request, 'bloqueado'),
        ], $paginacion['page'], $paginacion['per_page']);

        return $this->listaJson(
            $resultado['data'],
            $resultado['total'],
            $paginacion['page'],
            $paginacion['per_page']
        );
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $usuario = $this->usuarios->findAdminById($this->idParam($params));

        if ($usuario === null) {
            throw new HttpException(404, 'El usuario no existe.', [], 'NOT_FOUND');
        }

        return json_ok($usuario);
    }

    public function store(Request $request): Response
    {
        $datos = $this->validarCreacion($request->body());

        $id = $this->ejecutarEscritura(
            fn (): int => $this->usuarios->crearAdmin([
                'username' => $datos['username'],
                'email' => $datos['email'],
                'rol' => $datos['rol'],
                'activo' => $datos['activo'],
            ], password_hash($datos['password'], PASSWORD_BCRYPT)),
            'DUPLICATE_USER',
            'Ya existe un usuario o correo con esos datos.'
        );

        $usuario = $this->usuarios->findAdminById($id);

        return json_ok($usuario, [], 'Usuario creado.', 201);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->usuarios->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El usuario no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->validarActualizacion($existente, $request->body());

        if (array_key_exists('password', $datos) && $datos['password'] !== null) {
            $datos['password_hash'] = password_hash($datos['password'], PASSWORD_BCRYPT);
            unset($datos['password']);
        }

        $this->ejecutarEscritura(
            fn () => $this->usuarios->actualizarAdmin($id, $datos),
            'DUPLICATE_USER',
            'Ya existe un usuario o correo con esos datos.'
        );

        return json_ok($this->usuarios->findAdminById($id), [], 'Usuario actualizado.');
    }

    /** @param array<string, string> $params */
    public function estado(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->usuarios->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El usuario no existe.', [], 'NOT_FOUND');
        }

        $activo = $this->booleano($request->input('activo'));

        if ($activo === null && $request->has('estado')) {
            $estado = $this->limpiarTexto($request->input('estado'));

            if ($estado !== '' && !in_array($estado, ['activo', 'inactivo'], true)) {
                $this->validacion(['estado' => 'Usa activo o inactivo.']);
            }

            $activo = $estado === '' ? null : $estado === 'activo';
        }

        if ($activo === null) {
            $this->validacion(['activo' => 'Indica si el usuario debe estar activo.']);
        }

        $actual = auth();

        if (!$activo && $actual !== null && (int) ($actual['id'] ?? 0) === $id) {
            throw new HttpException(
                409,
                'No puedes desactivar tu propia cuenta.',
                [],
                'SELF_DEACTIVATION'
            );
        }

        if (
            !$activo
            && (string) $existente['rol'] === 'super_admin'
            && $this->usuarios->contarSuperAdminsActivos($id) === 0
        ) {
            throw new HttpException(
                409,
                'Debe quedar al menos un superadministrador activo.',
                [],
                'LAST_SUPER_ADMIN'
            );
        }

        $this->usuarios->actualizarEstado($id, $activo);

        return json_ok(
            $this->usuarios->findAdminById($id),
            [],
            $activo ? 'Usuario activado.' : 'Usuario desactivado.'
        );
    }

    /** @param array<string, string> $params */
    public function desbloquear(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->usuarios->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El usuario no existe.', [], 'NOT_FOUND');
        }

        $this->usuarios->desbloquear($id);

        return json_ok($this->usuarios->findAdminById($id), [], 'Usuario desbloqueado.');
    }

    /**
     * @param array<string, mixed> $body
     * @return array{username: ?string, email: ?string, password: string, rol: string, activo: bool}
     */
    private function validarCreacion(array $body): array
    {
        $campos = [];
        $username = $this->textoOpcional($body['username'] ?? null);
        $email = $this->textoOpcional($body['email'] ?? null);
        $email = $email === null ? null : mb_strtolower($email);
        $password = is_scalar($body['password'] ?? null) ? (string) $body['password'] : '';
        $passwordConfirmation = is_scalar($body['password_confirmation'] ?? null)
            ? (string) $body['password_confirmation']
            : '';
        $rol = $this->limpiarTexto($body['rol'] ?? '');
        $activo = $this->booleano($body['activo'] ?? true);

        $this->validarUsername($username, $campos);
        $this->validarEmail($email, $campos);
        $this->validarPassword($password, $passwordConfirmation, $campos);
        $this->validarRol($rol, $campos);

        if ($activo === null) {
            $campos['activo'] = 'Debe ser verdadero o falso.';
        }

        if ($username === null && $email === null) {
            $campos['username'] = 'Ingresa un usuario o un correo.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        if ($username !== null && $this->usuarios->existeUsername($username)) {
            throw new HttpException(
                409,
                'Ya existe un usuario con ese nombre de usuario.',
                [],
                'USERNAME_TAKEN',
                ['username' => 'Ya está en uso.']
            );
        }

        if ($email !== null && $this->usuarios->existeEmail($email)) {
            throw new HttpException(
                409,
                'Ya existe un usuario con ese correo.',
                [],
                'EMAIL_TAKEN',
                ['email' => 'Ya está registrado.']
            );
        }

        return [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'rol' => $rol,
            'activo' => (bool) $activo,
        ];
    }

    /**
     * @param array<string, mixed> $existente
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function validarActualizacion(array $existente, array $body): array
    {
        $campos = [];
        $datos = [];
        $id = (int) $existente['id'];
        $username = (string) ($existente['username'] ?? '');
        $email = (string) ($existente['email'] ?? '');

        if (array_key_exists('username', $body)) {
            $datos['username'] = $this->textoOpcional($body['username']);
            $username = (string) ($datos['username'] ?? '');
            $this->validarUsername($datos['username'], $campos);
        }

        if (array_key_exists('email', $body)) {
            $datos['email'] = $this->textoOpcional($body['email']);
            $datos['email'] = $datos['email'] === null ? null : mb_strtolower($datos['email']);
            $email = (string) ($datos['email'] ?? '');
            $this->validarEmail($datos['email'], $campos);
        }

        if (array_key_exists('rol', $body)) {
            $datos['rol'] = $this->limpiarTexto($body['rol']);
            $this->validarRol($datos['rol'], $campos);
        }

        if (array_key_exists('activo', $body)) {
            $datos['activo'] = $this->booleano($body['activo']);

            if ($datos['activo'] === null) {
                $campos['activo'] = 'Debe ser verdadero o falso.';
            }
        }

        if (
            array_key_exists('password', $body)
            && is_scalar($body['password'])
            && (string) $body['password'] !== ''
        ) {
            $password = (string) $body['password'];
            $confirmation = is_scalar($body['password_confirmation'] ?? null)
                ? (string) $body['password_confirmation']
                : '';
            $this->validarPassword($password, $confirmation, $campos);
            $datos['password'] = $password;
        }

        if ($username === '' && $email === '') {
            $campos['email'] = 'El usuario debe conservar un nombre de usuario o correo.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        $actual = auth();

        if ($actual !== null && (int) ($actual['id'] ?? 0) === $id) {
            if (array_key_exists('rol', $datos) && $datos['rol'] !== $existente['rol']) {
                throw new HttpException(
                    409,
                    'No puedes cambiar tu propio rol.',
                    [],
                    'SELF_ROLE_CHANGE'
                );
            }

            if (array_key_exists('activo', $datos) && $datos['activo'] === false) {
                throw new HttpException(
                    409,
                    'No puedes desactivar tu propia cuenta.',
                    [],
                    'SELF_DEACTIVATION'
                );
            }
        }

        if (
            (string) $existente['rol'] === 'super_admin'
            && (
                (array_key_exists('rol', $datos) && $datos['rol'] !== 'super_admin')
                || (array_key_exists('activo', $datos) && $datos['activo'] === false)
            )
            && $this->usuarios->contarSuperAdminsActivos($id) === 0
        ) {
            throw new HttpException(
                409,
                'Debe quedar al menos un superadministrador activo.',
                [],
                'LAST_SUPER_ADMIN'
            );
        }

        if (
            array_key_exists('username', $datos)
            && $datos['username'] !== null
            && $this->usuarios->existeUsername((string) $datos['username'], $id)
        ) {
            throw new HttpException(
                409,
                'Ya existe un usuario con ese nombre de usuario.',
                [],
                'USERNAME_TAKEN',
                ['username' => 'Ya está en uso.']
            );
        }

        if (
            array_key_exists('email', $datos)
            && $datos['email'] !== null
            && $this->usuarios->existeEmail((string) $datos['email'], $id)
        ) {
            throw new HttpException(
                409,
                'Ya existe un usuario con ese correo.',
                [],
                'EMAIL_TAKEN',
                ['email' => 'Ya está registrado.']
            );
        }

        return $datos;
    }

    /** @param array<string, string> $campos */
    private function validarUsername(?string $username, array &$campos): void
    {
        if ($username === null || $username === '') {
            return;
        }

        if (preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username) !== 1) {
            $campos['username'] = 'Usa entre 3 y 60 caracteres: letras, números, punto, guion o guion bajo.';
        }
    }

    /** @param array<string, string> $campos */
    private function validarEmail(?string $email, array &$campos): void
    {
        if ($email === null || $email === '') {
            return;
        }

        if (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $campos['email'] = 'El correo no tiene un formato válido.';
        }
    }

    /** @param array<string, string> $campos */
    private function validarPassword(string $password, string $confirmation, array &$campos): void
    {
        if (strlen($password) < 8) {
            $campos['password'] = 'Mínimo 8 caracteres.';
        } elseif (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
            $campos['password'] = 'Debe incluir al menos una letra y un número.';
        }

        if ($confirmation !== '' && $password !== $confirmation) {
            $campos['password_confirmation'] = 'Las contraseñas no coinciden.';
        }
    }

    /** @param array<string, string> $campos */
    private function validarRol(string $rol, array &$campos): void
    {
        if (!in_array($rol, self::ROLES, true)) {
            $campos['rol'] = 'Selecciona un rol válido.';
        }
    }
}
