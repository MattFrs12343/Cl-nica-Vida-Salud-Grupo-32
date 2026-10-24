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
    /**
     * Módulos aún no implementados en la UI. Se registran para que el menú
     * no lleve a un 404 mientras se completan las fases de cada rol.
     *
     * @var array<string, array{role: string, active: string, title: string, description: string, icon: string}>
     */
    private const MODULES = [
        '/admin/usuarios' => [
            'role' => 'admin',
            'active' => 'usuarios',
            'title' => 'Usuarios',
            'description' => 'Altas, roles, bloqueos y desbloqueos de cuentas.',
            'icon' => 'users',
        ],
        '/admin/medicos' => [
            'role' => 'admin',
            'active' => 'medicos',
            'title' => 'Médicos',
            'description' => 'Perfiles profesionales, especialidades y recepcionistas asignados.',
            'icon' => 'stethoscope',
        ],
        '/admin/especialidades' => [
            'role' => 'admin',
            'active' => 'especialidades',
            'title' => 'Especialidades',
            'description' => 'Catálogo de especialidades, restricciones y destacados.',
            'icon' => 'heart',
        ],
        '/admin/horarios' => [
            'role' => 'admin',
            'active' => 'horarios',
            'title' => 'Horarios',
            'description' => 'Franjas semanales, cupos por canal y bloqueos de agenda.',
            'icon' => 'clock',
        ],
        '/admin/citas' => [
            'role' => 'admin',
            'active' => 'citas',
            'title' => 'Citas',
            'description' => 'Supervisión global de la agenda y los estados de atención.',
            'icon' => 'calendar',
        ],
        '/admin/reportes' => [
            'role' => 'admin',
            'active' => 'reportes',
            'title' => 'Reportes',
            'description' => 'Indicadores de demanda, asistencia y ocupación por médico.',
            'icon' => 'chart',
        ],
        '/admin/auditoria' => [
            'role' => 'admin',
            'active' => 'auditoria',
            'title' => 'Auditoría',
            'description' => 'Bitácora de cambios sensibles del sistema.',
            'icon' => 'shield',
        ],
        '/medico/agenda' => [
            'role' => 'medico',
            'active' => 'agenda',
            'title' => 'Mi agenda',
            'description' => 'Consulta diaria, semana y bloqueos de atención.',
            'icon' => 'calendar',
        ],
        '/medico/pacientes' => [
            'role' => 'medico',
            'active' => 'pacientes',
            'title' => 'Pacientes',
            'description' => 'Pacientes atendidos y búsqueda por documento.',
            'icon' => 'users',
        ],
        '/medico/consultas' => [
            'role' => 'medico',
            'active' => 'consultas',
            'title' => 'Consultas',
            'description' => 'Registro clínico de la atención y signos vitales.',
            'icon' => 'clipboard',
        ],
        '/medico/historia' => [
            'role' => 'medico',
            'active' => 'historia',
            'title' => 'Historia clínica',
            'description' => 'Evolución cronológica y antecedentes del paciente.',
            'icon' => 'file',
        ],
        '/medico/pedidos' => [
            'role' => 'medico',
            'active' => 'pedidos',
            'title' => 'Recetas y órdenes',
            'description' => 'Recetas, documentos y órdenes de estudio.',
            'icon' => 'ticket',
        ],
        '/medico/pagos' => [
            'role' => 'medico',
            'active' => 'pagos',
            'title' => 'Pagos',
            'description' => 'Movimientos asociados a tu atención.',
            'icon' => 'card',
        ],
        '/medico/qr' => [
            'role' => 'medico',
            'active' => 'qr',
            'title' => 'Mi QR',
            'description' => 'Credencial de acceso para el kiosco de fichas.',
            'icon' => 'qr',
        ],
        '/portal/mis-citas' => [
            'role' => 'paciente',
            'active' => 'citas',
            'title' => 'Mis citas',
            'description' => 'Próximas citas, reprogramaciones y cancelaciones.',
            'icon' => 'calendar',
        ],
        '/portal/historial' => [
            'role' => 'paciente',
            'active' => 'historial',
            'title' => 'Mi historial',
            'description' => 'Atenciones, indicaciones y recetas.',
            'icon' => 'file',
        ],
        '/portal/perfil' => [
            'role' => 'paciente',
            'active' => 'perfil',
            'title' => 'Mi perfil',
            'description' => 'Datos de contacto, médicos y emergencia.',
            'icon' => 'user',
        ],
    ];

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
                'script' => 'panel/js/recepcion.js',
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
            ]
        );
    }

    /**
     * Renderiza un módulo marcado como pendiente. El menú se mantiene vivo
     * y el usuario siempre tiene un camino de vuelta a su panel.
     */
    public function module(Request $request): Response
    {
        $path = rtrim($request->path(), '/') ?: '/';
        $module = self::MODULES[$path] ?? null;

        if ($module === null) {
            abort(404, 'El módulo solicitado no existe.');
        }

        $roleHome = role_home((string) ($module['role'] === 'admin' ? 'admin' : $module['role']));

        return $this->renderPanel(
            'panels/module',
            $module['title'],
            $module['active'],
            [['label' => $module['role'] === 'paciente' ? 'Mi cuenta' : ucfirst($module['role'])], ['label' => $module['title']]],
            [
                'subtitle' => $module['description'],
                'module' => $module,
                'roleHome' => $roleHome,
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
