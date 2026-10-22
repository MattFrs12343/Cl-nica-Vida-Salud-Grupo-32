<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\EspecialidadModel;

final class EspecialidadApiController extends AdminApiController
{
    private const SEXOS = ['ambos', 'femenino', 'masculino'];
    private const ESTADOS = ['activo', 'inactivo'];

    public function __construct(private ?EspecialidadModel $especialidades = null)
    {
        $this->especialidades ??= new EspecialidadModel();
    }

    public function index(Request $request): Response
    {
        $estado = $this->queryText($request, 'estado');

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        $paginacion = $this->pagination($request);
        $resultado = $this->especialidades->listarAdmin([
            'q' => $this->queryText($request, 'q'),
            'estado' => $estado === '' ? null : $estado,
            'destacada' => $this->queryBool($request, 'destacada'),
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
        $especialidad = $this->especialidades->findAdminById($this->idParam($params));

        if ($especialidad === null) {
            throw new HttpException(404, 'La especialidad no existe.', [], 'NOT_FOUND');
        }

        return json_ok($especialidad);
    }

    public function store(Request $request): Response
    {
        $datos = $this->validarDatos($request->body(), null);

        $id = $this->ejecutarEscritura(
            fn (): int => $this->especialidades->crear($datos),
            'DUPLICATE_NAME',
            'Ya existe una especialidad con ese nombre.'
        );

        return json_ok(
            $this->especialidades->findAdminById($id),
            [],
            'Especialidad creada.',
            201
        );
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->especialidades->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'La especialidad no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->validarDatos($request->body(), $existente);

        $this->ejecutarEscritura(
            fn () => $this->especialidades->actualizar($id, $datos),
            'DUPLICATE_NAME',
            'Ya existe una especialidad con ese nombre.'
        );

        return json_ok(
            $this->especialidades->findAdminById($id),
            [],
            'Especialidad actualizada.'
        );
    }

    /** @param array<string, string> $params */
    public function estado(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->especialidades->findAdminById($id);

        if ($existente === null) {
            throw new HttpException(404, 'La especialidad no existe.', [], 'NOT_FOUND');
        }

        $estado = $this->limpiarTexto($request->input('estado', ''));
        $activo = $this->booleano($request->input('activo'));

        if ($estado === '' && $activo !== null) {
            $estado = $activo ? 'activo' : 'inactivo';
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Usa activo o inactivo.']);
        }

        $this->especialidades->cambiarEstado($id, $estado);

        return json_ok(
            $this->especialidades->findAdminById($id),
            [],
            $estado === 'activo' ? 'Especialidad activada.' : 'Especialidad desactivada.'
        );
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed>|null $existente
     * @return array<string, mixed>
     */
    private function validarDatos(array $body, ?array $existente): array
    {
        $campos = [];

        $nombre = array_key_exists('nombre', $body) || $existente === null
            ? $this->limpiarTexto($body['nombre'] ?? '')
            : (string) $existente['nombre'];
        $descripcion = array_key_exists('descripcion', $body) || $existente === null
            ? $this->textoOpcional($body['descripcion'] ?? null)
            : ($existente['descripcion'] ?? null);
        $sexo = array_key_exists('sexo_permitido', $body) || $existente === null
            ? $this->limpiarTexto($body['sexo_permitido'] ?? 'ambos')
            : (string) $existente['sexo_permitido'];
        $edadMin = array_key_exists('edad_min', $body) || $existente === null
            ? $this->edadOpcional($body['edad_min'] ?? null, 'edad_min', $campos)
            : ($existente['edad_min'] ?? null);
        $edadMax = array_key_exists('edad_max', $body) || $existente === null
            ? $this->edadOpcional($body['edad_max'] ?? null, 'edad_max', $campos)
            : ($existente['edad_max'] ?? null);
        $destacada = array_key_exists('destacada', $body) || $existente === null
            ? $this->booleano($body['destacada'] ?? false)
            : (bool) $existente['destacada'];
        $icono = array_key_exists('icono', $body) || $existente === null
            ? $this->textoOpcional($body['icono'] ?? null)
            : ($existente['icono'] ?? null);
        $estado = array_key_exists('estado', $body) || $existente === null
            ? $this->limpiarTexto($body['estado'] ?? 'activo')
            : (string) $existente['estado'];

        $longitudNombre = function_exists('mb_strlen') ? mb_strlen($nombre) : strlen($nombre);

        if ($longitudNombre < 3 || $longitudNombre > 80) {
            $campos['nombre'] = 'El nombre debe tener entre 3 y 80 caracteres.';
        }

        if (!in_array($sexo, self::SEXOS, true)) {
            $campos['sexo_permitido'] = 'Selecciona ambos, femenino o masculino.';
        }

        if (!in_array($estado, self::ESTADOS, true)) {
            $campos['estado'] = 'Usa activo o inactivo.';
        }

        if ($destacada === null) {
            $campos['destacada'] = 'Debe ser verdadero o falso.';
        }

        if ($edadMin !== null && $edadMax !== null && (int) $edadMin > (int) $edadMax) {
            $campos['edad_max'] = 'La edad máxima debe ser mayor o igual a la mínima.';
        }

        if ($icono !== null && strlen($icono) > 50) {
            $campos['icono'] = 'El icono no puede superar 50 caracteres.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        if ($this->especialidades->existeNombre($nombre, $existente === null ? null : (int) $existente['id'])) {
            throw new HttpException(
                409,
                'Ya existe una especialidad con ese nombre.',
                [],
                'DUPLICATE_NAME',
                ['nombre' => 'Ya está en uso.']
            );
        }

        return [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'sexo_permitido' => $sexo,
            'edad_min' => $edadMin,
            'edad_max' => $edadMax,
            'destacada' => (bool) $destacada,
            'icono' => $icono,
            'estado' => $estado,
        ];
    }

    /** @param array<string, string> $campos */
    private function edadOpcional(mixed $value, string $campo, array &$campos): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value > 120) {
            $campos[$campo] = 'Usa una edad entre 0 y 120, o déjala vacía.';

            return null;
        }

        return (int) $value;
    }
}
