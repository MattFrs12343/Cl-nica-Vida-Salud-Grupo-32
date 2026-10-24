<?php
/**
 * Menú lateral por rol.
 *
 * @var array<string, mixed> $user
 * @var string $active
 */

$rol = (string) ($user['rol'] ?? '');
$activo = $active ?? '';

$menus = [
    'admin' => [
        'Operación' => [
            ['dashboard', 'Dashboard', '/admin', 'grid'],
        ],
        'Configuración' => [
            ['usuarios', 'Usuarios', '/admin/usuarios', 'users'],
            ['medicos', 'Médicos', '/admin/medicos', 'stethoscope'],
            ['especialidades', 'Especialidades', '/admin/especialidades', 'heart'],
            ['horarios', 'Horarios', '/admin/horarios', 'clock'],
        ],
        'Seguimiento' => [
            ['citas', 'Citas', '/admin/citas', 'calendar'],
            ['reportes', 'Reportes', '/admin/reportes', 'chart'],
            ['auditoria', 'Auditoría', '/admin/auditoria', 'shield'],
        ],
    ],
    'recepcion' => [
        'Día a día' => [
            ['dashboard', 'Dashboard', '/recepcion', 'grid'],
            ['pacientes', 'Pacientes', '/recepcion/pacientes', 'users'],
            ['citas', 'Citas', '/recepcion/citas', 'calendar'],
            ['agenda', 'Agenda', '/recepcion/agenda', 'clock'],
            ['reconsultas', 'Reconsultas', '/recepcion/reconsultas', 'refresh'],
        ],
        'Fichas y caja' => [
            ['fichas', 'Fichas', '/recepcion/fichas', 'ticket'],
            ['pagos', 'Pagos', '/recepcion/pagos', 'card'],
        ],
    ],
    'medico' => [
        'Mi día' => [
            ['dashboard', 'Dashboard', '/medico', 'grid'],
            ['agenda', 'Agenda', '/medico/agenda', 'calendar'],
            ['pacientes', 'Pacientes', '/medico/pacientes', 'users'],
        ],
        'Clínica' => [
            ['consultas', 'Consultas', '/medico/consultas', 'clipboard'],
            ['historia', 'Historia clínica', '/medico/historia', 'file'],
            ['pedidos', 'Recetas y órdenes', '/medico/pedidos', 'ticket'],
        ],
        'Cuenta' => [
            ['pagos', 'Pagos', '/medico/pagos', 'card'],
            ['qr', 'Mi QR', '/medico/qr', 'qr'],
        ],
    ],
    'paciente' => [
        'Mi salud' => [
            ['portal', 'Inicio', '/portal', 'grid'],
            ['citas', 'Mis citas', '/portal/mis-citas', 'calendar'],
            ['historial', 'Historial', '/portal/historial', 'file'],
            ['perfil', 'Mi perfil', '/portal/perfil', 'user'],
        ],
    ],
];

if ($rol === 'super_admin') {
    $rol = 'admin';
}

if ($rol === 'recepcionista' || $rol === 'recepcionista_medico') {
    $rol = 'recepcion';
}

$grupos = $menus[$rol] ?? [];
$esRecepcionMedico = (string) ($user['rol'] ?? '') === 'recepcionista_medico';
$medicoIds = array_values(array_filter(
    array_map('intval', (array) ($user['medico_ids'] ?? [])),
    static fn (int $id): bool => $id > 0
));
?>
<nav class="sidebar-nav" aria-label="Navegación del panel">
    <?php foreach ($grupos as $grupo => $items): ?>
        <div class="nav-group">
            <p class="nav-group-label"><?= e($grupo) ?></p>
            <?php foreach ($items as [$clave, $etiqueta, $ruta, $icono]): ?>
                <a class="nav-item<?= $clave === $activo ? ' is-active' : '' ?>"
                   href="<?= e(url($ruta)) ?>"
                   <?= $clave === $activo ? 'aria-current="page"' : '' ?>>
                    <span class="nav-icon" aria-hidden="true">
                        <svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg>
                    </span>
                    <span class="nav-text"><?= e($etiqueta) ?></span>
                    <?php if ($clave === $activo): ?>
                        <span class="nav-current" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($esRecepcionMedico): ?>
        <div class="sidebar-scope">
            <span class="sidebar-scope-icon" aria-hidden="true">
                <svg class="icon"><use href="#i-shield"></use></svg>
            </span>
            <div>
                <strong>Mis médicos</strong>
                <p><?= count($medicoIds) ?> médico<?= count($medicoIds) === 1 ? '' : 's' ?> asignado<?= count($medicoIds) === 1 ? '' : 's' ?></p>
            </div>
        </div>
    <?php endif; ?>
</nav>
