<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\PagoModel;

/**
 * Registro y consulta de pagos de recepciA3n (M-28).
 *
 * El cobro es presencial: no hay pasarela. `visa` y `mastercard` son solo
 * etiquetas del movimiento; la referencia es libre.
 */
final class PagoApiController extends AdminApiController
{
    private const METODOS = ['efectivo', 'visa', 'mastercard'];

    public function __construct(private ?PagoModel $pagos = null)
    {
        $this->pagos ??= new PagoModel();
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

        $desde = $this->queryDate($request, 'desde');
        $hasta = $this->queryDate($request, 'hasta');
        $this->validarRango($desde, $hasta);

        $medicoId = $this->queryId($request, 'medico');
        $medicoIds = $this->medicoIdsDe($usuario);

        if ($medicoId !== null && $medicoIds !== null && !in_array($medicoId, $medicoIds, true)) {
            throw new HttpException(
                403,
                'No tienes permisos para consultar los pagos de este mAcdico.',
                [],
                'FORBIDDEN'
            );
        }

        $paginacion = $this->pagination($request);

        $resultado = $this->comoPropietario(
            fn (): array => $this->pagos->listar([
                'medico' => $medicoId,
                'desde' => $desde,
                'hasta' => $hasta,
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

    public function store(Request $request): Response
    {
        $usuario = $this->asegurarRoles(
            'super_admin',
            'admin',
            'recepcionista',
            'recepcionista_medico',
            'medico'
        );

        $pacienteId = $this->enteroPositivo($request->input('paciente_id'));
        $medicoId = $this->enteroPositivo($request->input('medico_id'));
        $campos = [];

        if ($pacienteId === null) {
            $campos['paciente_id'] = 'Selecciona un paciente vA�lido.';
        }

        if ($medicoId === null) {
            $campos['medico_id'] = 'Selecciona un mAcdico vA�lido.';
        }

        $monto = $this->montoValido($request->input('monto'));

        if ($monto === null) {
            $campos['monto'] = 'Ingresa un monto mayor a cero con hasta dos decimales.';
        }

        $metodo = strtolower($this->limpiarTexto($request->input('metodo', '')));

        if (!in_array($metodo, self::METODOS, true)) {
            $campos['metodo'] = 'Usa efectivo, visa o mastercard.';
        }

        $referencia = $this->textoOpcional($request->input('referencia_paciente'));

        if ($referencia !== null && mb_strlen($referencia) > 150) {
            $campos['referencia_paciente'] = 'No puede superar los 150 caracteres.';
        }

        if ($campos !== []) {
            $this->validacion($campos);
        }

        $medicoIds = $this->medicoIdsDe($usuario);

        if ($medicoIds !== null && !in_array($medicoId, $medicoIds, true)) {
            throw new HttpException(
                403,
                'No tienes permisos para registrar pagos de este mAcdico.',
                [],
                'FORBIDDEN'
            );
        }

        $pacienteExiste = $this->comoPropietario(
            fn (): bool => $this->pagos->pacienteExiste((int) $pacienteId)
        );

        if (!$pacienteExiste) {
            $this->validacion(['paciente_id' => 'El paciente no existe.']);
        }

        $medicoActivo = $this->comoPropietario(
            fn (): bool => $this->pagos->medicoActivo((int) $medicoId)
        );

        if (!$medicoActivo) {
            $this->validacion(['medico_id' => 'El mAcdico no existe o estAc inactivo.']);
        }

        $id = $this->comoPropietario(
            fn (): int => $this->pagos->crear([
                'paciente_id' => (int) $pacienteId,
                'medico_id' => (int) $medicoId,
                'registrado_por' => (int) ($usuario['id'] ?? 0),
                'monto' => $monto,
                'metodo' => $metodo,
                'referencia_paciente' => $referencia,
            ])
        );

        $pago = $this->comoPropietario(fn (): ?array => $this->pagos->porId($id));

        if ($pago === null) {
            throw new HttpException(500, 'No se pudo recuperar el pago registrado.', [], 'INTERNAL_ERROR');
        }

        return json_ok($pago, [], 'Pago registrado.', 201);
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
        $id = $this->idParam($params);
        $pago = $this->comoPropietario(fn (): ?array => $this->pagos->porId($id));

        if ($pago === null) {
            throw new HttpException(404, 'Pago no encontrado.', [], 'NOT_FOUND');
        }

        $medicoIds = $this->medicoIdsDe($usuario);
        $medicoId = (int) ($pago['medico']['id'] ?? 0);

        if ($medicoIds !== null && !in_array($medicoId, $medicoIds, true)) {
            throw new HttpException(
                403,
                'No tienes permisos para consultar este pago.',
                [],
                'FORBIDDEN'
            );
        }

        return json_ok($pago);
    }

    private function enteroPositivo(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && ctype_digit(trim($value))) {
            $entero = (int) trim($value);

            return $entero > 0 ? $entero : null;
        }

        return null;
    }

    private function montoValido(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $texto = (string) $value;
        } elseif (is_string($value)) {
            $texto = trim($value);
        } else {
            return null;
        }

        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $texto)) {
            return null;
        }

        if ((float) $texto <= 0) {
            return null;
        }

        return number_format((float) $texto, 2, '.', '');
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
