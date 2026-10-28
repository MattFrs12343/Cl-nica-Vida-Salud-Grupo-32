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
        $notificaciones = $this->comoPropietario(
            fn (): array => $this->reportes->notificacionesMedico($medicoId, $fecha)
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
            'reconsultas' => $notificaciones['reconsultas'],
            'notificaciones' => $notificaciones,
            'siguiente_cita' => $siguiente,
            'agenda' => $agenda,
        ]);
    }

    /**
     * Exporta cualquiera de los reportes a CSV.
     *
     * @param array<string, string> $params
     */
    public function csv(Request $request, array $params): Response
    {
        $this->asegurarRoles('admin', 'super_admin');
        $tipo = strtolower(trim((string) ($params['tipo'] ?? '')));

        if (!in_array($tipo, [
            'citas',
            'citas-por-medico',
            'citas-por-especialidad',
            'citas-por-estado',
            'pacientes',
            'asistencia',
            'agenda-diaria',
        ], true)) {
            throw new HttpException(404, 'El reporte solicitado no existe.', [], 'NOT_FOUND');
        }

        [$columnas, $filas] = $this->datosCsv($tipo, $request);
        $csv = $this->generarCsv($columnas, $filas);
        $nombre = 'reporte-' . $tipo . '-' . date('Ymd-His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $nombre . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     */
    private function datosCsv(string $tipo, Request $request): array
    {
        if ($tipo === 'citas') {
            $citas = $this->citasApi->exportar($request);

            return [
                ['codigo', 'fecha', 'hora_inicio', 'hora_fin', 'paciente', 'medico', 'especialidad', 'estado', 'origen', 'motivo'],
                array_map(static fn (array $cita): array => [
                    $cita['codigo'] ?? '',
                    $cita['fecha'] ?? '',
                    $cita['hora_inicio'] ?? '',
                    $cita['hora_fin'] ?? '',
                    $cita['paciente']['nombre_completo'] ?? '',
                    $cita['medico']['nombre_completo'] ?? '',
                    $cita['especialidad']['nombre'] ?? '',
                    $cita['estado'] ?? '',
                    $cita['origen'] ?? '',
                    $cita['motivo'] ?? '',
                ], $citas),
            ];
        }

        if ($tipo === 'citas-por-medico') {
            $datos = $this->datosDeRespuesta($this->citasPorMedico($request));

            return [
                ['medico_id', 'codigo', 'nombre_completo', 'total', 'programadas', 'confirmadas', 'atendidas', 'canceladas', 'inasistencias'],
                array_map(static fn (array $fila): array => [
                    $fila['medico_id'] ?? '',
                    $fila['codigo'] ?? '',
                    $fila['nombre_completo'] ?? '',
                    $fila['total'] ?? 0,
                    $fila['programadas'] ?? 0,
                    $fila['confirmadas'] ?? 0,
                    $fila['atendidas'] ?? 0,
                    $fila['canceladas'] ?? 0,
                    $fila['inasistencias'] ?? 0,
                ], $datos),
            ];
        }

        if ($tipo === 'citas-por-especialidad') {
            $datos = $this->datosDeRespuesta($this->citasPorEspecialidad($request));

            return [
                ['especialidad_id', 'nombre', 'total', 'programadas', 'confirmadas', 'atendidas', 'canceladas', 'inasistencias'],
                array_map(static fn (array $fila): array => [
                    $fila['especialidad_id'] ?? '',
                    $fila['nombre'] ?? '',
                    $fila['total'] ?? 0,
                    $fila['programadas'] ?? 0,
                    $fila['confirmadas'] ?? 0,
                    $fila['atendidas'] ?? 0,
                    $fila['canceladas'] ?? 0,
                    $fila['inasistencias'] ?? 0,
                ], $datos),
            ];
        }

        if ($tipo === 'citas-por-estado') {
            $datos = $this->datosDeRespuesta($this->citasPorEstado($request));
            $filas = [];

            foreach ($datos as $estado => $total) {
                $filas[] = [$estado, $total];
            }

            return [['estado', 'total'], $filas];
        }

        if ($tipo === 'pacientes') {
            $datos = $this->datosDeRespuesta($this->pacientes($request));

            return [
                ['id', 'codigo', 'ci', 'nombre_completo', 'telefono', 'email', 'estado', 'created_at'],
                array_map(static fn (array $fila): array => [
                    $fila['id'] ?? '',
                    $fila['codigo'] ?? '',
                    $fila['ci'] ?? '',
                    $fila['nombre_completo'] ?? '',
                    $fila['telefono'] ?? '',
                    $fila['email'] ?? '',
                    $fila['estado'] ?? '',
                    $fila['created_at'] ?? '',
                ], $datos),
            ];
        }

        if ($tipo === 'asistencia') {
            $datos = $this->datosDeRespuesta($this->asistencia($request));

            return [
                ['total', 'programadas', 'confirmadas', 'atendidas', 'canceladas', 'inasistencias', 'porcentaje_asistencia'],
                [[
                    $datos['total'] ?? 0,
                    $datos['programadas'] ?? 0,
                    $datos['confirmadas'] ?? 0,
                    $datos['atendidas'] ?? 0,
                    $datos['canceladas'] ?? 0,
                    $datos['inasistencias'] ?? 0,
                    $datos['porcentaje_asistencia'] ?? 0,
                ]],
            ];
        }

        $datos = $this->datosDeRespuesta($this->agendaDiaria($request));
        $citas = is_array($datos['citas'] ?? null) ? $datos['citas'] : [];
        $medico = (string) ($datos['medico']['nombre_completo'] ?? '');

        return [
            ['codigo', 'fecha', 'hora_inicio', 'hora_fin', 'medico', 'paciente', 'estado', 'motivo'],
            array_map(static fn (array $cita): array => [
                $cita['codigo'] ?? '',
                $cita['fecha'] ?? '',
                $cita['hora_inicio'] ?? '',
                $cita['hora_fin'] ?? '',
                $medico,
                $cita['paciente']['nombre_completo'] ?? '',
                $cita['estado'] ?? '',
                $cita['motivo'] ?? '',
            ], $citas),
        ];
    }

    /** @return array<int|string, mixed> */
    private function datosDeRespuesta(Response $response): array
    {
        $datos = json_decode($response->body(), true);

        if (!is_array($datos) || !is_array($datos['data'] ?? null)) {
            return [];
        }

        return $datos['data'];
    }

    /** @param array<int, string> $columnas @param array<int, array<int, mixed>> $filas */
    private function generarCsv(array $columnas, array $filas): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new HttpException(500, 'No se pudo generar el CSV.', [], 'CSV_ERROR');
        }

        fputcsv($handle, $columnas, ',', '"', '');

        foreach ($filas as $fila) {
            fputcsv($handle, array_map([$this, 'csvValor'], $fila), ',', '"', '');
        }

        rewind($handle);
        $contenido = stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF" . ($contenido === false ? '' : $contenido);
    }

    private function csvValor(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }

        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }

        $texto = (string) $valor;

        if ($texto !== '') {
            $primer = $texto[0];

            if (in_array($primer, ['=', '+', '@'], true)
                || ($primer === '-' && !is_numeric($texto))) {
                return "'" . $texto;
            }
        }

        return $texto;
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
