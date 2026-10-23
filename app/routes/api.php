<?php

declare(strict_types=1);

use App\Controllers\Api\AgendaApiController;
use App\Controllers\Api\AuditoriaApiController;
use App\Controllers\Api\CitaApiController;
use App\Controllers\Api\DisponibilidadApiController;
use App\Controllers\Api\EspecialidadApiController;
use App\Controllers\Api\HorarioApiController;
use App\Controllers\Api\KioscoApiController;
use App\Controllers\Api\MedicoApiController;
use App\Controllers\Api\PublicApiController;
use App\Controllers\Api\ReporteApiController;
use App\Controllers\Api\UsuarioApiController;
use App\Core\Response;

$router->get('/api/health', static function (): Response {
    return json_ok([
        'status' => 'ok',
        'time' => date(DATE_ATOM),
    ]);
});

$router->get('/api/csrf', static function (): Response {
    return json_ok([
        'token' => csrf_token(),
    ]);
});

$router->get('/api/me', static function (): Response {
    $user = auth();

    if ($user === null) {
        abort(401, 'No autenticado.');
    }

    return json_ok($user);
});

// Landing / reserva web (M-15, M-16)
$router->get('/api/public/especialidades', [PublicApiController::class, 'especialidades']);
$router->get('/api/public/medicos', [PublicApiController::class, 'medicos']);
$router->get('/api/public/disponibilidad', [PublicApiController::class, 'disponibilidad']);
$router->post('/api/public/reservar', [PublicApiController::class, 'reservar'], ['role:paciente']);

// Kiosco (M-23). Las rutas protegidas usan el middleware `kiosk`.
$router->post('/api/kiosco/sesion', [KioscoApiController::class, 'sesion']);

// APIs administrativas (M-20). Las escrituras exigen CSRF.
$router->group('/api', static function ($router): void {
    // Usuarios
    $router->get('/usuarios', [UsuarioApiController::class, 'index'], ['role:admin,super_admin']);
    $router->post('/usuarios', [UsuarioApiController::class, 'store'], ['role:admin,super_admin', 'csrf']);
    $router->get('/usuarios/{id}', [UsuarioApiController::class, 'show'], ['role:admin,super_admin']);
    $router->put('/usuarios/{id}', [UsuarioApiController::class, 'update'], ['role:admin,super_admin', 'csrf']);
    $router->patch('/usuarios/{id}/estado', [UsuarioApiController::class, 'estado'], ['role:admin,super_admin', 'csrf']);
    $router->patch('/usuarios/{id}/desbloquear', [UsuarioApiController::class, 'desbloquear'], ['role:admin,super_admin', 'csrf']);

    // Médicos
    $router->get('/medicos', [MedicoApiController::class, 'index'], ['role:admin,super_admin,recepcionista']);
    $router->post('/medicos', [MedicoApiController::class, 'store'], ['role:admin,super_admin', 'csrf']);
    $router->get('/medicos/{id}', [MedicoApiController::class, 'show'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);
    $router->put('/medicos/{id}', [MedicoApiController::class, 'update'], ['role:admin,super_admin,medico', 'csrf']);
    $router->patch('/medicos/{id}/estado', [MedicoApiController::class, 'estado'], ['role:admin,super_admin', 'csrf']);
    $router->put('/medicos/{id}/especialidades', [MedicoApiController::class, 'especialidades'], ['role:admin,super_admin', 'csrf']);
    $router->post('/medicos/{id}/qr', [MedicoApiController::class, 'qr'], ['role:medico', 'csrf']);
    $router->post('/medicos/{id}/recepcionistas', [MedicoApiController::class, 'recepcionista'], ['role:admin,super_admin', 'csrf']);

    // Especialidades
    $router->get('/especialidades', [EspecialidadApiController::class, 'index'], ['role:admin,super_admin,recepcionista']);
    $router->post('/especialidades', [EspecialidadApiController::class, 'store'], ['role:admin,super_admin', 'csrf']);
    $router->get('/especialidades/{id}', [EspecialidadApiController::class, 'show'], ['role:admin,super_admin']);
    $router->put('/especialidades/{id}', [EspecialidadApiController::class, 'update'], ['role:admin,super_admin', 'csrf']);
    $router->patch('/especialidades/{id}/estado', [EspecialidadApiController::class, 'estado'], ['role:admin,super_admin', 'csrf']);

    // Horarios, cupos y bloqueos
    $router->get('/medicos/{medico}/horarios', [HorarioApiController::class, 'index'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);
    $router->post('/medicos/{medico}/horarios', [HorarioApiController::class, 'store'], ['role:admin,super_admin', 'csrf']);
    $router->put('/horarios/{id}', [HorarioApiController::class, 'update'], ['role:admin,super_admin', 'csrf']);
    $router->delete('/horarios/{id}', [HorarioApiController::class, 'destroy'], ['role:admin,super_admin', 'csrf']);
    $router->get('/medicos/{medico}/bloqueos', [HorarioApiController::class, 'bloqueos'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);
    $router->post('/medicos/{medico}/bloqueos', [HorarioApiController::class, 'storeBloqueo'], ['role:admin,super_admin', 'csrf']);

    // Disponibilidad interna
    $router->get('/disponibilidad', [DisponibilidadApiController::class, 'show'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);

    // Citas, agenda, reportes y auditoría (M-21)
    $router->get('/citas', [CitaApiController::class, 'index'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);
    $router->get('/citas/{id}/historial-estados', [CitaApiController::class, 'historial'], ['role:admin,super_admin']);
    $router->get('/citas/{id}', [CitaApiController::class, 'show'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);

    $router->get('/medicos/{medico}/agenda', [AgendaApiController::class, 'medico'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);
    $router->get('/medicos/{medico}/agenda/dia', [AgendaApiController::class, 'dia'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico']);
    $router->get('/pacientes/{paciente}/historial', [AgendaApiController::class, 'historialPaciente'], ['role:admin,super_admin,recepcionista,recepcionista_medico,medico,paciente']);

    $router->get('/reportes/citas', [ReporteApiController::class, 'citas'], ['role:admin,super_admin']);
    $router->get('/reportes/citas-por-medico', [ReporteApiController::class, 'citasPorMedico'], ['role:admin,super_admin']);
    $router->get('/reportes/citas-por-especialidad', [ReporteApiController::class, 'citasPorEspecialidad'], ['role:admin,super_admin']);
    $router->get('/reportes/citas-por-estado', [ReporteApiController::class, 'citasPorEstado'], ['role:admin,super_admin']);
    $router->get('/reportes/pacientes', [ReporteApiController::class, 'pacientes'], ['role:admin,super_admin']);
    $router->get('/reportes/asistencia', [ReporteApiController::class, 'asistencia'], ['role:admin,super_admin']);
    $router->get('/reportes/agenda-diaria', [ReporteApiController::class, 'agendaDiaria'], ['role:admin,super_admin']);
    $router->get('/dashboard/admin', [ReporteApiController::class, 'dashboardAdmin'], ['role:admin,super_admin']);

    $router->get('/auditoria', [AuditoriaApiController::class, 'index'], ['role:admin,super_admin']);
    $router->get('/auditoria/{id}', [AuditoriaApiController::class, 'show'], ['role:admin,super_admin']);
}, ['auth']);

