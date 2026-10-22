<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\EspecialidadModel;
use App\Models\MedicoModel;
use App\Models\UsuarioModel;

final class MedicoApiController extends AdminApiController
{
    private const ESTADOS = ['activo', 'inactivo'];
    private const TIPOS_QR = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    public function __construct(
        private ?MedicoModel $medicos = null,
        private ?EspecialidadModel $especialidades = null,
        private ?UsuarioModel $usuarios = null
    ) {
        $this->medicos ??= new MedicoModel();
        $this->especialidades ??= new EspecialidadModel();
        $this->usuarios ??= new UsuarioModel();
    }

    public function index(Request $request): Response
    {
        $estado = $this->queryText($request, 'estado');

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        $especialidad = $request->query('especialidad');
        $especialidadId = null;

        if ($especialidad !== null && $especialidad !== '') {
            if (!is_scalar($especialidad) || !ctype_digit((string) $especialidad) || (int) $especialidad <= 0) {
                $this->validacion(['especialidad' => 'Indica un identificador válido.']);
            }

            $especialidadId = (int) $especialidad;
        }

        $paginacion = $this->pagination($request);
        $resultado = $this->medicos->listarAdmin([
            'q' => $this->queryText($request, 'q'),
            'especialidad' => $especialidadId,
            'estado' => $estado === '' ? null : $estado,
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
        $id = $this->idParam($params);
        $this->autorizarLecturaMedico($id);

        $medico = $this->medicos->findAdminById($id);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        return json_ok($this->conRecepcionistas($medico));
    }

    public function store(Request $request): Response
    {
        $datos = $this->validarCreacion($request->body());

        $id = $this->ejecutarEscritura(
            fn (): int => DB::transaction(function () use ($datos): int {
                $usuarioId = $datos['usuario_id'];

                if ($datos['crear_usuario']) {
                    $usuarioId = $this->usuarios->crearAdmin([
                        'username' => $datos['username'],
                        'email' => $datos['email'],
                        'rol' => 'medico',
                        'activo' => true,
                    ], password_hash($datos['password'], PASSWORD_BCRYPT));
                }

                $medicoId = $this->medicos->crearAdmin([
                    'usuario_id' => $usuarioId,
                    'registro_profesional' => $datos['registro_profesional'],
                    'nombres' => $datos['nombres'],
                    'apellidos' => $datos['apellidos'],
                    'telefono' => $datos['telefono'],
                    'email' => $datos['email'],
                    'duracion_cita_min' => $datos['duracion_cita_min'],
                ]);

                $this->medicos->reemplazarEspecialidades($medicoId, $datos['especialidades']);

                return $medicoId;
            }),
            'DUPLICATE_DOCTOR',
            'Ya existe un médico con ese registro profesional o usuario.'
        );

        $medico = $this->medicos->findAdminById($id);

        return json_ok(
            $medico === null ? null : $this->conRecepcionistas($medico),
            [],
            'Médico creado.',
            201
        );
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $usuario = $this->usuarioActual();
        $rol = (string) ($usuario['rol'] ?? '');
        $esMedico = $rol === 'medico';

        if ($esMedico && (int) ($usuario['medico_id'] ?? 0) !== $id) {
            throw new HttpException(403, 'No puedes editar los datos de otro médico.', [], 'FORBIDDEN');
        }

        $existente = $this->medicos->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->validarActualizacion($request->body(), $existente, $esMedico);
        $especialidades = $datos['especialidades'] ?? null;
        unset($datos['especialidades']);

        if ($datos !== [] || $especialidades !== null) {
            $this->ejecutarEscritura(
                fn () => DB::transaction(function () use ($id, $datos, $especialidades): void {
                    if ($datos !== []) {
                        $this->medicos->actualizarAdmin($id, $datos);
                    }

                    if ($especialidades !== null) {
                        $this->medicos->reemplazarEspecialidades($id, $especialidades);
                    }
                }),
                'DUPLICATE_DOCTOR',
                'Ya existe un médico con ese registro profesional.'
            );
        }

        $medico = $this->medicos->findAdminById($id);

        return json_ok(
            $medico === null ? null : $this->conRecepcionistas($medico),
            [],
            'Médico actualizado.'
        );
    }

    /** @param array<string, string> $params */
    public function estado(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->medicos->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $estado = $this->limpiarTexto($request->input('estado', ''));
        $activo = $this->booleano($request->input('activo'));

        if ($estado === '' && $activo !== null) {
            $estado = $activo ? 'activo' : 'inactivo';
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        if ($estado === 'inactivo' && $this->medicos->tieneCitasFuturas($id)) {
            throw new HttpException(
                409,
                'El médico tiene citas futuras activas. Reprograma o cancela antes de desactivarlo.',
                [],
                'FUTURE_APPOINTMENTS'
            );
        }

        $this->medicos->cambiarEstado($id, $estado);

        return json_ok(
            $this->conRecepcionistas($this->medicos->findAdminById($id) ?? $existente),
            [],
            $estado === 'activo' ? 'Médico activado.' : 'Médico desactivado.'
        );
    }

    /** @param array<string, string> $params */
    public function especialidades(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->medicos->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $campos = [];
        $especialidades = $this->parseEspecialidades($request->input('especialidades'), $campos);

        if ($campos !== []) {
            $this->validacion($campos);
        }

        $this->medicos->reemplazarEspecialidades($id, $especialidades);

        return json_ok(
            $this->conRecepcionistas($this->medicos->findAdminById($id) ?? $existente),
            [],
            'Especialidades actualizadas.'
        );
    }

    /** @param array<string, string> $params */
    public function qr(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $this->asegurarMedicoPropio($id);

        $medico = $this->medicos->findById($id);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $archivo = $request->file('qr') ?? $request->file('imagen') ?? $request->file('archivo');

        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->validacion(['qr' => 'Adjunta una imagen PNG o JPG.']);
        }

        $tamano = (int) ($archivo['size'] ?? 0);

        if ($tamano <= 0 || $tamano > 1_048_576) {
            $this->validacion(['qr' => 'La imagen no puede superar 1 MB.']);
        }

        $tmp = (string) ($archivo['tmp_name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $this->validacion(['qr' => 'No se pudo recibir la imagen.']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $tmp);

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!is_string($mime) || !isset(self::TIPOS_QR[$mime])) {
            $this->validacion(['qr' => 'El archivo debe ser una imagen PNG o JPG.']);
        }

        $dimensiones = @getimagesize($tmp);

        if ($dimensiones === false || (int) $dimensiones[0] <= 0 || (int) $dimensiones[1] <= 0) {
            $this->validacion(['qr' => 'La imagen no es válida.']);
        }

        $extension = self::TIPOS_QR[$mime];
        $directorio = public_path('uploads/qr');

        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new HttpException(500, 'No se pudo preparar la carpeta de imágenes.', [], 'STORAGE_ERROR');
        }

        $nombre = 'medico-' . $id . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new HttpException(500, 'No se pudo guardar la imagen.', [], 'STORAGE_ERROR');
        }

        $ruta = 'uploads/qr/' . $nombre;
        $this->medicos->actualizarQr($id, $ruta);

        $anterior = (string) ($medico['qr_imagen'] ?? '');

        if ($anterior !== '' && $anterior !== $ruta) {
            $rutaAnterior = public_path(ltrim($anterior, '/'));

            if (is_file($rutaAnterior)) {
                @unlink($rutaAnterior);
            }
        }

        $actualizado = $this->medicos->findAdminById($id);

        return json_ok(
            [
                'qr_url' => '/uploads/qr/' . $nombre,
                'medico' => $actualizado === null ? null : $this->conRecepcionistas($actualizado),
            ],
            [],
            'Código QR actualizado.'
        );
    }

    /** @param array<string, string> $params */
    public function recepcionista(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->medicos->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $usuarioId = $request->input('usuario_id');

        if (!is_scalar($usuarioId) || !ctype_digit((string) $usuarioId) || (int) $usuarioId <= 0) {
            $this->validacion(['usuario_id' => 'Indica un usuario válido.']);
        }

        $usuario = $this->usuarios->findById((int) $usuarioId);

        if ($usuario === null) {
            $this->validacion(['usuario_id' => 'El usuario no existe.']);
        }

        if ((string) $usuario['rol'] !== 'recepcionista_medico') {
            $this->validacion(['usuario_id' => 'El usuario debe tener el rol recepcionista_medico.']);
        }

        if (!(bool) $usuario['activo']) {
            $this->validacion(['usuario_id' => 'El usuario debe estar activo.']);
        }

        $this->medicos->asignarRecepcionista($id, (int) $usuarioId);

        return json_ok(
            $this->medicos->recepcionistasDe($id),
            [],
            'Recepcionista asignado.'
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function validarCreacion(array $body): array
    {
        $campos = [];
        $registro = $this->limpiarTexto($body['registro_profesional'] ?? '');
        $nombres = $this->limpiarTexto($body['nombres'] ?? '');
        $apellidos = $this->limpiarTexto($body['apellidos'] ?? '');
        $telefono = $this->telefonoOpcional($body['telefono'] ?? null);
        $email = $this->emailOpcional($body['email'] ?? null, $campos);
        $duracion = $this->enteroEnRango($body['duracion_cita_min'] ?? 30, 5, 120, 'duracion_cita_min', $campos);
        $especialidades = $this->parseEspecialidades($body['especialidades'] ?? null, $campos);
        $crearUsuario = $this->booleano($body['crear_usuario'] ?? false);
        $usuarioId = null;

        if ($this->longitud($registro) < 1 || $this->longitud($registro) > 50) {
            $campos['registro_profesional'] = 'El registro profesional es obligatorio (máximo 50 caracteres).';
        }

        if ($this->longitud($nombres) < 1 || $this->longitud($nombres) > 100) {
            $campos['nombres'] = 'Ingresa los nombres (máximo 100 caracteres).';
        }

        if ($this->longitud($apellidos) < 1 || $this->longitud($apellidos) > 100) {
            $campos['apellidos'] = 'Ingresa los apellidos (máximo 100 caracteres).';
        }

        if ($crearUsuario === null) {
            $campos['crear_usuario'] = 'Debe ser verdadero o falso.';
            $crearUsuario = false;
        }

        $username = null;
        $password = null;
        $passwordConfirmation = '';

        if ($crearUsuario) {
            $username = $this->textoOpcional($body['username'] ?? null);

            if ($username === null) {
                $campos['username'] = 'Ingresa un usuario para la cuenta del médico.';
            } elseif (preg_match('/^[A-Za-z0-9._-]{3,60}$/', $username) !== 1) {
                $campos['username'] = 'Usa entre 3 y 60 caracteres: letras, números, punto, guion o guion bajo.';
            }

            $password = is_scalar($body['password'] ?? null) ? (string) $body['password'] : '';

            if (strlen($password) < 8) {
                $campos['password'] = 'Mínimo 8 caracteres.';
            } elseif (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
                $campos['password'] = 'Debe incluir al menos una letra y un número.';
            }

            $passwordConfirmation = is_scalar($body['password_confirmation'] ?? null)
                ? (string) $body['password_confirmation']
                : '';

            if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
                $campos['password_confirmation'] = 'Las contraseñas no coinciden.';
            }
        } else {
            $usuarioIdRaw = $body['usuario_id'] ?? null;

            if ($usuarioIdRaw !== null && $usuarioIdRaw !== '') {
                $usuarioId = $this->enteroEnRango($usuarioIdRaw, 1, PHP_INT_MAX, 'usuario_id', $campos);
            }
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        if ($this->medicos->existeRegistro($registro)) {
            throw new HttpException(
                409,
                'Ya existe un médico con ese registro profesional.',
                [],
                'DUPLICATE_REGISTRO',
                ['registro_profesional' => 'Ya está en uso.']
            );
        }

        if ($usuarioId !== null) {
            $usuario = $this->usuarios->findById($usuarioId);

            if ($usuario === null) {
                $this->validacion(['usuario_id' => 'El usuario no existe.']);
            }

            if ((string) $usuario['rol'] !== 'medico') {
                $this->validacion(['usuario_id' => 'El usuario debe tener el rol médico.']);
            }

            if ($this->medicos->findByUsuarioId($usuarioId) !== null) {
                throw new HttpException(
                    409,
                    'Ese usuario ya está vinculado a un médico.',
                    [],
                    'USER_ALREADY_LINKED',
                    ['usuario_id' => 'Ya está vinculado.']
                );
            }
        }

        if ($crearUsuario) {
            if ($username !== null && $this->usuarios->existeUsername($username)) {
                throw new HttpException(
                    409,
                    'Ya existe una cuenta con ese usuario.',
                    [],
                    'USERNAME_TAKEN',
                    ['username' => 'Ya está en uso.']
                );
            }

            if ($email !== null && $this->usuarios->existeEmail($email)) {
                throw new HttpException(
                    409,
                    'Ya existe una cuenta con ese correo.',
                    [],
                    'EMAIL_TAKEN',
                    ['email' => 'Ya está registrado.']
                );
            }
        }

        return [
            'registro_profesional' => $registro,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'telefono' => $telefono,
            'email' => $email,
            'duracion_cita_min' => (int) $duracion,
            'especialidades' => $especialidades,
            'crear_usuario' => (bool) $crearUsuario,
            'usuario_id' => $usuarioId,
            'username' => $username,
            'password' => (string) $password,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $existente
     * @return array<string, mixed>
     */
    private function validarActualizacion(array $body, array $existente, bool $esMedico): array
    {
        $campos = [];
        $datos = [];
        $permitidos = $esMedico
            ? ['nombres', 'apellidos', 'telefono', 'email', 'duracion_cita_min']
            : ['registro_profesional', 'nombres', 'apellidos', 'telefono', 'email', 'duracion_cita_min', 'especialidades'];

        foreach (array_keys($body) as $clave) {
            if (str_starts_with((string) $clave, '_')) {
                continue;
            }

            if (!in_array($clave, $permitidos, true)) {
                throw new HttpException(
                    403,
                    'No tienes permisos para modificar ese campo.',
                    [],
                    'FORBIDDEN',
                    [$clave => 'Campo no editable.']
                );
            }
        }

        if (array_key_exists('registro_profesional', $body)) {
            $registro = $this->limpiarTexto($body['registro_profesional']);

            if ($this->longitud($registro) < 1 || $this->longitud($registro) > 50) {
                $campos['registro_profesional'] = 'El registro es obligatorio (máximo 50 caracteres).';
            } else {
                $datos['registro_profesional'] = $registro;
            }
        }

        foreach (['nombres' => 100, 'apellidos' => 100] as $campo => $maximo) {
            if (!array_key_exists($campo, $body)) {
                continue;
            }

            $valor = $this->limpiarTexto($body[$campo]);

            if ($this->longitud($valor) < 1 || $this->longitud($valor) > $maximo) {
                $campos[$campo] = 'Ingresa un valor de hasta ' . $maximo . ' caracteres.';
            } else {
                $datos[$campo] = $valor;
            }
        }

        if (array_key_exists('telefono', $body)) {
            $datos['telefono'] = $this->telefonoOpcional($body['telefono']);
        }

        if (array_key_exists('email', $body)) {
            $datos['email'] = $this->emailOpcional($body['email'], $campos);
        }

        if (array_key_exists('duracion_cita_min', $body)) {
            $duracion = $this->enteroEnRango($body['duracion_cita_min'], 5, 120, 'duracion_cita_min', $campos);

            if ($duracion !== null) {
                $datos['duracion_cita_min'] = $duracion;
            }
        }

        $especialidades = null;

        if (array_key_exists('especialidades', $body)) {
            $especialidades = $this->parseEspecialidades($body['especialidades'], $campos);
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        if (
            isset($datos['registro_profesional'])
            && $datos['registro_profesional'] !== $existente['registro_profesional']
            && $this->medicos->existeRegistro((string) $datos['registro_profesional'], (int) $existente['id'])
        ) {
            throw new HttpException(
                409,
                'Ya existe un médico con ese registro profesional.',
                [],
                'DUPLICATE_REGISTRO',
                ['registro_profesional' => 'Ya está en uso.']
            );
        }

        if ($especialidades !== null) {
            $datos['especialidades'] = $especialidades;
        }

        return $datos;
    }

    /**
     * @param array<string, string> $campos
     * @return array<int, int>
     */
    private function parseEspecialidades(mixed $raw, array &$campos): array
    {
        if (!is_array($raw) || $raw === []) {
            $campos['especialidades'] = 'Selecciona al menos una especialidad activa.';

            return [];
        }

        $ids = [];

        foreach ($raw as $valor) {
            if (!is_scalar($valor) || !ctype_digit((string) $valor) || (int) $valor <= 0) {
                $campos['especialidades'] = 'Las especialidades deben ser identificadores válidos.';

                return [];
            }

            $id = (int) $valor;

            if ($this->especialidades->findActivaById($id) === null) {
                $campos['especialidades'] = 'Selecciona solo especialidades activas.';

                return [];
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, string> $campos */
    private function emailOpcional(mixed $value, array &$campos): ?string
    {
        $email = $this->textoOpcional($value);

        if ($email === null) {
            return null;
        }

        $email = mb_strtolower($email);

        if (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $campos['email'] = 'El correo no tiene un formato válido.';

            return null;
        }

        return $email;
    }

    private function telefonoOpcional(mixed $value): ?string
    {
        $telefono = $this->textoOpcional($value);

        if ($telefono === null) {
            return null;
        }

        if (strlen($telefono) > 20 || preg_match('/^[0-9+().\-\s]{7,20}$/', $telefono) !== 1) {
            $this->validacion(['telefono' => 'Usa un teléfono válido de hasta 20 caracteres.']);
        }

        return $telefono;
    }

    /** @param array<string, string> $campos */
    private function enteroEnRango(
        mixed $value,
        int $minimo,
        int $maximo,
        string $campo,
        array &$campos
    ): ?int {
        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            $campos[$campo] = 'Usa un número entero válido.';

            return null;
        }

        $numero = (int) $value;

        if ($numero < $minimo || $numero > $maximo) {
            $campos[$campo] = 'Usa un valor entre ' . $minimo . ' y ' . $maximo . '.';

            return null;
        }

        return $numero;
    }

    private function longitud(string $valor): int
    {
        return function_exists('mb_strlen') ? mb_strlen($valor) : strlen($valor);
    }

    /** @param array<string, mixed> $medico */
    private function conRecepcionistas(array $medico): array
    {
        $medico['recepcionistas'] = $this->medicos->recepcionistasDe((int) $medico['id']);

        return $medico;
    }
}
