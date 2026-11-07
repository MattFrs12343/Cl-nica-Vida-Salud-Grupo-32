<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ExamenModel;

/**
 * Catálogo de exámenes de laboratorio/imagen.
 *
 * Recepción y administración fijan nombre, tipo y precio. El médico solo lee
 * el catálogo activo para armar sus órdenes.
 */
final class ExamenApiController extends AdminApiController
{
    private const TIPOS = ['laboratorio', 'imagen', 'otro'];

    public function __construct(private ?ExamenModel $examenes = null)
    {
        $this->examenes ??= new ExamenModel();
    }

    public function index(Request $request): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );

        $tipo = strtolower($this->queryText($request, 'tipo'));

        if ($tipo !== '' && !in_array($tipo, self::TIPOS, true)) {
            $this->validacion(['tipo' => 'Usa laboratorio, imagen u otro.']);
        }

        $activo = $this->queryBool($request, 'activo');
        $soloActivos = $this->queryBool($request, 'solo_activos');

        if ($soloActivos === true) {
            $data = $this->comoPropietario(
                fn (): array => $this->examenes->listarActivos($tipo === '' ? null : $tipo)
            );

            return $this->listaJson($data, count($data), 1, max(1, count($data)));
        }

        $paginacion = $this->pagination($request);

        $resultado = $this->comoPropietario(
            fn (): array => $this->examenes->listar([
                'tipo' => $tipo === '' ? null : $tipo,
                'activo' => $activo,
                'q' => $this->queryText($request, 'q'),
            ], $paginacion['page'], $paginacion['per_page'])
        );

        return $this->listaJson(
            $resultado['data'],
            $resultado['total'],
            $paginacion['page'],
            $paginacion['per_page']
        );
    }

    public function store(Request $request): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista');

        $datos = $this->datos($request);

        $id = $this->comoPropietario(
            fn (): int => $this->examenes->crear($datos)
        );

        $examen = $this->comoPropietario(fn (): ?array => $this->examenes->porId($id));

        return json_ok($examen, [], 'Examen registrado.', 201);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );
        $id = $this->idParam($params);
        $examen = $this->comoPropietario(fn (): ?array => $this->examenes->porId($id));

        if ($examen === null) {
            throw new HttpException(404, 'El examen no existe.', [], 'NOT_FOUND');
        }

        return json_ok($examen);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista');
        $id = $this->idParam($params);
        $examen = $this->comoPropietario(fn (): ?array => $this->examenes->porId($id));

        if ($examen === null) {
            throw new HttpException(404, 'El examen no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->datos($request, $examen);

        $this->comoPropietario(fn (): mixed => $this->examenes->actualizar($id, $datos));

        return json_ok($this->comoPropietario(fn (): ?array => $this->examenes->porId($id)), [], 'Examen actualizado.');
    }

    /** @param array<string, string> $params */
    public function estado(Request $request, array $params): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista');
        $id = $this->idParam($params);
        $examen = $this->comoPropietario(fn (): ?array => $this->examenes->porId($id));

        if ($examen === null) {
            throw new HttpException(404, 'El examen no existe.', [], 'NOT_FOUND');
        }

        $activo = $this->booleano($request->input('activo'));

        if ($activo === null) {
            $this->validacion(['activo' => 'Indica si el examen está activo.']);
        }

        $this->comoPropietario(fn (): mixed => $this->examenes->cambiarEstado($id, $activo));

        return json_ok(
            $this->comoPropietario(fn (): ?array => $this->examenes->porId($id)),
            [],
            $activo ? 'Examen activado.' : 'Examen desactivado.'
        );
    }

    /**
     * @param array<string, mixed>|null $actual
     * @return array<string, mixed>
     */
    private function datos(Request $request, ?array $actual = null): array
    {
        $nombre = $this->limpiarTexto($request->input('nombre', $actual['nombre'] ?? ''));

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $this->validacion(['nombre' => 'Ingresa un nombre de hasta 150 caracteres.']);
        }

        $tipo = strtolower($this->limpiarTexto($request->input('tipo', $actual['tipo'] ?? 'laboratorio')));

        if (!in_array($tipo, self::TIPOS, true)) {
            $this->validacion(['tipo' => 'Usa laboratorio, imagen u otro.']);
        }

        $precio = $request->input('precio', $actual['precio'] ?? null);

        if (!is_numeric($precio) || (float) $precio < 0) {
            $this->validacion(['precio' => 'Ingresa un precio válido (0 o más).']);
        }

        $codigo = $this->textoOpcional($request->input('codigo', $actual['codigo'] ?? null));

        if ($codigo !== null && mb_strlen($codigo) > 20) {
            $this->validacion(['codigo' => 'El código no puede superar 20 caracteres.']);
        }

        $activo = $this->booleano($request->input('activo', $actual['activo'] ?? true));

        return [
            'codigo' => $codigo !== null ? strtoupper($codigo) : null,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'precio' => round((float) $precio, 2),
            'activo' => $activo ?? true,
        ];
    }
}
