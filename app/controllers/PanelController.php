<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Renderiza los paneles de rol. No contiene reglas de negocio ni SQL:
 * las vistas consumen las APIs documentadas en docs/api/contract.md.
 */
final class PanelController extends Controller
{
    public function adminDashboard(Request $request): Response
    {
        return $this->renderPanel(
            'admin/dashboard',
            'Dashboard',
            'dashboard',
            [['label' => 'Administración'], ['label' => 'Dashboard']],
            [
                'subtitle' => 'Una lectura rápida de la operación de hoy.',
                'script' => 'panel/js/admin.js',
            ]
        );
    }

    public function adminUsuarios(Request $request): Response
    {
        return $this->renderPanel(
            'admin/usuarios/index',
            'Usuarios',
            'usuarios',
            [['label' => 'Administración'], ['label' => 'Usuarios']],
            ['subtitle' => 'Altas, roles, bloqueos y desbloqueos de cuentas.', 'script' => 'panel/js/admin.js']
        );
    }

    public function adminMedicos(Request $request): Response
    {
        return $this->renderPanel(
            'admin/medicos/index',
            'Médicos',
            'medicos',
            [['label' => 'Administración'], ['label' => 'Médicos']],
            ['subtitle' => 'Perfiles profesionales, especialidades y recepcionistas asignados.', 'script' => 'panel/js/admin.js']
        );
    }

    public function adminEspecialidades(Request $request): Response
    {
        return $this->renderPanel(
            'admin/especialidades/index',
            'Especialidades',
            'especialidades',
            [['label' => 'Administración'], ['label' => 'Especialidades']],
            ['subtitle' => 'Catálogo de especialidades, restricciones y destacados.', 'script' => 'panel/js/admin.js']
        );
    }

    public function adminHorarios(Request $request): Response
    {
        return $this->renderPanel(
            'admin/horarios/index',
            'Horarios',
            'horarios',
            [['label' => 'Administración'], ['label' => 'Horarios']],
            ['subtitle' => 'Franjas semanales, cupos por canal y bloqueos de agenda.', 'script' => 'panel/js/admin.js']
        );
    }

    public function adminCitas(Request $request): Response
    {
        return $this->renderPanel(
            'admin/citas/index',
            'Citas',
            'citas',
            [['label' => 'Administración'], ['label' => 'Citas']],
            ['subtitle' => 'Supervisión global de la agenda y los estados de atención.', 'script' => 'panel/js/admin.js']
        );
    }

    public function adminReportes(Request $request): Response
    {
        return $this->renderPanel(
            'admin/reportes/index',
            'Reportes',
            'reportes',
            [['label' => 'Administración'], ['label' => 'Reportes']],
            ['subtitle' => 'Indicadores de demanda, asistencia y ocupación por médico.', 'script' => 'panel/js/admin.js']
        );
    }

    public function adminAuditoria(Request $request): Response
    {
        return $this->renderPanel(
            'admin/auditoria/index',
            'Auditoría',
            'auditoria',
            [['label' => 'Administración'], ['label' => 'Auditoría']],
            ['subtitle' => 'Bitácora de cambios sensibles del sistema.', 'script' => 'panel/js/admin.js']
        );
    }

