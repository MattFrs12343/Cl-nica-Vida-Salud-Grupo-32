<?php
/**
 * Layout del panel de rol.
 *
 * @var string $title
 * @var string $active
 * @var array<int, array{label: string, url?: string}> $breadcrumbs
 * @var array<string, mixed> $user
 * @var string $csrfToken
 * @var array<int, array{type: string, message: string}> $flashes
 * @var string $content
 * @var string|null $script
 * @var string $subtitle
 */

$usuario = $user ?? auth();
$rol = (string) ($usuario['rol'] ?? '');
$nombre = (string) ($usuario['nombre'] ?? 'Usuario');
$partes = preg_split('/\s+/', trim($nombre)) ?: [];
$iniciales = '';

foreach (array_slice($partes, 0, 2) as $parte) {
    $iniciales .= mb_substr($parte, 0, 1);
}

$iniciales = mb_strtoupper($iniciales !== '' ? $iniciales : 'U');

$roles = [
    'super_admin' => 'Super administrador',
    'admin' => 'Administrador',
    'recepcionista' => 'Recepción',
    'recepcionista_medico' => 'Recepción médica',
    'medico' => 'Médico',
    'paciente' => 'Paciente',
];

$rolEtiqueta = $roles[$rol] ?? 'Usuario';
$hoy = [
    'domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado',
][(int) date('w')] . ', ' . date('j') . ' de ' . [
    1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
][(int) date('n')];

