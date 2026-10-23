<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CitaModel;
use App\Models\MedicoModel;
use App\Models\PacienteModel;

final class AgendaApiController extends AdminApiController
{
    private const ESTADOS = ['programada', 'confirmada', 'atendida', 'cancelada', 'inasistencia'];

    public function __construct(
        private ?CitaModel $citas = null,
        private ?MedicoModel $medicos = null,
        private ?PacienteModel $pacientes = null
    ) {
        $this->citas ??= new CitaModel();
        $this->medicos ??= new MedicoModel();
        $this->pacientes ??= new PacienteModel();
    }

    /** @param array<string, string> $params */
    public function medico(Request $request, array $params): Response
    {
        $medicoId = $this->idParam($params, 'medico');
        $this->autorizarLecturaMedico($medicoId);

        $medico = $this->comoPropietario(fn (): ?array => $this->medicos->findById($medicoId));

        if ($medico === null) {
            throw new HttpException(404, 'El médico solicitado no existe.', [], 'NOT_FOUND');
        }

        $fecha = $this->queryDate($request, 'fecha');
        $desde = $this->queryDate($request, 'desde');
        $hasta = $this->queryDate($request, 'hasta');

        if ($fecha !== null && ($desde !== null || $hasta !== null)) {
            $this->validacion(['fecha' => 'Usa una fecha exacta o un rango, no ambos.']);
        }

        if (($desde === null) !== ($hasta === null)) {
            $this->validacion(['desde' => 'Envía desde y hasta juntos.']);
        }

        $this->validarRango($desde, $hasta);

        if ($fecha === null && $desde === null) {
            $fecha = date('Y-m-d');
        }

        $inicio = $fecha ?? (string) $desde;
        $fin = $fecha ?? (string) $hasta;
        $estado = strtolower($this->queryText($request, 'estado'));

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Selecciona un estado válido.']);
        }

        $citas = $this->comoPropietario(
            fn (): array => $this->citas->agenda($medicoId, $inicio, $fin, $estado === '' ? null : $estado)
        );
        $contadores = $this->comoPropietario(
            fn (): array => $this->citas->contadoresAgenda($medicoId, $inicio, $fin)
        );

        return json_ok([
            'medico' => $this->medicoBasico($medico),
            'fecha' => $fecha,
            'desde' => $desde,
            'hasta' => $hasta,
            'citas' => $citas,
            'contadores' => $contadores,
        ]);
    }

    /** @param array<string, string> $params */
    public function dia(Request $request, array $params): Response
    {
        $medicoId = $this->idParam($params, 'medico');
        $this->autorizarLecturaMedico($medicoId);

        $medico = $this->comoPropietario(fn (): ?array => $this->medicos->findById($medicoId));

        if ($medico === null) {
            throw new HttpException(404, 'El médico solicitado no existe.', [], 'NOT_FOUND');
        }

        $fecha = $this->queryDate($request, 'fecha') ?? date('Y-m-d');
        $contadores = $this->comoPropietario(
            fn (): array => $this->citas->contadoresAgenda($medicoId, $fecha, $fecha)
        );

        return json_ok([
            'medico' => $this->medicoBasico($medico),
            'fecha' => $fecha,
            'contadores' => $contadores,
        ]);
    }

    /** @param array<string, string> $params */
    public function historialPaciente(Request $request, array $params): Response
    {
        $pacienteId = $this->idParam($params, 'paciente');
        $this->autorizarHistorialPaciente($pacienteId);

        $paciente = $this->comoPropietario(fn (): ?array => $this->pacientes->findById($pacienteId));

        if ($paciente === null) {
            throw new HttpException(404, 'El paciente solicitado no existe.', [], 'NOT_FOUND');
        }

        $desde = $this->queryDate($request, 'desde');
        $hasta = $this->queryDate($request, 'hasta');
        $this->validarRango($desde, $hasta);

        $estado = strtolower($this->queryText($request, 'estado'));

        if ($estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            $this->validacion(['estado' => 'Selecciona un estado válido.']);
        }

        $citas = $this->comoPropietario(
            fn (): array => $this->citas->historialPaciente(
                $pacienteId,
                $desde,
                $hasta,
                $estado === '' ? null : $estado
            )
        );

        return json_ok([
            'paciente' => $this->pacienteBasico($paciente),
            'citas' => $citas,
        ]);
    }

    private function autorizarHistorialPaciente(int $pacienteId): void
    {
        $usuario = $this->usuarioActual();
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        if ($rol === 'paciente' && (int) ($usuario['paciente_id'] ?? 0) === $pacienteId) {
            return;
        }

        $medicoIds = [];

        if ($rol === 'medico') {
            $medicoIds = [(int) ($usuario['medico_id'] ?? 0)];
        } elseif ($rol === 'recepcionista_medico') {
            $medicoIds = array_map('intval', (array) ($usuario['medico_ids'] ?? []));
        }

        $medicoIds = array_values(array_filter($medicoIds, static fn (int $id): bool => $id > 0));

        if ($medicoIds !== []
            && $this->comoPropietario(
                fn (): bool => $this->citas->tieneCitaConPaciente($pacienteId, $medicoIds)
            )
        ) {
            return;
        }

        throw new HttpException(403, 'No tienes permisos para ver este historial.', [], 'FORBIDDEN');
    }

    /**
     * @param array<string, mixed> $medico
     * @return array<string, mixed>
     */
    private function medicoBasico(array $medico): array
    {
        $nombre = trim((string) $medico['nombres'] . ' ' . (string) $medico['apellidos']);

        return [
            'id' => (int) $medico['id'],
            'codigo' => (string) $medico['codigo'],
            'nombres' => (string) $medico['nombres'],
            'apellidos' => (string) $medico['apellidos'],
            'nombre_completo' => $nombre !== '' ? 'Dr. ' . $nombre : '',
            'duracion_cita_min' => (int) ($medico['duracion_cita_min'] ?? 0),
            'estado' => (string) ($medico['estado'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $paciente
     * @return array<string, mixed>
     */
    private function pacienteBasico(array $paciente): array
    {
        return [
            'id' => (int) $paciente['id'],
            'codigo' => (string) $paciente['codigo'],
            'ci' => (string) $paciente['ci'],
            'nombres' => (string) $paciente['nombres'],
            'apellidos' => (string) $paciente['apellidos'],
            'nombre_completo' => trim((string) $paciente['nombres'] . ' ' . (string) $paciente['apellidos']),
            'fecha_nacimiento' => $paciente['fecha_nacimiento'],
            'sexo' => $paciente['sexo'],
            'telefono' => $paciente['telefono'],
            'email' => $paciente['email'],
            'estado' => (string) ($paciente['estado'] ?? ''),
        ];
    }
}
