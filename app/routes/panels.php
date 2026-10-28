<?php

declare(strict_types=1);

/**
 * Rutas HTML de los paneles de rol.
 *
 * Contrato: docs/api/view-contract.md.
 * Las vistas consumen las APIs de docs/api/contract.md; este archivo solo
 * conecta rutas con el controlador que renderiza el shell.
 */

use App\Controllers\PanelController;

$router->group('/admin', static function ($router): void {
    $router->get('', [PanelController::class, 'adminDashboard']);
    $router->get('/usuarios', [PanelController::class, 'adminUsuarios']);
    $router->get('/medicos', [PanelController::class, 'adminMedicos']);
    $router->get('/especialidades', [PanelController::class, 'adminEspecialidades']);
    $router->get('/horarios', [PanelController::class, 'adminHorarios']);
    $router->get('/citas', [PanelController::class, 'adminCitas']);
    $router->get('/reportes', [PanelController::class, 'adminReportes']);
    $router->get('/auditoria', [PanelController::class, 'adminAuditoria']);
}, ['auth', 'role:admin,super_admin']);

$router->group('/recepcion', static function ($router): void {
    $router->get('', [PanelController::class, 'recepcionDashboard']);
    $router->get('/pacientes', [PanelController::class, 'recepcionPacientes']);
    $router->get('/citas', [PanelController::class, 'recepcionCitas']);
    $router->get('/agenda', [PanelController::class, 'recepcionAgenda']);
    $router->get('/reconsultas', [PanelController::class, 'recepcionReconsultas']);
    $router->get('/fichas', [PanelController::class, 'recepcionFichas']);
    $router->get('/pagos', [PanelController::class, 'recepcionPagos']);
}, ['auth', 'role:recepcionista,recepcionista_medico']);

$router->group('/medico', static function ($router): void {
    $router->get('', [PanelController::class, 'medicoDashboard']);
    $router->get('/agenda', [PanelController::class, 'medicoAgenda']);
    $router->get('/pacientes', [PanelController::class, 'medicoPacientes']);
    $router->get('/consultas', [PanelController::class, 'medicoConsultas']);
    $router->get('/historia', [PanelController::class, 'medicoHistoria']);
    $router->get('/pedidos', [PanelController::class, 'medicoPedidos']);
    $router->get('/pagos', [PanelController::class, 'medicoPagos']);
    $router->get('/qr', [PanelController::class, 'medicoQr']);
}, ['auth', 'role:medico']);

$router->group('/portal', static function ($router): void {
    $router->get('', [PanelController::class, 'portalDashboard']);
    $router->get('/mis-citas', [PanelController::class, 'portalMisCitas']);
    $router->get('/historial', [PanelController::class, 'portalHistorial']);
    $router->get('/perfil', [PanelController::class, 'portalPerfil']);
}, ['auth', 'role:paciente']);
