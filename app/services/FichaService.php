<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\HttpException;
use PDO;

/**
 * Emisión y consulta de fichas (Spec 09).
 *
 * La ficha es el ticket de la cita: al emitirse, una cita `programada` pasa a
 * `confirmada` y se registra en el historial de estados. El QR se devuelve
 * como texto (`qr_contenido`) y lo dibuja el cliente del kiosco.
 */
final class FichaService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::connection();
    }

    /**
     * Emite la ficha de una cita o recupera la existente (idempotente por
     * `cita_id`). El código FIC-#### lo genera el trigger.
     *
     * @return array<string, mixed>
     */
    public function emitir(
        int $citaId,
        string $canal = 'web',
        bool $verificacionBiometrica = false,
        ?string $observacion = null
    ): array {
        if ($citaId <= 0) {
            throw new HttpException(
                422,
                'Revisa los datos de la ficha.',
                [],
                'VALIDATION_ERROR',
                ['cita_id' => 'Selecciona una cita válida.']
            );
        }

        if (!in_array($canal, ['web', 'kiosco'], true)) {
            throw new HttpException(422, 'El canal de la ficha no es válido.', [], 'VALIDATION_ERROR');
        }

        return DB::transaction(function () use ($citaId, $canal, $verificacionBiometrica, $observacion): array {
            $cita = $this->buscarCita($citaId, true);

            if ($cita === null) {
                throw new HttpException(404, 'La cita no existe.', [], 'CITA_NOT_FOUND');
            }

            if ((string) $cita['estado'] === 'cancelada') {
                throw new HttpException(
                    409,
                    'No se puede emitir una ficha de una cita cancelada.',
                    [],
                    'CITA_CANCELLED'
                );
            }

            $ficha = $this->buscarFichaPorCita($citaId);
            $esNueva = $ficha === null;

            if ($ficha === null) {
                $codigoAcceso = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                $statement = $this->pdo->prepare(
                    'INSERT INTO fichas (cita_id, canal, codigo_acceso, verificacion_biometrica)
                     VALUES (
                         :cita_id,
                         CAST(:canal AS canal_ficha),
                         :codigo_acceso,
                         :verificacion
                     )
                     RETURNING id, codigo, cita_id, canal, codigo_acceso,
                               verificacion_biometrica, emitida_at'
                );
                $statement->execute([
                    'cita_id' => $citaId,
                    'canal' => $canal,
                    'codigo_acceso' => $codigoAcceso,
                    'verificacion' => $verificacionBiometrica ? 'true' : 'false',
                ]);

                $ficha = $statement->fetch() ?: [];
            }

            $estado = (string) $cita['estado'];

            if ($estado === 'programada') {
                $this->pdo->prepare(
                    "UPDATE citas SET estado = 'confirmada' WHERE id = :id"
                )->execute(['id' => $citaId]);

                $this->pdo->prepare(
                    "INSERT INTO citas_estado_historial (
                         cita_id, estado_anterior, estado_nuevo, usuario_id, observacion
                     ) VALUES (
                         :cita_id, 'programada', 'confirmada', fn_app_usuario_id(), :observacion
                     )"
                )->execute([
                    'cita_id' => $citaId,
                    'observacion' => $observacion ?? ('Ficha ' . (string) ($ficha['codigo'] ?? '') . ' emitida'),
                ]);

                $estado = 'confirmada';
            }

            $cita['estado'] = $estado;

            $datos = $this->normalizar($cita, $ficha);
            $datos['nueva'] = $esNueva;

            return $datos;
        });
    }

    /**
     * Reconsulta una ficha por su código visible (FIC-####).
     *
     * @return array<string, mixed>|null
     */
    public function porCodigo(string $codigo): ?array
    {
        $statement = $this->pdo->prepare(
            $this->sqlCita()
            . ' JOIN fichas f ON f.cita_id = c.id
                WHERE f.codigo = :codigo
                LIMIT 1'
        );
        $statement->execute(['codigo' => $codigo]);
        $cita = $statement->fetch();

        if ($cita === false) {
            return null;
        }

        $ficha = $this->buscarFichaPorCita((int) $cita['cita_id']);

        return $ficha === null ? null : $this->normalizar($cita, $ficha);
    }

    /** @return array<string, mixed>|null */
    private function buscarCita(int $citaId, bool $bloquear = false): ?array
    {
        $statement = $this->pdo->prepare(
            $this->sqlCita()
            . ' WHERE c.id = :id'
            . ($bloquear ? ' FOR UPDATE OF c' : '')
            . ' LIMIT 1'
        );
        $statement->execute(['id' => $citaId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    private function buscarFichaPorCita(int $citaId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, codigo, cita_id, canal, codigo_acceso,
                    verificacion_biometrica, emitida_at
             FROM fichas
             WHERE cita_id = :cita_id
             LIMIT 1'
        );
        $statement->execute(['cita_id' => $citaId]);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    private function sqlCita(): string
    {
        return 'SELECT c.id AS cita_id, c.codigo AS cita_codigo, c.fecha,
                       c.hora_inicio, c.hora_fin, c.estado, c.origen,
                       c.paciente_id, c.medico_id, c.especialidad_id,
                       p.codigo AS paciente_codigo, p.ci,
                       p.nombres AS paciente_nombres, p.apellidos AS paciente_apellidos,
                       m.nombres AS medico_nombres, m.apellidos AS medico_apellidos,
                       e.nombre AS especialidad_nombre
                FROM citas c
                JOIN pacientes p ON p.id = c.paciente_id
                JOIN medicos m ON m.id = c.medico_id
                JOIN especialidades e ON e.id = c.especialidad_id';
    }

    /**
     * @param array<string, mixed> $cita
     * @param array<string, mixed> $ficha
     * @return array<string, mixed>
     */
    private function normalizar(array $cita, array $ficha): array
    {
        $codigoFicha = (string) ($ficha['codigo'] ?? '');
        $codigoCita = (string) ($cita['cita_codigo'] ?? '');
        $fecha = (string) ($cita['fecha'] ?? '');
        $horaInicio = substr((string) ($cita['hora_inicio'] ?? ''), 0, 5);
        $horaFin = substr((string) ($cita['hora_fin'] ?? ''), 0, 5);
        $pacienteNombre = trim(
            (string) ($cita['paciente_nombres'] ?? '') . ' ' . (string) ($cita['paciente_apellidos'] ?? '')
        );
        $medicoNombre = trim(
            (string) ($cita['medico_nombres'] ?? '') . ' ' . (string) ($cita['medico_apellidos'] ?? '')
        );
        $especialidadNombre = (string) ($cita['especialidad_nombre'] ?? '');
        $fechaImpresion = $fecha !== ''
            ? (new \DateTimeImmutable($fecha))->format('d/m/Y')
            : '';

        return [
            'codigo' => $codigoFicha,
            'codigo_acceso' => (string) ($ficha['codigo_acceso'] ?? ''),
            'canal' => (string) ($ficha['canal'] ?? ''),
            'verificacion_biometrica' => (bool) ($ficha['verificacion_biometrica'] ?? false),
            'emitida_at' => (string) ($ficha['emitida_at'] ?? ''),
            'qr_contenido' => 'VIDASALUD|' . $codigoFicha . '|' . $codigoCita,
            'cita' => [
                'id' => (int) ($cita['cita_id'] ?? 0),
                'codigo' => $codigoCita,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'estado' => (string) ($cita['estado'] ?? ''),
                'origen' => (string) ($cita['origen'] ?? ''),
            ],
            'paciente' => [
                'id' => (int) ($cita['paciente_id'] ?? 0),
                'codigo' => (string) ($cita['paciente_codigo'] ?? ''),
                'ci' => (string) ($cita['ci'] ?? ''),
                'nombre_completo' => $pacienteNombre,
            ],
            'medico' => [
                'id' => (int) ($cita['medico_id'] ?? 0),
                'nombre_completo' => $medicoNombre !== '' ? 'Dr(a). ' . $medicoNombre : '',
            ],
            'especialidad' => [
                'id' => (int) ($cita['especialidad_id'] ?? 0),
                'nombre' => $especialidadNombre,
            ],
            'impresion' => [
                'clinica' => (string) config('app.name', 'Clínica Vida Salud'),
                'titulo' => 'Ficha de atención',
                'paciente' => $pacienteNombre,
                'ci' => (string) ($cita['ci'] ?? ''),
                'especialidad' => $especialidadNombre,
                'medico' => $medicoNombre !== '' ? 'Dr(a). ' . $medicoNombre : '',
                'fecha' => $fechaImpresion,
                'hora' => $horaInicio,
                'codigo' => $codigoFicha,
                'codigo_acceso' => (string) ($ficha['codigo_acceso'] ?? ''),
                'instrucciones' => 'Presenta esta ficha en recepción. Sin ficha no se atiende.',
            ],
        ];
    }
}
