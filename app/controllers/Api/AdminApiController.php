<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\DisponibilidadService;
use DateTimeImmutable;
use PDOException;

abstract class AdminApiController extends Controller
{
    /** @param array<string, string> $params */
    protected function idParam(array $params, string $key = 'id'): int
    {
        $value = $params[$key] ?? null;

        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
            throw new HttpException(404, 'El recurso solicitado no existe.', [], 'NOT_FOUND');
        }

        return (int) $value;
    }

    /** @return array{page: int, per_page: int, offset: int} */
    protected function pagination(Request $request): array
    {
        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 20);

        $page = is_scalar($page) && ctype_digit((string) $page) ? max(1, (int) $page) : 1;
        $perPage = is_scalar($perPage) && ctype_digit((string) $perPage)
            ? min(100, max(1, (int) $perPage))
            : 20;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    protected function queryText(Request $request, string $key): string
    {
        $value = $request->query($key, '');

        return is_scalar($value) ? $this->limpiarTexto((string) $value) : '';
    }

    protected function queryBool(Request $request, string $key): ?bool
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value)) {
            throw new HttpException(
                422,
                'Revisa los filtros enviados.',
                [],
                'VALIDATION_ERROR',
                [$key => 'Debe ser verdadero o falso.']
            );
        }

        $boolean = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($boolean === null) {
            throw new HttpException(
                422,
                'Revisa los filtros enviados.',
                [],
                'VALIDATION_ERROR',
                [$key => 'Debe ser verdadero o falso.']
            );
        }

        return $boolean;
    }

    protected function queryDate(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || DisponibilidadService::normalizarFecha(trim((string) $value)) === null) {
            throw new HttpException(
                422,
                'Revisa los filtros enviados.',
                [],
                'VALIDATION_ERROR',
                [$key => 'Usa el formato YYYY-MM-DD.']
            );
        }

        return trim((string) $value);
    }

    protected function queryId(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
            throw new HttpException(
                422,
                'Revisa los filtros enviados.',
                [],
                'VALIDATION_ERROR',
                [$key => 'Debe ser un identificador numérico.']
            );
        }

        return (int) $value;
    }

    protected function validarRango(?string $desde, ?string $hasta): void
    {
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            throw new HttpException(
                422,
                'El rango de fechas no es válido.',
                [],
                'VALIDATION_ERROR',
                ['hasta' => 'Debe ser igual o posterior a la fecha inicial.']
            );
        }
    }

    /** @return array{desde: string, hasta: string} */
    protected function rangoMesActual(): array
    {
        $hoy = new DateTimeImmutable('today');

        return [
            'desde' => $hoy->format('Y-m-01'),
            'hasta' => $hoy->format('Y-m-t'),
        ];
    }

    /** @param string ...$roles @return array<string, mixed> */
    protected function asegurarRoles(string ...$roles): array
    {
        $usuario = $this->usuarioActual();

        if (!in_array((string) ($usuario['rol'] ?? ''), $roles, true)) {
            throw new HttpException(
                403,
                'No tienes permisos para esta sección.',
                [],
                'FORBIDDEN'
            );
        }

        return $usuario;
    }

    protected function limpiarTexto(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $texto = trim((string) $value);

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    protected function textoOpcional(mixed $value): ?string
    {
        $texto = $this->limpiarTexto($value);

        return $texto === '' ? null : $texto;
    }

    protected function booleano(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @param array<string, string> $campos
     */
    protected function validacion(array $campos): never
    {
        throw new HttpException(
            422,
            'Revisa los datos enviados.',
            [],
            'VALIDATION_ERROR',
            $campos
        );
    }

    /**
     * Convierte errores de unicidad, solapamiento o FK en conflictos de negocio.
     */
    protected function ejecutarEscritura(
        callable $callback,
        string $codigo = 'CONFLICT',
        string $mensaje = 'No se pudo guardar el registro.',
        int $status = 409
    ): mixed {
        try {
            return $callback();
        } catch (PDOException $exception) {
            $sqlState = (string) $exception->getCode();

            if (in_array($sqlState, ['23505', '23P01', '23503'], true)) {
                throw new HttpException($status, $mensaje, [], $codigo);
            }

            throw $exception;
        }
    }

    /**
     * Ejecuta una operación sin RLS y restaura el contexto al terminar. Se usa
     * solo después de autorizar explícitamente el acceso. En escrituras conserva
     * el usuario/IP para que los triggers de auditoría registren al responsable.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function comoPropietario(callable $callback): mixed
    {
        $usuario = auth();

        if ($usuario === null) {
            return $callback();
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        DB::clearContext();

        try {
            DB::setAuditContext((int) ($usuario['id'] ?? 0), $ip);

            return $callback();
        } finally {
            DB::setContext($usuario, $ip);
        }
    }

    /** @return array<string, mixed> */
    protected function usuarioActual(): array
    {
        $usuario = auth();

        if ($usuario === null) {
            throw new HttpException(401, 'No autenticado.', [], 'UNAUTHENTICATED');
        }

        return $usuario;
    }

    protected function autorizarLecturaMedico(int $medicoId): void
    {
        $usuario = $this->usuarioActual();
        $rol = (string) ($usuario['rol'] ?? '');

        if (in_array($rol, ['super_admin', 'admin', 'recepcionista'], true)) {
            return;
        }

        if ($rol === 'medico' && (int) ($usuario['medico_id'] ?? 0) === $medicoId) {
            return;
        }

        if (
            $rol === 'recepcionista_medico'
            && in_array($medicoId, array_map('intval', (array) ($usuario['medico_ids'] ?? [])), true)
        ) {
            return;
        }

        throw new HttpException(
            403,
            'No tienes permisos para consultar la agenda de este médico.',
            [],
            'FORBIDDEN'
        );
    }

    protected function asegurarMedicoPropio(int $medicoId): void
    {
        $usuario = $this->usuarioActual();

        if (
            (string) ($usuario['rol'] ?? '') !== 'medico'
            || (int) ($usuario['medico_id'] ?? 0) !== $medicoId
        ) {
            throw new HttpException(
                403,
                'Solo el propio médico puede realizar esta acción.',
                [],
                'FORBIDDEN'
            );
        }
    }

    /** @param array<int, array<string, mixed>> $data */
    protected function listaJson(array $data, int $total, int $page, int $perPage): Response
    {
        return json_ok($data, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }
}
