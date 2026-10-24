#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\HttpException;
use App\Models\EspecialidadModel;
use App\Models\MedicoModel;
use App\Services\AuthService;
use App\Services\DisponibilidadService;
use App\Services\ReservaWebService;

require dirname(__DIR__) . '/app/bootstrap.php';

$fallos = 0;

function comprobar(string $etiqueta, bool $condicion): void
{
    global $fallos;

    if ($condicion) {
        echo 'OK   ' . $etiqueta . PHP_EOL;

        return;
    }

    $fallos++;
    echo 'FALLO ' . $etiqueta . PHP_EOL;
}

function codigoDeError(callable $operacion): ?string
{
    try {
        $operacion();
    } catch (HttpException $exception) {
        return $exception->errorCode();
    }

    return null;
}

$pdo = DB::connection();
$especialidades = new EspecialidadModel();
$medicos = new MedicoModel();
$disponibilidad = new DisponibilidadService();
$reservas = new ReservaWebService();

$citaCreada = null;
$fichaCreada = null;

try {
    DB::clearContext();

    $activas = $especialidades->listActivas();
    comprobar('especialidades activas listadas', count($activas) >= 3);
    comprobar('destacadas primero', ($activas[0]['destacada'] ?? false) === true);

    $medicosPediatria = $medicos->listActivos(2);

    comprobar(
        'médicos filtrados por especialidad',
        $medicosPediatria !== [] && array_reduce(
            $medicosPediatria,
            static fn (bool $ok, array $medico): bool => $ok && in_array(
                2,
                array_column($medico['especialidades'], 'id'),
                true
            ),
            true
        )
    );
    comprobar('médicos incluyen especialidades', isset($medicosPediatria[0]['especialidades']));

    $fecha = date('Y-m-d', strtotime('next monday +1 week'));
    $slots = $disponibilidad->slots(3, $fecha, 'web');
    $disponibles = array_values(array_filter($slots, static fn (array $slot): bool => $slot['disponible'] === true));

    comprobar('disponibilidad devuelve bloques', count($slots) > 0);
    comprobar('disponibilidad tiene cupos libres', $disponibles !== []);
    comprobar(
        'bloque de 30 minutos',
        $slots === [] || (
            substr($slots[0]['hora'], 3, 2) === '00'
            && substr($slots[0]['hora_fin'], 3, 2) === '30'
        )
    );
    comprobar('sin slots en fecha pasada', $disponibilidad->slots(3, date('Y-m-d', strtotime('-1 day'))) === []);
    comprobar('sin slots con fecha inválida', $disponibilidad->slots(3, '2026-02-31') === []);

    if ($disponibles === []) {
        throw new RuntimeException('No hay slots disponibles para ejecutar la prueba de reserva.');
    }

    $slot = $disponibles[0];
    $auth = new AuthService();
    $paciente = $auth->login('12345678', 'Demo1234!');

    comprobar('login de paciente para reservar', ($paciente['rol'] ?? '') === 'paciente');
    comprobar('sesión incluye paciente_id', (int) ($paciente['paciente_id'] ?? 0) > 0);

    $resultado = $reservas->reservar([
        'medico_id' => 3,
        'especialidad_id' => 1,
        'fecha' => $fecha,
        'hora' => $slot['hora'],
        'motivo' => 'Prueba automatizada de reserva web',
    ], $paciente);

    $citaCreada = $resultado['cita'];
    $fichaCreada = $resultado['ficha'];

    comprobar('cita creada con código', str_starts_with((string) $citaCreada['codigo'], 'CM-'));
    comprobar('cita queda programada', $citaCreada['estado'] === 'programada');
    comprobar('origen de la cita es web', $citaCreada['origen'] === 'web');
    comprobar('ficha generada', str_starts_with((string) $fichaCreada['codigo'], 'FIC-'));
    comprobar('código de acceso de 4 dígitos', preg_match('/^\d{4}$/', (string) $fichaCreada['codigo_acceso']) === 1);

    DB::clearContext();

    $auditoriaUsuario = $pdo->query(
        "SELECT usuario_id FROM auditoria
         WHERE tabla_afectada = 'citas' AND accion = 'INSERT' AND registro_id = " . (int) $citaCreada['id'] . '
         ORDER BY id DESC LIMIT 1'
    )->fetchColumn();

    comprobar(
        'auditoría registra al paciente',
        $auditoriaUsuario !== false && (int) $auditoriaUsuario === (int) $paciente['id']
    );

    comprobar(
        'slot reservado deja de estar disponible',
        $disponibilidad->slotDisponible(3, $fecha, $slot['hora'], 'web') === false
    );

    comprobar(
        'segunda reserva del mismo slot es rechazada',
        codigoDeError(fn () => $reservas->reservar([
            'medico_id' => 3,
            'especialidad_id' => 1,
            'fecha' => $fecha,
            'hora' => $slot['hora'],
        ], $paciente)) === 'SLOT_TAKEN'
    );

    comprobar(
        'fecha pasada rechazada',
        codigoDeError(fn () => $reservas->reservar([
            'medico_id' => 3,
            'especialidad_id' => 1,
            'fecha' => date('Y-m-d', strtotime('-1 day')),
            'hora' => '09:00',
        ], $paciente)) === 'VALIDATION_ERROR'
    );

    comprobar(
        'paciente no elegible por edad',
        codigoDeError(fn () => $reservas->reservar([
            'medico_id' => 2,
            'especialidad_id' => 2,
            'fecha' => $fecha,
            'hora' => '09:00',
        ], $paciente)) === 'NOT_ELIGIBLE'
    );

    comprobar(
        'médico sin la especialidad rechazado',
        codigoDeError(fn () => $reservas->reservar([
            'medico_id' => 1,
            'especialidad_id' => 2,
            'fecha' => $fecha,
            'hora' => '09:00',
        ], $paciente)) === 'VALIDATION_ERROR'
    );

    comprobar(
        'reserva sin sesión rechazada',
        codigoDeError(fn () => $reservas->reservar([
            'medico_id' => 3,
            'especialidad_id' => 1,
            'fecha' => $fecha,
            'hora' => '09:00',
        ], null)) === 'UNAUTHENTICATED'
    );
} catch (Throwable $exception) {
    $fallos++;
    fwrite(STDERR, 'ERROR: ' . $exception->getMessage() . PHP_EOL);
} finally {
    DB::clearContext();

    if (is_array($citaCreada) && isset($citaCreada['id'])) {
        $citaId = (int) $citaCreada['id'];

        try {
            $fichaIds = $pdo->query(
                'SELECT id FROM fichas WHERE cita_id = ' . $citaId
            )->fetchAll(PDO::FETCH_COLUMN);

            $pdo->exec('DELETE FROM citas_estado_historial WHERE cita_id = ' . $citaId);
            $pdo->exec('DELETE FROM fichas WHERE cita_id = ' . $citaId);
            $pdo->exec('DELETE FROM citas WHERE id = ' . $citaId);

            $ids = array_merge([$citaId], array_map('intval', $fichaIds));

            if ($ids !== []) {
                $pdo->exec(
                    "DELETE FROM auditoria
                     WHERE tabla_afectada IN ('citas', 'fichas')
                       AND registro_id IN (" . implode(',', $ids) . ')'
                );
            }
        } catch (Throwable $exception) {
            fwrite(STDERR, 'ERROR limpiando reserva: ' . $exception->getMessage() . PHP_EOL);
        }
    }
}

echo PHP_EOL . ($fallos === 0 ? 'Todas las pruebas pasaron.' : $fallos . ' prueba(s) fallaron.') . PHP_EOL;
exit($fallos === 0 ? 0 : 1);
