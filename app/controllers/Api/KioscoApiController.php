<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\KioscoService;
use App\Services\KioskTokenService;

final class KioscoApiController extends Controller
{
    public function __construct(
        private ?KioskTokenService $tokens = null,
        private ?KioscoService $kiosco = null
    ) {
        $this->tokens ??= new KioskTokenService();
        $this->kiosco ??= new KioscoService();
    }

    public function sesion(Request $request): Response
    {
        $deviceKey = $request->body()['device_key'] ?? null;

        if (!is_string($deviceKey) || trim($deviceKey) === '') {
            throw new HttpException(
                422,
                'Revisa los datos enviados.',
                [],
                'VALIDATION_ERROR',
                ['device_key' => 'Ingresa la clave del dispositivo.']
            );
        }

        if (!$this->tokens->deviceKeyValid(trim($deviceKey))) {
            throw new HttpException(
                401,
                'La clave del dispositivo no es válida.',
                [],
                'UNAUTHENTICATED'
            );
        }

        return $this->sinCache(json_ok($this->tokens->issue()));
    }

    public function identificar(Request $request): Response
    {
        $body = $request->body();
        $resultado = $this->comoKiosco(
            fn (): array => $this->kiosco->identificar($body['ci'] ?? null)
        );

        return $this->sinCache(json_ok($resultado));
    }

    public function registrar(Request $request): Response
    {
        $body = $request->body();
        $paciente = $this->comoKiosco(
            fn (): array => $this->kiosco->registrar($body)
        );

        return $this->sinCache(json_ok($paciente, [], 'Paciente registrado.', 201));
    }

    public function especialidades(Request $request): Response
    {
        $pacienteId = $this->pacienteIdOpcional($request);
        $data = $this->comoKiosco(
            fn (): array => $this->kiosco->especialidades($pacienteId)
        );

        return $this->sinCache(json_ok($data, [
            'page' => 1,
            'per_page' => count($data),
            'total' => count($data),
        ]));
    }

    public function medicos(Request $request): Response
    {
        $especialidad = $request->query('especialidad');
        $pacienteId = $this->pacienteIdOpcional($request);
        $data = $this->comoKiosco(
            fn (): array => $this->kiosco->medicos($especialidad, $pacienteId)
        );

        return $this->sinCache(json_ok($data, [
            'page' => 1,
            'per_page' => count($data),
            'total' => count($data),
        ]));
    }

    public function disponibilidad(Request $request): Response
    {
        $pacienteId = $this->pacienteIdOpcional($request);
        $data = $this->comoKiosco(
            fn (): array => $this->kiosco->disponibilidad(
                $request->query('medico'),
                $request->query('fecha'),
                $pacienteId
            )
        );

        return $this->sinCache(json_ok($data));
    }

    public function reservar(Request $request): Response
    {
        $body = $request->body();
        $resultado = $this->comoKiosco(
            fn (): array => $this->kiosco->reservar($body, $request->ip())
        );

        return $this->sinCache(json_ok($resultado, [], 'Cita reservada.', 201));
    }

    public function ficha(Request $request): Response
    {
        $body = $request->body();
        $verificacion = filter_var(
            $body['verificacion_biometrica'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $resultado = $this->comoKiosco(
            fn (): array => $this->kiosco->emitirFicha(
                $body['cita_id'] ?? null,
                $verificacion,
                $request->ip()
            )
        );

        $esNueva = (bool) ($resultado['nueva'] ?? false);
        unset($resultado['nueva']);

        return $this->sinCache(json_ok(
            $resultado,
            [],
            $esNueva ? 'Ficha emitida.' : 'Ficha recuperada.',
            $esNueva ? 201 : 200
        ));
    }

    public function verFicha(Request $request, array $params): Response
    {
        $codigo = rawurldecode((string) ($params['codigo'] ?? ''));

        $ficha = $this->comoKiosco(
            fn (): array => $this->kiosco->ficha($codigo)
        );

        return $this->sinCache(json_ok($ficha));
    }

    /**
     * `paciente_id` es opcional en los catálogos: permite filtrar por las
     * restricciones de sexo/edad del paciente identificado.
     */
    private function pacienteIdOpcional(Request $request): ?int
    {
        $valor = $request->query('paciente_id');

        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_scalar($valor) || !ctype_digit((string) $valor) || (int) $valor <= 0) {
            throw new HttpException(
                422,
                'Revisa los filtros de búsqueda.',
                [],
                'VALIDATION_ERROR',
                ['paciente_id' => 'Debe ser un identificador numérico.']
            );
        }

        return (int) $valor;
    }

    /**
     * El kiosco no usa sesión de usuario: sus consultas corren como
     * propietario, igual que la landing. Si hubiera una sesión abierta en el
     * navegador, se restaura al terminar.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function comoKiosco(callable $callback): mixed
    {
        $usuario = auth();

        if ($usuario === null) {
            return $callback();
        }

        DB::clearContext();

        try {
            return $callback();
        } finally {
            DB::setContext($usuario, '127.0.0.1');
        }
    }

    private function sinCache(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'no-store');
    }
}
