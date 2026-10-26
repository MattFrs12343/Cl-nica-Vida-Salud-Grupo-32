<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use App\Models\CitaModel;
use App\Models\ConsultaModel;
use PDOException;

/**
 * Registro de la consulta médica al atender una cita (Spec 10 / M-31).
 *
 * La cita debe estar `confirmada` o ya `atendida` (si el botón atender de
 * Spec 07 corrió antes). Si está `confirmada`, esta misma operación la pasa
 * a `atendida` dentro de la transacción. `consultas.cita_id` es único: una
 * segunda consulta sobre la misma cita se rechaza como conflicto.
 */
final class ConsultaService
{
    private const MAX_TEXTO = 4000;

    public function __construct(
        private ?CitaModel $citas = null,
        private ?ConsultaModel $consultas = null
    ) {
        $this->citas ??= new CitaModel();
        $this->consultas ??= new ConsultaModel();
    }

    /**
     * @param array<string, mixed> $datos
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    public function crear(int $citaId, array $datos, array $usuario): array
    {
        $medicoId = (int) ($usuario['medico_id'] ?? 0);

        if ($medicoId <= 0) {
            throw new HttpException(403, 'No tienes permisos para registrar consultas.', [], 'FORBIDDEN');
        }

        $campos = [
            'motivo' => $this->texto($datos['motivo'] ?? null, 'motivo', 200, true),
            'anamnesis' => $this->texto($datos['anamnesis'] ?? null, 'anamnesis', self::MAX_TEXTO, false),
            'examen_fisico' => $this->texto($datos['examen_fisico'] ?? null, 'examen_fisico', self::MAX_TEXTO, false),
            'diagnostico' => $this->texto($datos['diagnostico'] ?? null, 'diagnostico', self::MAX_TEXTO, true),
            'tratamiento' => $this->texto($datos['tratamiento'] ?? null, 'tratamiento', self::MAX_TEXTO, false),
            'indicaciones' => $this->texto($datos['indicaciones'] ?? null, 'indicaciones', self::MAX_TEXTO, false),
            'signos_vitales' => $this->signosVitales($datos['signos_vitales'] ?? null),
        ];

        try {
            $id = DB::transaction(function () use ($citaId, $medicoId, $campos, $usuario): int {
                $cita = $this->citas->findRawById($citaId, true);

                if ($cita === null) {
                    throw new HttpException(404, 'La cita no existe.', [], 'NOT_FOUND');
                }

                if ((int) $cita['medico_id'] !== $medicoId) {
                    throw new HttpException(
                        403,
                        'No tienes permisos para atender esta cita.',
                        [],
                        'FORBIDDEN'
                    );
                }

                $estado = (string) $cita['estado'];

                if (!in_array($estado, ['confirmada', 'atendida'], true)) {
                    throw new HttpException(
                        409,
                        'La cita debe estar confirmada para registrar la consulta.',
                        [],
                        'INVALID_STATE'
                    );
                }

                if ($estado === 'confirmada') {
                    $this->citas->cambiarEstado($citaId, 'atendida');
                    $this->citas->registrarHistorial(
                        $citaId,
                        'confirmada',
                        'atendida',
                        (int) ($usuario['id'] ?? 0) ?: null,
                        'Consulta registrada'
                    );
                }

                return $this->consultas->crear([
                    'cita_id' => $citaId,
                    'paciente_id' => (int) $cita['paciente_id'],
                    'medico_id' => $medicoId,
                    ...$campos,
                ]);
            });
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new HttpException(
                    409,
                    'Esta cita ya tiene una consulta registrada.',
                    [],
                    'CONFLICT'
                );
            }

            throw $exception;
        }

        $consulta = $this->consultas->porId($id);

        if ($consulta === null) {
            throw new HttpException(500, 'No se pudo recuperar la consulta registrada.', [], 'SERVER_ERROR');
        }

        return $consulta;
    }

    private function texto(mixed $valor, string $campo, int $maximo, bool $requerido): ?string
    {
        if (!is_scalar($valor)) {
            if ($requerido) {
                $this->validacion([$campo => 'Este campo es obligatorio.']);
            }

            return null;
        }

        $texto = preg_replace('/\s+/u', ' ', trim((string) $valor)) ?? trim((string) $valor);

        if ($texto === '') {
            if ($requerido) {
                $this->validacion([$campo => 'Este campo es obligatorio.']);
            }

            return null;
        }

        if (mb_strlen($texto) > $maximo) {
            $this->validacion([$campo => 'No puede superar ' . $maximo . ' caracteres.']);
        }

        return $texto;
    }

    /** @return array<string, mixed>|null */
    private function signosVitales(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (!is_array($valor)) {
            $this->validacion(['signos_vitales' => 'Debe ser un objeto con los signos vitales.']);
        }

        $numericos = ['fc', 'temp', 'peso', 'spo2', 'talla'];
        $resultado = [];

        if (isset($valor['pa'])) {
            if (!is_scalar($valor['pa'])) {
                $this->validacion(['signos_vitales' => 'La presión arterial no es válida.']);
            }

            $pa = trim((string) $valor['pa']);

            if ($pa !== '') {
                if (mb_strlen($pa) > 20) {
                    $this->validacion(['signos_vitales' => 'La presión arterial no puede superar 20 caracteres.']);
                }

                $resultado['pa'] = $pa;
            }
        }

        foreach ($numericos as $clave) {
            if (!isset($valor[$clave]) || $valor[$clave] === '') {
                continue;
            }

            if (!is_numeric($valor[$clave])) {
                $this->validacion(['signos_vitales' => 'Revisa los valores numéricos de los signos vitales.']);
            }

            $resultado[$clave] = $clave === 'fc' || $clave === 'spo2'
                ? (int) $valor[$clave]
                : (float) $valor[$clave];
        }

        return $resultado === [] ? null : $resultado;
    }

    /** @param array<string, string> $campos */
    private function validacion(array $campos): never
    {
        throw new HttpException(
            422,
            'Revisa los datos de la consulta.',
            [],
            'VALIDATION_ERROR',
            $campos
        );
    }
}
