<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\HorarioModel;
use App\Models\MedicoModel;
use App\Services\DisponibilidadService;

final class HorarioApiController extends AdminApiController
{
    public function __construct(
        private ?HorarioModel $horarios = null,
        private ?MedicoModel $medicos = null
    ) {
        $this->horarios ??= new HorarioModel();
        $this->medicos ??= new MedicoModel();
    }

    /** @param array<string, string> $params */
    public function index(Request $request, array $params): Response
    {
        $medicoId = $this->idParam($params, 'medico');
        $this->autorizarLecturaMedico($medicoId);

        $medico = $this->medicos->findById($medicoId);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        return json_ok($this->horarios->listarPorMedico(
            $medicoId,
            $this->queryBool($request, 'activo')
        ));
    }

    /** @param array<string, string> $params */
    public function store(Request $request, array $params): Response
    {
        $medicoId = $this->idParam($params, 'medico');
        $medico = $this->medicos->findById($medicoId);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->validarFranja(
            $request->body(),
            (int) $medico['duracion_cita_min'],
            null
        );
        $datos['medico_id'] = $medicoId;

        if ($datos['activo'] && $this->horarios->haySolape(
            $medicoId,
            (int) $datos['dia_semana'],
            (string) $datos['hora_inicio'],
            (string) $datos['hora_fin']
        )) {
            $this->conflictoSolape();
        }

        $id = $this->ejecutarEscritura(
            fn (): int => $this->horarios->crear($datos),
            'OVERLAP',
            'La franja se solapa con otro horario activo del médico.'
        );

        return json_ok($this->horarios->findById($id), [], 'Franja creada.', 201);
    }

    /** @param array<string, string> $params */
    public function update(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->horarios->findById($id);

        if ($existente === null) {
            throw new HttpException(404, 'La franja no existe.', [], 'NOT_FOUND');
        }

        $medico = $this->medicos->findById((int) $existente['medico_id']);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->validarFranja(
            $request->body(),
            (int) $medico['duracion_cita_min'],
            $existente
        );

        if ($datos['activo'] && $this->horarios->haySolape(
            (int) $existente['medico_id'],
            (int) $datos['dia_semana'],
            (string) $datos['hora_inicio'],
            (string) $datos['hora_fin'],
            $id
        )) {
            $this->conflictoSolape();
        }

        $this->ejecutarEscritura(
            fn () => $this->horarios->actualizar($id, $datos),
            'OVERLAP',
            'La franja se solapa con otro horario activo del médico.'
        );

        return json_ok($this->horarios->findById($id), [], 'Franja actualizada.');
    }

    /** @param array<string, string> $params */
    public function destroy(Request $request, array $params): Response
    {
        $id = $this->idParam($params);
        $existente = $this->horarios->findById($id);

        if ($existente === null) {
            throw new HttpException(404, 'La franja no existe.', [], 'NOT_FOUND');
        }

        $this->horarios->desactivar($id);

        return Response::noContent();
    }

    /** @param array<string, string> $params */
    public function bloqueos(Request $request, array $params): Response
    {
        $medicoId = $this->idParam($params, 'medico');
        $this->autorizarLecturaMedico($medicoId);

        $medico = $this->medicos->findById($medicoId);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $campos = [];
        $desde = $this->fechaQuery($request, 'desde', $campos);
        $hasta = $this->fechaQuery($request, 'hasta', $campos);

        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $campos['hasta'] = 'La fecha final no puede ser anterior a la inicial.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        return json_ok($this->horarios->listarBloqueos($medicoId, $desde, $hasta));
    }

    /** @param array<string, string> $params */
    public function storeBloqueo(Request $request, array $params): Response
    {
        $medicoId = $this->idParam($params, 'medico');
        $medico = $this->medicos->findById($medicoId);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe.', [], 'NOT_FOUND');
        }

        $datos = $this->validarBloqueo($request->body());
        $datos['medico_id'] = $medicoId;

        $id = $this->horarios->crearBloqueo($datos);

