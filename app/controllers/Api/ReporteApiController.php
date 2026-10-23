<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CitaModel;
use App\Models\MedicoModel;
use App\Models\ReporteModel;

final class ReporteApiController extends AdminApiController
{
    private const ESTADOS = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];

    private CitaModel $citas;
    private CitaApiController $citasApi;

    public function __construct(
        private ?ReporteModel $reportes = null,
        ?CitaModel $citas = null,
        private ?MedicoModel $medicos = null
    ) {
        $this->reportes ??= new ReporteModel();
        $this->citas = $citas ?? new CitaModel();
        $this->medicos ??= new MedicoModel();
        $this->citasApi = new CitaApiController($this->citas);
    }

    public function citas(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');

        return $this->citasApi->citasDelMes($request);
    }

    public function citasPorMedico(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $rango = $this->rango($request);
        $data = $this->reportes->citasPorMedico($rango['desde'], $rango['hasta']);

        return json_ok($data, [
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'total_citas' => array_sum(array_column($data, 'total')),
        ]);
    }

    public function citasPorEspecialidad(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $rango = $this->rango($request);
        $data = $this->reportes->citasPorEspecialidad($rango['desde'], $rango['hasta']);

        return json_ok($data, [
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'total_citas' => array_sum(array_column($data, 'total')),
        ]);
    }

    public function citasPorEstado(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $rango = $this->rango($request);
        $data = $this->reportes->citasPorEstado($rango['desde'], $rango['hasta']);

        return json_ok($data, [
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'total_citas' => array_sum($data),
        ]);
    }

    public function pacientes(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $rango = $this->rango($request);
        $resultado = $this->reportes->pacientesRegistrados($rango['desde'], $rango['hasta']);

        return json_ok($resultado['data'], [
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
            'total' => $resultado['total'],
        ]);
    }

    public function asistencia(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $rango = $this->rango($request);
        $data = $this->reportes->asistencia($rango['desde'], $rango['hasta']);
        $base = $data['atendidas'] + $data['inasistencias'];
        $data['porcentaje_asistencia'] = $base > 0
            ? round(($data['atendidas'] / $base) * 100, 2)
            : 0.0;

        return json_ok($data, [
            'desde' => $rango['desde'],
            'hasta' => $rango['hasta'],
        ]);
    }

    public function agendaDiaria(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $medicoId = $this->queryId($request, 'medico');

        if ($medicoId === null) {
            $this->validacion(['medico' => 'Selecciona un médico.']);
        }

        $fecha = $this->queryDate($request, 'fecha') ?? date('Y-m-d');
        $estado = strtolower($this->queryText($request, 'estado'));

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Selecciona un estado válido.']);
        }

        $medico = $this->medicos->findById($medicoId);

        if ($medico === null) {
            throw new HttpException(404, 'El médico solicitado no existe.', [], 'NOT_FOUND');
        }

        $citas = $this->citas->agenda($medicoId, $fecha, $fecha, $estado === '' ? null : $estado);
        $contadores = $this->citas->contadoresAgenda($medicoId, $fecha, $fecha);

        return json_ok([
            'medico' => [
                'id' => (int) $medico['id'],
                'codigo' => (string) $medico['codigo'],
                'nombres' => (string) $medico['nombres'],
                'apellidos' => (string) $medico['apellidos'],
                'nombre_completo' => 'Dr. ' . trim((string) $medico['nombres'] . ' ' . (string) $medico['apellidos']),
            ],
            'fecha' => $fecha,
            'citas' => $citas,
            'contadores' => $contadores,
        ]);
    }

    public function dashboardAdmin(Request $request): Response
    {
        $this->asegurarRoles('admin', 'super_admin');

        return json_ok($this->reportes->dashboardAdmin(date('Y-m-d')));
    }

    /** @return array{desde: string, hasta: string} */
    private function rango(Request $request): array
    {
        $desde = $this->queryDate($request, 'desde');
        $hasta = $this->queryDate($request, 'hasta');

        if ($desde === null && $hasta === null) {
            $rango = $this->rangoMesActual();

            return $rango;
        }

        if (($desde === null) !== ($hasta === null)) {
            $this->validacion(['desde' => 'Envía desde y hasta juntos.']);
        }

        $this->validarRango($desde, $hasta);

        return ['desde' => (string) $desde, 'hasta' => (string) $hasta];
    }
}
