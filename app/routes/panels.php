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
    $router->get('/usuarios', [PanelController::class, 'module']);
    $router->get('/medicos', [PanelController::class, 'module']);
    $router->get('/especialidades', [PanelController::class, 'module']);
    $router->get('/horarios', [PanelController::class, 'module']);
    $router->get('/citas', [PanelController::class, 'module']);
    $router->get('/reportes', [PanelController::class, 'module']);
    $router->get('/auditoria', [PanelController::class, 'module']);
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
    $router->get('/agenda', [PanelController::class, 'module']);
    $router->get('/pacientes', [PanelController::class, 'module']);
    $router->get('/consultas', [PanelController::class, 'module']);
    $router->get('/historia', [PanelController::class, 'module']);
    $router->get('/pedidos', [PanelController::class, 'module']);
    $router->get('/pagos', [PanelController::class, 'module']);
    $router->get('/qr', [PanelController::class, 'module']);
}, ['auth', 'role:medico']);

$router->group('/portal', static function ($router): void {
    $router->get('', [PanelController::class, 'portalDashboard']);
    $router->get('/mis-citas', [PanelController::class, 'module']);
    $router->get('/historial', [PanelController::class, 'module']);
    $router->get('/perfil', [PanelController::class, 'module']);
}, ['auth', 'role:paciente']);