        return json_ok($this->horarios->findBloqueo($id), [], 'Bloqueo creado.', 201);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed>|null $existente
     * @return array<string, mixed>
     */
    private function validarFranja(array $body, int $duracion, ?array $existente): array
    {
        $campos = [];

        $dia = array_key_exists('dia_semana', $body)
            ? $this->entero($body['dia_semana'], 0, 6, 'dia_semana', $campos)
            : ($existente === null ? null : (int) $existente['dia_semana']);
        $horaInicio = array_key_exists('hora_inicio', $body)
            ? $this->hora($body['hora_inicio'], 'hora_inicio', $campos)
            : ($existente === null ? null : (string) $existente['hora_inicio']);
        $horaFin = array_key_exists('hora_fin', $body)
            ? $this->hora($body['hora_fin'], 'hora_fin', $campos)
            : ($existente === null ? null : (string) $existente['hora_fin']);
        $cuposWeb = array_key_exists('cupos_web', $body)
            ? $this->entero($body['cupos_web'], 0, 32767, 'cupos_web', $campos)
            : ($existente === null ? 0 : (int) $existente['cupos_web']);
        $cuposKiosco = array_key_exists('cupos_kiosco', $body)
            ? $this->entero($body['cupos_kiosco'], 0, 32767, 'cupos_kiosco', $campos)
            : ($existente === null ? 0 : (int) $existente['cupos_kiosco']);
        $activo = array_key_exists('activo', $body)
            ? $this->booleano($body['activo'])
            : ($existente === null ? true : (bool) $existente['activo']);

        if ($dia === null && !isset($campos['dia_semana'])) {
            $campos['dia_semana'] = 'Selecciona un día de la semana.';
        }

        if ($horaInicio === null && !isset($campos['hora_inicio'])) {
            $campos['hora_inicio'] = 'Indica la hora de inicio.';
        }

        if ($horaFin === null && !isset($campos['hora_fin'])) {
            $campos['hora_fin'] = 'Indica la hora de fin.';
        }

        if ($activo === null) {
            $campos['activo'] = 'Debe ser verdadero o falso.';
        }

        if ($horaInicio !== null && $horaFin !== null) {
            $inicioMinutos = $this->aMinutos($horaInicio);
            $finMinutos = $this->aMinutos($horaFin);

            if ($inicioMinutos >= $finMinutos) {
                $campos['hora_fin'] = 'La hora de fin debe ser posterior a la de inicio.';
            } elseif ($duracion > 0) {
                $slots = intdiv($finMinutos - $inicioMinutos, $duracion);

                if ($slots < 1) {
                    $campos['hora_fin'] = 'La franja debe permitir al menos una cita.';
                } elseif ($cuposWeb !== null && $cuposKiosco !== null && ($cuposWeb + $cuposKiosco) > $slots) {
                    $campos['cupos_web'] = 'Los cupos web y kiosco no pueden superar los '
                        . $slots . ' espacios de la franja.';
                }
            }
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        return [
            'dia_semana' => (int) $dia,
            'hora_inicio' => (string) $horaInicio,
            'hora_fin' => (string) $horaFin,
            'cupos_web' => (int) $cuposWeb,
            'cupos_kiosco' => (int) $cuposKiosco,
            'activo' => (bool) $activo,
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function validarBloqueo(array $body): array
    {
        $campos = [];
        $fecha = $this->textoOpcional($body['fecha'] ?? null);

        if ($fecha === null || DisponibilidadService::normalizarFecha($fecha) === null) {
            $campos['fecha'] = 'Usa una fecha válida en formato AAAA-MM-DD.';
        }

        $inicio = $this->textoOpcional($body['hora_inicio'] ?? null);
        $fin = $this->textoOpcional($body['hora_fin'] ?? null);

        if (($inicio === null) !== ($fin === null)) {
            $campos['hora_inicio'] = 'Indica ambas horas o deja las dos vacías para todo el día.';
        }

        if ($inicio !== null && $fin !== null) {
            $inicio = DisponibilidadService::normalizarHora($inicio);
            $fin = DisponibilidadService::normalizarHora($fin);

            if ($inicio === null || $fin === null) {
                $campos['hora_inicio'] = 'Usa horas válidas en formato HH:MM.';
            } elseif ($this->aMinutos($inicio) >= $this->aMinutos($fin)) {
                $campos['hora_fin'] = 'La hora de fin debe ser posterior a la de inicio.';
            }
        }

        $motivo = $this->textoOpcional($body['motivo'] ?? null);

        if ($motivo !== null && strlen($motivo) > 120) {
            $campos['motivo'] = 'El motivo no puede superar 120 caracteres.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        return [
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'motivo' => $motivo,
        ];
    }

    /** @param array<string, string> $campos */
    private function entero(mixed $value, int $minimo, int $maximo, string $campo, array &$campos): ?int
    {
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

    /** @param array<string, string> $campos */
    private function hora(mixed $value, string $campo, array &$campos): ?string
    {
        if (!is_scalar($value)) {
            $campos[$campo] = 'Usa una hora válida en formato HH:MM.';

            return null;
        }

        $hora = DisponibilidadService::normalizarHora(trim((string) $value));

        if ($hora === null) {
            $campos[$campo] = 'Usa una hora válida en formato HH:MM.';
        }

        return $hora;
    }

    /** @param array<string, string> $campos */
    private function fechaQuery(Request $request, string $campo, array &$campos): ?string
    {
        $valor = $this->queryText($request, $campo);

        if ($valor === '') {
            return null;
        }

        $fecha = DisponibilidadService::normalizarFecha($valor);

        if ($fecha === null) {
            $campos[$campo] = 'Usa una fecha válida en formato AAAA-MM-DD.';
        }

        return $fecha;
    }

    private function aMinutos(string $hora): int
    {
        [$horas, $minutos] = array_map('intval', explode(':', $hora));

        return ($horas * 60) + $minutos;
    }

    private function conflictoSolape(): never
    {
        throw new HttpException(
            409,
            'La franja se solapa con otro horario activo del médico.',
            [],
            'OVERLAP'
        );
    }
}
