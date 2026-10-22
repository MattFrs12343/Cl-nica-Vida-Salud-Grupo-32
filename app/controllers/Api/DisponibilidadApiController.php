<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\MedicoModel;
use App\Services\DisponibilidadService;

final class DisponibilidadApiController extends AdminApiController
{
    private const CANALES = ['web', 'kiosco', 'recepcion'];

    public function __construct(
        private ?DisponibilidadService $disponibilidad = null,
        private ?MedicoModel $medicos = null
    ) {
        $this->disponibilidad ??= new DisponibilidadService();
        $this->medicos ??= new MedicoModel();
    }

    public function show(Request $request, array $params = []): Response
    {
        $campos = [];
        $medicoRaw = $request->query('medico');

        if (!is_scalar($medicoRaw) || !ctype_digit((string) $medicoRaw) || (int) $medicoRaw <= 0) {
            $campos['medico'] = 'Indica un médico válido.';
            $medicoId = 0;
        } else {
            $medicoId = (int) $medicoRaw;
        }

        $fechaRaw = $request->query('fecha');
        $fecha = is_scalar($fechaRaw) ? DisponibilidadService::normalizarFecha((string) $fechaRaw) : null;

        if ($fecha === null) {
            $campos['fecha'] = 'Usa una fecha válida en formato AAAA-MM-DD.';
        }

        $canalRaw = $request->query('canal', 'web');
        $canal = is_scalar($canalRaw) ? trim((string) $canalRaw) : '';

        if (!in_array($canal, self::CANALES, true)) {
            $campos['canal'] = 'Usa web, kiosco o recepcion.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        $this->autorizarLecturaMedico($medicoId);

        $medico = $this->medicos->findActivoById($medicoId);

        if ($medico === null) {
            throw new HttpException(404, 'El médico no existe o está inactivo.', [], 'NOT_FOUND');
        }

        $slots = $this->comoPropietario(
            fn (): array => $this->disponibilidad->slots($medicoId, (string) $fecha, (string) $canal)
        );

        $slots = array_map(
            static fn (array $slot): array => $slot + ['cupos_libres' => $slot['cupo_restante']],
            $slots
        );

        return json_ok([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
            'canal' => $canal,
            'duracion_cita_min' => (int) $medico['duracion_cita_min'],
            'slots' => $slots,
        ]);
    }
}
