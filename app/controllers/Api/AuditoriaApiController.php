<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditoriaModel;

final class AuditoriaApiController extends AdminApiController
{
    private const ACCIONES = ['INSERT', 'UPDATE', 'DELETE'];

    public function __construct(private ?AuditoriaModel $auditoria = null)
    {
        $this->auditoria ??= new AuditoriaModel();
    }

    public function index(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');

        $tabla = strtolower($this->queryText($request, 'tabla'));

        if ($tabla === '') {
            $tabla = strtolower($this->queryText($request, 'tabla_afectada'));
        }

        if ($tabla !== '' && preg_match('/^[a-z_][a-z0-9_]{0,59}$/', $tabla) !== 1) {
            $this->validacion(['tabla' => 'Selecciona una tabla válida.']);
        }

        $accion = strtoupper($this->queryText($request, 'accion'));

        if ($accion !== '' && !in_array($accion, self::ACCIONES, true)) {
            $this->validacion(['accion' => 'Usa INSERT, UPDATE o DELETE.']);
        }

        $usuario = $this->queryText($request, 'usuario');
        $usuarioId = $this->queryId($request, 'usuario_id');

        if ($usuarioId === null && $usuario !== '' && ctype_digit($usuario)) {
            $usuarioId = (int) $usuario;
            $usuario = '';
        }

        $desde = $this->queryDate($request, 'desde');
        $hasta = $this->queryDate($request, 'hasta');
        $this->validarRango($desde, $hasta);

        $paginacion = $this->pagination($request);
        $resultado = $this->auditoria->listar([
            'tabla' => $tabla === '' ? null : $tabla,
            'accion' => $accion === '' ? null : $accion,
            'usuario_id' => $usuarioId,
            'usuario' => $usuario,
            'desde' => $desde,
            'hasta' => $hasta,
        ], $paginacion['page'], $paginacion['per_page']);

        return json_ok($resultado['data'], [
            'page' => $paginacion['page'],
            'per_page' => $paginacion['per_page'],
            'total' => $resultado['total'],
        ]);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $id = $this->idParam($params);
        $registro = $this->auditoria->findById($id);

        if ($registro === null) {
            throw new HttpException(404, 'El registro de auditoría no existe.', [], 'NOT_FOUND');
        }

        $registro['cambios'] = $this->cambios(
            $registro['datos_antes'] ?? null,
            $registro['datos_despues'] ?? null
        );

        return json_ok($registro);
    }

    /**
     * @param array<string, mixed>|null $antes
     * @param array<string, mixed>|null $despues
     * @return array<int, array{campo: string, antes: mixed, despues: mixed}>
     */
    private function cambios(?array $antes, ?array $despues): array
    {
        if ($antes === null || $despues === null) {
            return [];
        }

        $campos = array_unique(array_merge(array_keys($antes), array_keys($despues)));
        $cambios = [];

        foreach ($campos as $campo) {
            $valorAntes = $antes[$campo] ?? null;
            $valorDespues = $despues[$campo] ?? null;

            if ($valorAntes === $valorDespues) {
                continue;
            }

            $cambios[] = [
                'campo' => (string) $campo,
                'antes' => $valorAntes,
                'despues' => $valorDespues,
            ];
        }

        return $cambios;
    }
}
