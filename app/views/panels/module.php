<?php
/**
 * Módulo pendiente de integración de UI.
 *
 * @var array<string, mixed> $user
 * @var array{role: string, active: string, title: string, description: string, icon: string} $module
 * @var string $roleHome
 */
$homes = [
    'admin' => ['label' => 'Volver al dashboard', 'url' => '/admin'],
    'medico' => ['label' => 'Volver a mi consulta', 'url' => '/medico'],
    'paciente' => ['label' => 'Volver a mi espacio', 'url' => '/portal'],
];
$home = $homes[$module['role']] ?? $homes['admin'];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Módulo del panel</span>
        <h1><?= e($module['title']) ?></h1>
        <p><?= e($module['description']) ?></p>
    </div>
    <div class="page-actions">
        <span class="badge badge-warning"><svg class="icon" aria-hidden="true"><use href="#i-clock"></use></svg> En preparación</span>
        <a class="btn btn-ghost btn-sm" href="<?= e(url($home['url'])) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
            <?= e($home['label']) ?>
        </a>
    </div>
</header>

<section class="module-hero card">
    <div class="module-hero-icon" aria-hidden="true">
        <svg class="icon"><use href="#i-<?= e($module['icon']) ?>"></use></svg>
    </div>
    <div class="module-hero-copy">
        <span class="card-kicker">Siguiente entrega</span>
        <h2>Estamos conectando esta pantalla.</h2>
        <p>
            La lógica y los endpoints de este módulo ya forman parte del backend. La interfaz se integrará
            manteniendo el mismo lenguaje visual del panel y las mismas reglas de permisos.
        </p>
        <ul class="check-list">
            <li><svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg> Datos en vivo desde la API</li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg> Acciones con permisos por rol</li>
            <li><svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg> Estados de carga, vacío y error</li>
        </ul>
    </div>
</section>

<div class="quick-grid quick-grid-3">
    <a class="quick-card" href="<?= e(url($home['url'])) ?>">
        <span class="quick-icon tone-primary" aria-hidden="true"><svg class="icon"><use href="#i-grid"></use></svg></span>
        <div><strong><?= e($home['label']) ?></strong><p>Vuelve a los indicadores principales de tu rol.</p></div>
        <svg class="icon quick-arrow" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
    </a>
    <a class="quick-card" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
        <span class="quick-icon tone-success" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
        <div><strong>Sitio público</strong><p>Revisa la landing, la reserva y el kiosco.</p></div>
        <svg class="icon quick-arrow" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
    </a>
    <a class="quick-card" href="mailto:soporte@vidasalud.test">
        <span class="quick-icon tone-accent" aria-hidden="true"><svg class="icon"><use href="#i-mail"></use></svg></span>
        <div><strong>Reportar una necesidad</strong><p>Cuéntanos qué debería resolver este módulo.</p></div>
        <svg class="icon quick-arrow" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
    </a>
</div>