    public function recepcionDashboard(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/dashboard',
            'Recepción',
            'dashboard',
            [['label' => 'Recepción'], ['label' => 'Dashboard']],
            [
                'subtitle' => 'Lo que está pasando hoy en la clínica, en una sola mirada.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function recepcionPacientes(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/pacientes/index',
            'Pacientes',
            'pacientes',
            [['label' => 'Recepción'], ['label' => 'Pacientes']],
            [
                'subtitle' => 'Busca, registra y actualiza la información de tus pacientes.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function recepcionCitas(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/citas/index',
            'Citas',
            'citas',
            [['label' => 'Recepción'], ['label' => 'Citas']],
            [
                'subtitle' => 'Agenda, reprograma y acompaña cada cita hasta su atención.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function recepcionAgenda(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/agenda',
            'Agenda',
            'agenda',
            [['label' => 'Recepción'], ['label' => 'Agenda']],
            [
                'subtitle' => 'El día del médico, ordenado por horario y estado.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function recepcionReconsultas(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/reconsultas',
            'Reconsultas',
            'reconsultas',
            [['label' => 'Recepción'], ['label' => 'Reconsultas']],
            [
                'subtitle' => 'Reprograma una atención sin perder la continuidad clínica.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function recepcionFichas(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/fichas',
            'Fichas',
            'fichas',
            [['label' => 'Recepción'], ['label' => 'Fichas']],
            [
                'subtitle' => 'Busca una ficha emitida y vuelve a imprimirla cuando haga falta.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function recepcionPagos(Request $request): Response
    {
        return $this->renderPanel(
            'recepcion/pagos',
            'Pagos',
            'pagos',
            [['label' => 'Recepción'], ['label' => 'Pagos']],
            [
                'subtitle' => 'Registra el cobro de la consulta y revisa los movimientos del día.',
                'script' => 'panel/js/recepcion.js',
            ]
        );
    }

    public function medicoDashboard(Request $request): Response
    {
        return $this->renderPanel(
            'medico/dashboard',
            'Mi consulta',
            'dashboard',
            [['label' => 'Médico'], ['label' => 'Dashboard']],
            [
                'subtitle' => 'Tu jornada clínica, sin ruido.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoAgenda(Request $request): Response
    {
        return $this->renderPanel(
            'medico/agenda/index',
            'Mi agenda',
            'agenda',
            [['label' => 'Médico'], ['label' => 'Agenda']],
            [
                'subtitle' => 'Consulta diaria, semana y bloqueos de atención.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoPacientes(Request $request): Response
    {
        return $this->renderPanel(
            'medico/pacientes/index',
            'Pacientes',
            'pacientes',
            [['label' => 'Médico'], ['label' => 'Pacientes']],
            [
                'subtitle' => 'Pacientes atendidos y búsqueda por documento.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoConsultas(Request $request): Response
    {
        return $this->renderPanel(
            'medico/consultas/index',
            'Consultas',
            'consultas',
            [['label' => 'Médico'], ['label' => 'Consultas']],
            [
                'subtitle' => 'Registro clínico de la atención y signos vitales.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoHistoria(Request $request): Response
    {
        return $this->renderPanel(
            'medico/historia/index',
            'Historia clínica',
            'historia',
            [['label' => 'Médico'], ['label' => 'Historia clínica']],
            [
                'subtitle' => 'Evolución cronológica y antecedentes del paciente.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoPedidos(Request $request): Response
    {
        return $this->renderPanel(
            'medico/pedidos/index',
            'Recetas y órdenes',
            'pedidos',
            [['label' => 'Médico'], ['label' => 'Recetas y órdenes']],
            [
                'subtitle' => 'Recetas, documentos y órdenes de estudio.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoPagos(Request $request): Response
    {
        return $this->renderPanel(
            'medico/pagos/index',
            'Pagos',
            'pagos',
            [['label' => 'Médico'], ['label' => 'Pagos']],
            [
                'subtitle' => 'Movimientos asociados a tu atención.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function medicoQr(Request $request): Response
    {
        return $this->renderPanel(
            'medico/qr/index',
            'Mi QR',
            'qr',
            [['label' => 'Médico'], ['label' => 'Mi QR']],
            [
                'subtitle' => 'Credencial de acceso para el kiosco de fichas.',
                'script' => 'panel/js/medico.js',
            ]
        );
    }

    public function portalDashboard(Request $request): Response
    {
        return $this->renderPanel(
            'paciente/dashboard',
            'Mi espacio',
            'portal',
            [['label' => 'Mi cuenta'], ['label' => 'Inicio']],
            [
                'subtitle' => 'Tus citas y tu información de salud, en un solo lugar.',
                'script' => 'panel/js/paciente.js',
            ]
        );
    }

    public function portalMisCitas(Request $request): Response
    {
        return $this->renderPanel(
            'paciente/mis-citas',
            'Mis citas',
            'citas',
            [['label' => 'Mi cuenta'], ['label' => 'Mis citas']],
            [
                'subtitle' => 'Próximas atenciones, estado de cada reserva y cancelaciones.',
                'script' => 'panel/js/paciente.js',
            ]
        );
    }

    public function portalHistorial(Request $request): Response
    {
        return $this->renderPanel(
            'paciente/historial',
            'Mi historial',
            'historial',
            [['label' => 'Mi cuenta'], ['label' => 'Mi historial']],
            [
                'subtitle' => 'Atenciones, indicaciones y signos vitales registrados.',
                'script' => 'panel/js/paciente.js',
            ]
        );
    }

    public function portalPerfil(Request $request): Response
    {
        return $this->renderPanel(
            'paciente/perfil',
            'Mi perfil',
            'perfil',
            [['label' => 'Mi cuenta'], ['label' => 'Mi perfil']],
            [
                'subtitle' => 'Datos de contacto, información clínica y emergencia.',
                'script' => 'panel/js/paciente.js',
            ]
        );
    }

    /**
     * @param array<int, array{label: string, url?: string}> $breadcrumbs
     * @param array<string, mixed> $extra
     */
    private function renderPanel(
        string $view,
        string $title,
        string $active,
        array $breadcrumbs,
        array $extra = []
    ): Response {
        $user = auth();

        if ($user === null) {
            return redirect('/login');
        }

        $data = array_merge([
            'title' => $title,
            'active' => $active,
            'breadcrumbs' => $breadcrumbs,
            'user' => $user,
            'csrfToken' => csrf_token(),
            'flashes' => flashes(),
            'script' => null,
            'subtitle' => '',
        ], $extra);

        return $this->view($view, $data, 'layouts/gentelella');
    }
}
