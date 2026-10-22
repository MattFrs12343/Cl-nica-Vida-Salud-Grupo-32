<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CitaModel;
use App\Models\HorarioModel;
use App\Models\MedicoModel;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Motor de disponibilidad (Spec 05).
 *
 * Calcula los bloques de una fecha para un médico y canal, descontando
 * bloqueos, citas activas y cupos del canal.
 */
final class DisponibilidadService
{
    public function __construct(
        private ?MedicoModel $medicos = null,
        private ?HorarioModel $horarios = null,
        private ?CitaModel $citas = null
    ) {
        $this->medicos ??= new MedicoModel();
        $this->horarios ??= new HorarioModel();
        $this->citas ??= new CitaModel();
    }

    /**
     * @return array<int, array{hora: string, hora_inicio: string, hora_fin: string, disponible: bool, cupo_restante: int}>
     */
    public function slots(int $medicoId, string $fecha, string $canal = 'web'): array
    {
        if (self::normalizarFecha($fecha) === null || !in_array($canal, ['web', 'kiosco', 'recepcion'], true)) {
            return [];
        }

        $medico = $this->medicos->findActivoById($medicoId);

        if ($medico === null) {
            return [];
        }

        $hoy = new DateTimeImmutable('today');
        $dia = new DateTimeImmutable($fecha);

        if ($dia < $hoy) {
            return [];
        }

        $diaSemana = (int) $dia->format('w');
        $franjas = $this->horarios->franjasActivas($medicoId, $diaSemana);

        if ($franjas === []) {
            return [];
        }

        $duracion = max(5, (int) $medico['duracion_cita_min']);
        $bloqueos = $this->horarios->bloqueosDelDia($medicoId, $fecha);
        $citas = $this->citas->activasDelDia($medicoId, $fecha);
        $ahora = new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone', 'America/La_Paz')));

        $slots = [];

        foreach ($franjas as $franja) {
            $inicioFranja = self::aMinutos((string) $franja['hora_inicio']);
            $finFranja = self::aMinutos((string) $franja['hora_fin']);

            if ($inicioFranja === null || $finFranja === null || $finFranja <= $inicioFranja) {
                continue;
            }

            $cupo = $this->cupoDelCanal($franja, $canal);
            $usado = $cupo === null
                ? 0
                : $this->citas->contarCanalEnFranja(
                    $medicoId,
                    $fecha,
                    self::desdeMinutos($inicioFranja),
                    self::desdeMinutos($finFranja),
                    $canal
                );
            $restante = $cupo === null ? null : max(0, $cupo - $usado);

            for ($inicio = $inicioFranja; $inicio + $duracion <= $finFranja; $inicio += $duracion) {
                $fin = $inicio + $duracion;
                $hora = self::desdeMinutos($inicio);
                $horaFin = self::desdeMinutos($fin);

                $enBloqueo = $this->cruzaBloqueo($bloqueos, $inicio, $fin);
                $ocupado = $this->cruzaCita($citas, $inicio, $fin);
                $enPasado = $this->esPasado($fecha, $inicio, $ahora);
                $sinCupo = $restante !== null && $restante <= 0;

                $disponible = !$enBloqueo && !$ocupado && !$enPasado && !$sinCupo;

                $slots[] = [
                    'hora' => $hora,
                    'hora_inicio' => $hora,
                    'hora_fin' => $horaFin,
                    'disponible' => $disponible,
                    'cupo_restante' => $disponible ? ($restante ?? 0) : 0,
                ];
            }
        }

        return $slots;
    }

    public function slotDisponible(int $medicoId, string $fecha, string $hora, string $canal = 'web'): bool
    {
        $horaNormalizada = self::normalizarHora($hora);

        if ($horaNormalizada === null) {
            return false;
        }

        foreach ($this->slots($medicoId, $fecha, $canal) as $slot) {
            if ($slot['hora'] === $horaNormalizada) {
                return $slot['disponible'] === true;
            }
        }

        return false;
    }

    public static function normalizarFecha(string $fecha): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes) !== 1) {
            return null;
        }

        if (!checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            return null;
        }

        return $fecha;
    }

    public static function normalizarHora(string $hora): ?string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) === 1 ? $hora : null;
    }

    public static function sumarMinutos(string $hora, int $minutos): string
    {
        $inicio = self::aMinutos($hora) ?? 0;

        return self::desdeMinutos($inicio + $minutos);
    }

    /** @param array<string, mixed> $franja */
    private function cupoDelCanal(array $franja, string $canal): ?int
    {
        return match ($canal) {
            'web' => (int) $franja['cupos_web'],
            'kiosco' => (int) $franja['cupos_kiosco'],
            default => null,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $bloqueos
     */
    private function cruzaBloqueo(array $bloqueos, int $inicio, int $fin): bool
    {
        foreach ($bloqueos as $bloqueo) {
            if ($bloqueo['hora_inicio'] === null || $bloqueo['hora_fin'] === null) {
                return true;
            }

            $inicioBloqueo = self::aMinutos((string) $bloqueo['hora_inicio']);
            $finBloqueo = self::aMinutos((string) $bloqueo['hora_fin']);

            if ($inicioBloqueo === null || $finBloqueo === null) {
                continue;
            }

            if ($inicio < $finBloqueo && $fin > $inicioBloqueo) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $citas
     */
    private function cruzaCita(array $citas, int $inicio, int $fin): bool
    {
        foreach ($citas as $cita) {
            $inicioCita = self::aMinutos((string) $cita['hora_inicio']);
            $finCita = self::aMinutos((string) $cita['hora_fin']);

            if ($inicioCita === null || $finCita === null) {
                continue;
            }

            if ($inicio < $finCita && $fin > $inicioCita) {
                return true;
            }
        }

        return false;
    }

    private function esPasado(string $fecha, int $minutos, DateTimeImmutable $ahora): bool
    {
        $inicio = new DateTimeImmutable(
            $fecha . ' ' . self::desdeMinutos($minutos) . ':00',
            new DateTimeZone((string) config('app.timezone', 'America/La_Paz'))
        );

        return $inicio <= $ahora;
    }

    private static function aMinutos(string $hora): ?int
    {
        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $hora, $partes) !== 1) {
            return null;
        }

        return ((int) $partes[1] * 60) + (int) $partes[2];
    }

    private static function desdeMinutos(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60) % 24, $minutos % 60);
    }
}