$panelData = [
    'role' => $rol,
    'userId' => (int) ($usuario['id'] ?? 0),
    'name' => $nombre,
    'medicoId' => isset($usuario['medico_id']) && $usuario['medico_id'] !== null
        ? (int) $usuario['medico_id']
        : null,
    'medicoIds' => array_values(array_map(
        'intval',
        array_filter((array) ($usuario['medico_ids'] ?? []))
    )),
    'pacienteId' => isset($usuario['paciente_id']) && $usuario['paciente_id'] !== null
        ? (int) $usuario['paciente_id']
        : null,
    'today' => date('Y-m-d'),
    'home' => role_home($rol),
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Clínica Vida Salud</title>
    <meta name="theme-color" content="#0B3D35">
    <meta name="csrf-token" content="<?= e($csrfToken ?? csrf_token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('landing/img/favicon.svg')) ?>">
    <link rel="preload" href="<?= e(asset('landing/fonts/bricolage-grotesque-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('landing/fonts/dm-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('landing/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('panel/css/panel.css')) ?>">
    <script>window.PANEL = <?= json_encode($panelData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body class="panel-body"
      data-page="<?= e($active) ?>"
      data-role="<?= e($rol) ?>"
      data-today="<?= e(date('Y-m-d')) ?>">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<?php require app_path('views/partials/panel/icons.php'); ?>

<div class="panel-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="sidebar" id="panel-sidebar" data-sidebar aria-label="Barra lateral">
        <div class="sidebar-head">
            <a class="panel-brand" href="<?= e(url($panelData['home'])) ?>" aria-label="Clínica Vida Salud, inicio del panel">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" focusable="false">
                        <rect width="40" height="40" rx="14" fill="currentColor"/>
                        <path d="M20 11.5v17M11.5 20h17" stroke="#0B3D35" stroke-width="4.2" stroke-linecap="round"/>
                        <circle cx="30.5" cy="9.5" r="4" fill="#D95E3C"/>
                    </svg>
                </span>
                <span class="brand-type"><strong>vida</strong><em>salud</em></span>
            </a>
            <button class="sidebar-close" type="button" data-sidebar-close aria-label="Cerrar menú">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </div>

        <div class="sidebar-role">
            <span class="sidebar-role-dot"></span>
            <span><?= e($rolEtiqueta) ?></span>
        </div>

        <?php require app_path('views/layouts/menu.php'); ?>

        <div class="sidebar-foot">
            <div class="sidebar-help">
                <span class="sidebar-help-icon" aria-hidden="true">
                    <svg class="icon"><use href="#i-sun"></use></svg>
                </span>
                <div>
                    <strong>Jornada de hoy</strong>
                    <p>Lun a vie · 08:00–18:00</p>
                </div>
            </div>
            <a class="sidebar-site-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                <span>Ver sitio público</span>
                <svg class="icon" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
            </a>
        </div>
    </aside>

    <div class="panel-main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="topbar-menu" type="button" data-sidebar-open aria-label="Abrir menú">
                    <svg class="icon" aria-hidden="true"><use href="#i-menu"></use></svg>
                </button>
                <nav class="breadcrumbs" aria-label="Migas de pan">
                    <?php foreach ($breadcrumbs as $indice => $miga): ?>
                        <?php if (!empty($miga['url'])): ?>
                            <a href="<?= e(url((string) $miga['url'])) ?>"><?= e($miga['label']) ?></a>
                        <?php else: ?>
                            <span aria-current="<?= $indice === array_key_last($breadcrumbs) ? 'page' : 'false' ?>"><?= e($miga['label']) ?></span>
                        <?php endif; ?>
                        <?php if ($indice < count($breadcrumbs) - 1): ?>
                            <svg class="icon breadcrumb-sep" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="topbar-right">
                <span class="topbar-date">
                    <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
                    <?= e(ucfirst($hoy)) ?>
                </span>

                <details class="user-menu">
                    <summary class="user-trigger" aria-label="Menú de usuario">
                        <span class="avatar avatar-sm"><?= e($iniciales) ?></span>
                        <span class="user-trigger-copy">
                            <strong><?= e($nombre) ?></strong>
                            <small><?= e($rolEtiqueta) ?></small>
                        </span>
                        <svg class="icon user-chevron" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </summary>
                    <div class="user-popover">
                        <div class="user-popover-head">
                            <span class="avatar"><?= e($iniciales) ?></span>
                            <div>
                                <strong><?= e($nombre) ?></strong>
                                <span><?= e($rolEtiqueta) ?></span>
                            </div>
                        </div>
                        <a class="popover-link" href="<?= e(url($panelData['home'])) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#i-grid"></use></svg>
                            Mi panel
                        </a>
                        <a class="popover-link" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                            <svg class="icon" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
                            Ir al sitio
                        </a>
                        <form method="post" action="<?= e(url('/auth/logout')) ?>" class="popover-logout">
                            <?= csrf_field() ?>
                            <button type="submit" class="popover-link is-danger">
                                <svg class="icon" aria-hidden="true"><use href="#i-logout"></use></svg>
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="panel-content" id="contenido">
            <?php if (($flashes ?? []) !== []): ?>
                <div class="panel-flashes" role="status" aria-live="polite">
                    <?php foreach ($flashes as $mensaje): ?>
                        <?php
                        $tipo = (string) ($mensaje['type'] ?? 'info');
                        $icono = match ($tipo) {
                            'success' => 'check-circle',
                            'error', 'danger' => 'x-circle',
                            'warning' => 'alert',
                            default => 'info',
                        };
                        ?>
                        <div class="panel-flash flash-<?= e($tipo) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#i-<?= e($icono) ?>"></use></svg>
                            <span><?= e($mensaje['message'] ?? '') ?></span>
                            <button type="button" class="flash-close" data-flash-close aria-label="Cerrar aviso">
                                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>

        <footer class="panel-foot">
            <span>Clínica Vida Salud · Panel interno</span>
            <span class="panel-foot-dot"></span>
            <span><?= e($rolEtiqueta) ?></span>
            <span class="panel-foot-spacer"></span>
            <span class="panel-foot-status"><span class="status-dot"></span> Sistema operativo</span>
        </footer>
    </div>
</div>

<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="false"></div>

<script type="module" src="<?= e(asset('panel/js/app.js')) ?>"></script>
<?php if (!empty($script)): ?>
    <script type="module" src="<?= e(asset((string) $script)) ?>"></script>
<?php endif; ?>
</body>
</html>
