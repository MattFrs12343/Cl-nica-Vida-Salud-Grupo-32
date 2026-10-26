<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Models\DocumentoModel;
use App\Models\EspecialidadModel;
use App\Models\MedicoModel;
use App\Models\OrdenModel;
use App\Models\RecetaModel;
use App\Services\DisponibilidadService;
use App\Services\PedidoService;
use App\Services\ReservaWebService;

final class PublicApiController
{
    public function __construct(
        private ?EspecialidadModel $especialidades = null,
        private ?MedicoModel $medicos = null,
        private ?DisponibilidadService $disponibilidad = null,
        private ?ReservaWebService $reservas = null,
        private ?RecetaModel $recetas = null,
        private ?DocumentoModel $documentos = null,
        private ?OrdenModel $ordenes = null,
        private ?PedidoService $pedidos = null
    ) {
        $this->especialidades ??= new EspecialidadModel();
        $this->medicos ??= new MedicoModel();
        $this->disponibilidad ??= new DisponibilidadService();
        $this->reservas ??= new ReservaWebService();
        $this->recetas ??= new RecetaModel();
        $this->documentos ??= new DocumentoModel();
        $this->ordenes ??= new OrdenModel();
        $this->pedidos ??= new PedidoService();
    }

    public function especialidades(Request $request): Response
    {
        $data = $this->comoPropietario(
            fn (): array => $this->especialidades->listActivas()
        );

        return json_ok($data, [
            'page' => 1,
            'per_page' => 50,
            'total' => count($data),
        ]);
    }

    public function medicos(Request $request): Response
    {
        $especialidad = $request->query('especialidad');

        if ($especialidad !== null && $especialidad !== '') {
            if (!is_scalar($especialidad) || !ctype_digit((string) $especialidad)) {
                return json_error(
                    'VALIDATION_ERROR',
                    'Revisa los filtros de búsqueda.',
                    ['especialidad' => 'Debe ser un identificador numérico.'],
                    422
                );
            }

            $especialidadId = (int) $especialidad;
        } else {
            $especialidadId = null;
        }

        return $this->comoPropietario(function () use ($especialidadId): Response {
            if ($especialidadId !== null && $this->especialidades->findActivaById($especialidadId) === null) {
                return json_error(
                    'NOT_FOUND',
                    'La especialidad no existe o está inactiva.',
                    [],
                    404
                );
            }

            $data = $this->medicos->listActivos($especialidadId);

            return json_ok($data, [
                'page' => 1,
                'per_page' => 50,
                'total' => count($data),
            ]);
        });
    }

    public function disponibilidad(Request $request): Response
    {
        $medico = $request->query('medico');
        $fecha = $request->query('fecha');

        $campos = [];

        if (!is_scalar($medico) || !ctype_digit((string) $medico) || (int) $medico <= 0) {
            $campos['medico'] = 'Selecciona un médico válido.';
        }

        $fecha = is_scalar($fecha) ? trim((string) $fecha) : '';

        if (DisponibilidadService::normalizarFecha($fecha) === null) {
            $campos['fecha'] = 'Usa el formato YYYY-MM-DD.';
        }

        if ($campos !== []) {
            return json_error('VALIDATION_ERROR', 'Revisa los parámetros de disponibilidad.', $campos, 422);
        }

        $medicoId = (int) $medico;

        return $this->comoPropietario(function () use ($medicoId, $fecha): Response {
            $medicoData = $this->medicos->findActivoById($medicoId);

            if ($medicoData === null) {
                return json_error('NOT_FOUND', 'El médico no existe o está inactivo.', [], 404);
            }

            // La agenda se calcula sin RLS para ver también las citas de otros pacientes.
            $slots = $this->disponibilidad->slots($medicoId, $fecha, 'web');

            return json_ok([
                'fecha' => $fecha,
                'medico_id' => $medicoId,
                'canal' => 'web',
                'duracion_cita_min' => (int) $medicoData['duracion_cita_min'],
                'slots' => $slots,
            ]);
        });
    }

    public function reservar(Request $request): Response
    {
        $resultado = $this->reservas->reservar($request->body(), auth(), $request->ip());

        return json_ok($resultado, [], 'Cita reservada. Revisa tu correo.', 201);
    }

    /**
     * Consulta pública de un pedido (receta, documento u orden) por su
     * código visible + código de acceso de 4 dígitos (Spec 11). No usa
     * sesión: el código de acceso es la única autorización.
     */
    public function pedido(Request $request): Response
    {
        $codigo = $request->query('codigo');
        $acceso = $request->query('acceso');

        $campos = [];

        if (!is_scalar($codigo) || trim((string) $codigo) === '') {
            $campos['codigo'] = 'Ingresa el código del pedido.';
        }

        if (!is_scalar($acceso) || !preg_match('/^\d{4}$/', trim((string) $acceso))) {
            $campos['acceso'] = 'Ingresa el código de acceso de 4 dígitos.';
        }

        if ($campos !== []) {
            return json_error('VALIDATION_ERROR', 'Revisa los datos ingresados.', $campos, 422);
        }

        $codigo = strtoupper(trim((string) $codigo));
        $acceso = trim((string) $acceso);
        $tipo = $this->pedidos->tipoDesdeCodigo($codigo);

        if ($tipo === null) {
            return json_error('NOT_FOUND', 'Pedido no encontrado.', [], 404);
        }

        $pedido = $this->comoPropietario(fn (): ?array => match ($tipo) {
            'receta' => $this->recetas->porCodigo($codigo),
            'documento' => $this->documentos->porCodigo($codigo),
            'orden' => $this->ordenes->porCodigo($codigo),
        });

        if ($pedido === null) {
            return json_error('NOT_FOUND', 'Pedido no encontrado.', [], 404);
        }

        if (!hash_equals((string) $pedido['codigo_acceso'], $acceso)) {
            return json_error('FORBIDDEN', 'Código de acceso inválido.', [], 403);
        }

        unset($pedido['paciente']['telefono'], $pedido['paciente']['ci']);

        // `$pedido` puede traer su propia columna `tipo` (certificado,
        // laboratorio...); la categoría del pedido (receta/documento/orden)
        // debe prevalecer para que el cliente sepa cómo renderizarlo.
        return json_ok(array_merge($pedido, ['tipo' => $tipo]));
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function comoPropietario(callable $callback): mixed
    {
        $usuario = auth();

        if ($usuario === null) {
            return $callback();
        }

        DB::clearContext();

        try {
            return $callback();
        } finally {
            DB::setContext($usuario);
        }
    }
}
