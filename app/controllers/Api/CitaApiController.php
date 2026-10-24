<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CitaModel;
use App\Services\CitaService;

final class CitaApiController extends AdminApiController
{
    private const ESTADOS = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];
    private const ORIGENES = ['web', 'kiosco', 'recepcion'];

    public function __construct(
        private ?CitaModel $citas = null,
        private ?CitaService $servicio = null
    ) {
        $this->citas ??= new CitaModel();
        $this->servicio ??= new CitaService();
    }

    public function index(Request $request): Response
    {
        return $this->listar($request, false);
    }

    public function store(Request $request): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista', 'recepcionista_medico');

        $cita = $this->comoPropietario(
            fn (): array => $this->servicio->crear($request->body(), $this->usuarioActual())
        );

        return json_ok($cita, [], 'Cita registrada.', 201);
    }

    /** @param array<string, string> $params */
    public function reprogramar(Request $request, array $params): Response
    {
        $this->asegurarRoles('super_admin', 'admin', 'recepcionista', 'recepcionista_medico');
        $id = $this->idParam($params);

        $cita = $this->comoPropietario(
            fn (): array => $this->servicio->reprogramar($id, $request->body(), $this->usuarioActual())
        );

        return json_ok($cita, [], 'Cita reprogramada.');
    }

    /** @param array<string, string> $params */
    public function cancelar(Request $request, array $params): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'paciente'
        );
        $id = $this->idParam($params);

        $cita = $this->comoPropietario(
            fn (): array => $this->servicio->cancelar($id, $request->body(), $this->usuarioActual())
        );

        return json_ok($cita, [], 'Cita cancelada.');
    }

    /** @param array<string, string> $params */
    public function atender(Request $request, array $params): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );
        $id = $this->idParam($params);

        $cita = $this->comoPropietario(
            fn (): array => $this->servicio->atender($id, $this->usuarioActual())
        );

        return json_ok($cita, [], 'Cita marcada como atendida.');
    }

    /** @param array<string, string> $params */
    public function inasistencia(Request $request, array $params): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );
        $id = $this->idParam($params);

        $cita = $this->comoPropietario(
            fn (): array => $this->servicio->inasistencia($id, $this->usuarioActual())
        );

        return json_ok($cita, [], 'Cita marcada como inasistencia.');
    }

    /** @param array<string, string> $params */
    public function reconsulta(Request $request, array $params): Response
    {
        $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );
        $id = $this->idParam($params);

        $resultado = $this->comoPropietario(
            fn (): array => $this->servicio->reconsulta($id, $request->body(), $this->usuarioActual())
        );

        return json_ok($resultado, [], 'Reconsulta registrada.', 201);
    }

    public function citasDelMes(Request $request): Response
    {
        return $this->listar($request, true);
    }

    private function listar(Request $request, bool $rangoMesPorDefecto): Response
    {
        $usuario = $this->usuarioActual();
        $filtros = $this->filtros($request);

        if ($rangoMesPorDefecto
            && $filtros['fecha'] === null
            && $filtros['desde'] === null
            && $filtros['hasta'] === null) {
            $rango = $this->rangoMesActual();
            $filtros['desde'] = $rango['desde'];
            $filtros['hasta'] = $rango['hasta'];
        }

        $filtros = array_merge($filtros, $this->alcance($usuario, $filtros['medico_id'] ?? null));
        $paginacion = $this->pagination($request);

        $resultado = $this->comoPropietario(
            fn (): array => $this->citas->listarAdmin(
                $filtros,
                $paginacion['page'],
                $paginacion['per_page']
            )
        );

        return json_ok($resultado['data'], [
            'page' => $paginacion['page'],
            'per_page' => $paginacion['per_page'],
            'total' => $resultado['total'],
        ]);
    }

    /** @param array<string, string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $cita = $this->comoPropietario(fn (): ?array => $this->citas->findAdminById($id));

        if ($cita === null) {
            throw new HttpException(404, 'La cita solicitada no existe.', [], 'NOT_FOUND');
        }

        $this->autorizarCita($cita);

        return json_ok($cita);
    }

    /** @param array<string, string> $params */
    public function historial(Request $request, array $params): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $id = $this->idParam($params);
        $cita = $this->citas->findAdminById($id);

        if ($cita === null) {
            throw new HttpException(404, 'La cita solicitada no existe.', [], 'NOT_FOUND');
        }

        return json_ok($this->citas->historialEstados($id), ['cita_id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $fecha = $this->queryDate($request, 'fecha');
        $desde = $this->queryDate($request, 'desde');
        $hasta = $this->queryDate($request, 'hasta');

        if ($fecha !== null && ($desde !== null || $hasta !== null)) {
            $this->validacion(['fecha' => 'Usa una fecha exacta o un rango, no ambos.']);
        }

        $this->validarRango($desde, $hasta);

        $estado = strtolower($this->queryText($request, 'estado'));
        $origen = strtolower($this->queryText($request, 'origen'));

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Selecciona un estado válido.']);
        }

        if ($origen !== '' && !in_array($origen, self::ORIGENES, true)) {
            $this->validacion(['origen' => 'Selecciona un origen válido.']);
        }

        $sort = strtolower($this->queryText($request, 'sort'));
        $sort = $sort === '' ? 'fecha' : $sort;

        if (!in_array($sort, ['fecha', 'hora', 'codigo', 'estado', 'created_at'], true)) {
            $this->validacion(['sort' => 'Selecciona un orden válido.']);
        }

        $direction = strtolower($this->queryText($request, 'direction'));
        $direction = $direction === '' ? 'desc' : $direction;

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $this->validacion(['direction' => 'Usa asc o desc.']);
        }

        return [
            'fecha' => $fecha,
            'desde' => $desde,
            'hasta' => $hasta,
            'medico_id' => $this->queryId($request, 'medico') ?? $this->queryId($request, 'medico_id'),
            'especialidad_id' => $this->queryId($request, 'especialidad')
                ?? $this->queryId($request, 'especialidad_id'),
            'paciente_id' => $this->queryId($request, 'paciente') ?? $this->queryId($request, 'paciente_id'),
            'estado' => $estado === '' ? null : $estado,
            'origen' => $origen === '' ? null : $origen,
            'q' => $this->queryText($request, 'q'),
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function alcance(array $usuario, ?int $medicoSolicitado): array
    {
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return [];
        }

        if ($rol === 'medico') {
            $medicoId = (int) ($usuario['medico_id'] ?? 0);

            if ($medicoId <= 0 || ($medicoSolicitado !== null && $medicoSolicitado !== $medicoId)) {
                throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
            }

            return ['medico_ids' => [$medicoId]];
        }

        if ($rol === 'recepcionista_medico') {
            $medicoIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($usuario['medico_ids'] ?? [])),
                static fn (int $id): bool => $id > 0
            )));

            if ($medicoIds === []
                || ($medicoSolicitado !== null && !in_array($medicoSolicitado, $medicoIds, true))) {
                throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
            }

            return ['medico_ids' => $medicoIds];
        }

        throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
    }

    /** @param array<string, mixed> $cita */
    private function autorizarCita(array $cita): void
    {
        $usuario = $this->usuarioActual();
        $rol = (string) ($usuario['rol'] ?? '');
        $medicoId = (int) ($cita['medico_id'] ?? 0);

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        if ($rol === 'medico' && $medicoId === (int) ($usuario['medico_id'] ?? 0)) {
            return;
        }

        if ($rol === 'recepcionista_medico'
            && in_array($medicoId, array_map('intval', (array) ($usuario['medico_ids'] ?? [])), true)) {
            return;
        }

        throw new HttpException(403, 'No tienes permisos para esta sección.', [], 'FORBIDDEN');
    }
}
