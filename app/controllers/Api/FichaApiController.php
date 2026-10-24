<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\FichaModel;

/**
 * BAsqueda, detalle y reimpresiA3n de fichas (M-28).
 *
 * La emisiA3n de fichas sigue en el kiosco / FichaService. AquA- solo se
 * consulta el ticket ya emitido y se audita cada reimpresiA3n.
 */
final class FichaApiController extends AdminApiController
{
    public function __construct(private ?FichaModel $fichas = null)
    {
        $this->fichas ??= new FichaModel();
    }

    public function index(Request $request): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );

        $fecha = $this->queryDate($request, 'fecha');
        $paginacion = $this->pagination($request);
        $medicoIds = $this->medicoIdsDe($usuario);

        $resultado = $this->comoPropietario(
            fn (): array => $this->fichas->listar([
                'q' => $this->queryText($request, 'q'),
                'fecha' => $fecha,
                'medico_ids' => $medicoIds,
            ], $paginacion['page'], $paginacion['per_page'])
        );

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
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );
        $codigo = $this->codigoParam($params);
        $ficha = $this->comoPropietario(fn (): ?array => $this->fichas->porCodigo($codigo));

        if ($ficha === null) {
            throw new HttpException(404, 'Ficha no encontrada.', [], 'FICHA_NOT_FOUND');
        }

        $this->autorizarFicha($ficha, $usuario);

        return json_ok($ficha);
    }

    /** @param array<string, string> $params */
    public function reimprimir(Request $request, array $params): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );
        $codigo = $this->codigoParam($params);
        $ficha = $this->comoPropietario(fn (): ?array => $this->fichas->porCodigo($codigo));

        if ($ficha === null) {
            throw new HttpException(404, 'Ficha no encontrada.', [], 'FICHA_NOT_FOUND');
        }

        $this->autorizarFicha($ficha, $usuario);

        $actualizada = $this->comoPropietario(
            fn (): ?array => $this->fichas->registrarReimpresion((int) $ficha['id'])
        );

        if ($actualizada === null) {
            throw new HttpException(404, 'Ficha no encontrada.', [], 'FICHA_NOT_FOUND');
        }

        return json_ok(
            ['ficha' => $actualizada],
            [],
            'ReimpresiA3n registrada.'
        );
    }

    /** @param array<string, string> $params */
    private function codigoParam(array $params): string
    {
        $codigo = strtoupper(trim((string) ($params['codigo'] ?? '')));

        if ($codigo === '' || strlen($codigo) > 20) {
            throw new HttpException(404, 'Ficha no encontrada.', [], 'FICHA_NOT_FOUND');
        }

        return $codigo;
    }

    /**
     * @param array<string, mixed> $ficha
     * @param array<string, mixed> $usuario
     */
    private function autorizarFicha(array $ficha, array $usuario): void
    {
        $medicoIds = $this->medicoIdsDe($usuario);

        if ($medicoIds === null) {
            return;
        }

        $medicoId = (int) ($ficha['medico']['id'] ?? 0);

        if (in_array($medicoId, $medicoIds, true)) {
            return;
        }

        throw new HttpException(
            403,
            'No tienes permisos para consultar esta ficha.',
            [],
            'FORBIDDEN'
        );
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<int, int>|null Null cuando el rol puede ver todos los mAcdicos.
     */
    private function medicoIdsDe(array $usuario): ?array
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return null;
        }

        if ($rol === 'medico') {
            $medicoId = (int) ($usuario['medico_id'] ?? 0);

            return $medicoId > 0 ? [$medicoId] : [];
        }

        if ($rol === 'recepcionista_medico') {
            return array_values(array_unique(array_filter(
                array_map('intval', (array) ($usuario['medico_ids'] ?? [])),
                static fn (int $id): bool => $id > 0
            )));
        }

        throw new HttpException(403, 'No tienes permisos para esta secciA3n.', [], 'FORBIDDEN');
    }
}
