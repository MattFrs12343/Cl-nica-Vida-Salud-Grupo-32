<?php
/** @var array<string, mixed> $user */
$nombre = (string) ($user['nombre'] ?? 'Administrador');
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1><?= e($saludo) ?>, <em><?= e(explode(' ', trim($nombre))[0] ?? 'equipo') ?>.</em></h1>
        <p>La operación de la clínica, resumida en los números que importan hoy.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/reportes')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-chart"></use></svg>
            Ver reportes
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/admin/auditoria')) ?>">
            Auditoría
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-shield"></use></svg></span>
        </a>
    </div>
</header>

<div class="admin-dashboard" data-admin-dashboard>
    <section class="kpi-grid kpi-grid-5" aria-label="Indicadores de hoy" data-admin-kpis>
        <?php foreach ([
            ['citas_hoy', 'Citas de hoy', 'calendar', 'primary'],
            ['programadas', 'Programadas', 'clock', 'neutral'],
            ['confirmadas', 'Confirmadas', 'check-circle', 'info'],
            ['atendidas', 'Atendidas', 'stethoscope', 'success'],
            ['inasistencias', 'Inasistencias', 'x-circle', 'danger'],
        ] as [$clave, $etiqueta, $icono, $tono]): ?>
            <article class="kpi-card tone-<?= e($tono) ?>">
                <div class="kpi-top">
                    <span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg></span>
                    <span class="kpi-label"><?= e($etiqueta) ?></span>
                </div>
                <strong class="kpi-value" data-admin-kpi="<?= e($clave) ?>">
                    <span class="skeleton skeleton-value"></span>
                </strong>
                <span class="kpi-foot">Hoy · <?= e(date('d/m/Y')) ?></span>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="dashboard-grid">
        <section class="card card-span-2">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Hoy</span>
                    <h2>Agenda del día</h2>
                </div>
                <a class="text-link" href="<?= e(url('/admin/citas')) ?>">
                    Ver todas
                    <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
                </a>
            </div>
            <div class="table-wrap" data-admin-agenda>
                <div class="table-loading">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                        <div class="skeleton skeleton-row"></div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <aside class="dashboard-side">
            <section class="card">
                <div class="card-head">
                    <div>
                        <span class="card-kicker">Origen</span>
                        <h2>Cómo llegan las citas</h2>
                    </div>
                </div>
                <div class="origin-chart" data-admin-origin>
                    <div class="origin-donut" data-origin-donut aria-hidden="true"></div>
                    <div class="origin-legend">
                        <div class="origin-item"><span class="origin-dot web"></span><span>Web</span><strong data-origin="web">—</strong></div>
                        <div class="origin-item"><span class="origin-dot kiosco"></span><span>Kiosco</span><strong data-origin="kiosco">—</strong></div>
                        <div class="origin-item"><span class="origin-dot recepcion"></span><span>Recepción</span><strong data-origin="recepcion">—</strong></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-head">
                    <div>
                        <span class="card-kicker">Comunidad</span>
                        <h2>Base registrada</h2>
                    </div>
                </div>
                <div class="mini-stats">
                    <div class="mini-stat">
                        <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span>
                        <div><strong data-admin-total="pacientes">—</strong><span>Pacientes</span></div>
                    </div>
                    <div class="mini-stat">
                        <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-stethoscope"></use></svg></span>
                        <div><strong data-admin-total="medicos">—</strong><span>Médicos activos</span></div>
                    </div>
                </div>
            </section>

            <section class="card card-accent">
                <span class="card-kicker">Siguiente paso</span>
                <h2>Completa la operación diaria</h2>
                <p>Los módulos de usuarios, horarios y reportes ya tienen API. La interfaz se integrará de forma gradual.</p>
                <a class="btn btn-light btn-sm" href="<?= e(url('/admin/reportes')) ?>">
                    Explorar reportes
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
                </a>
            </section>
        </aside>
    </div>
</div>
