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

    public function dashboardMedico(Request $request): Response
    {
        $usuario = $this->asegurarRoles('medico');
        $medicoId = (int) ($usuario['medico_id'] ?? 0);

        if ($medicoId <= 0) {
            throw new HttpException(
                403,
                'La cuenta no tiene un médico asociado.',
                [],
                'FORBIDDEN'
            );
        }

        $medico = $this->comoPropietario(fn (): ?array => $this->medicos->findById($medicoId));

        if ($medico === null) {
            throw new HttpException(404, 'El médico solicitado no existe.', [], 'NOT_FOUND');
        }

        $fecha = $this->queryDate($request, 'fecha') ?? date('Y-m-d');
        $citas = $this->comoPropietario(
            fn (): array => $this->citas->agenda($medicoId, $fecha, $fecha)
        );
        $contadores = $this->comoPropietario(
            fn (): array => $this->citas->contadoresAgenda($medicoId, $fecha, $fecha)
        );

        $agenda = [];
        $siguiente = null;

        foreach ($citas as $cita) {
            $paciente = (string) ($cita['paciente']['nombre_completo'] ?? '');

            $agenda[] = [
                'id' => (int) $cita['id'],
                'codigo' => (string) $cita['codigo'],
                'hora' => (string) $cita['hora_inicio'],
                'hora_fin' => (string) $cita['hora_fin'],
                'paciente_id' => (int) $cita['paciente_id'],
                'paciente' => $paciente,
                'estado' => (string) $cita['estado'],
                'motivo' => $cita['motivo'],
            ];

            if ($siguiente === null && in_array((string) $cita['estado'], ['programada', 'confirmada'], true)) {
                $siguiente = [
                    'hora' => (string) $cita['hora_inicio'],
                    'paciente' => $paciente,
                    'motivo' => $cita['motivo'],
                ];
            }
        }

        return json_ok([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
            'citas_hoy' => $contadores['total'],
            'en_espera' => $contadores['confirmadas'],
            'atendidas' => $contadores['atendidas'],
            'proximas' => $contadores['programadas'] + $contadores['confirmadas'],
            'pendientes' => $contadores['programadas'],
            'inasistencias' => $contadores['inasistencias'],
            'siguiente_cita' => $siguiente,
            'agenda' => $agenda,
        ]);
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
