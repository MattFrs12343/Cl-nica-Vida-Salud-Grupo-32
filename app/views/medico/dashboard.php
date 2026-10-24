<?php
/** @var array<string, mixed> $user */
$nombre = (string) ($user['nombre'] ?? 'Doctor');
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Mi consulta</span>
        <h1><?= e($saludo) ?>, <em><?= e(explode(' ', trim($nombre))[0] ?? 'doctor') ?>.</em></h1>
        <p>Tu agenda de hoy y los pacientes que esperan por ti.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/medico/qr')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
            Mi QR
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/medico/agenda')) ?>">
            Ver agenda
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
        </a>
    </div>
</header>

<section class="kpi-grid kpi-grid-4" aria-label="Resumen de tu jornada" data-doctor-kpis>
    <?php foreach ([
        ['total', 'Citas de hoy', 'calendar', 'primary'],
        ['confirmadas', 'En espera', 'clock', 'info'],
        ['atendidas', 'Atendidas', 'check-circle', 'success'],
        ['inasistencias', 'Inasistencias', 'x-circle', 'danger'],
    ] as [$clave, $etiqueta, $icono, $tono]): ?>
        <article class="kpi-card tone-<?= e($tono) ?>">
            <div class="kpi-top">
                <span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg></span>
                <span class="kpi-label"><?= e($etiqueta) ?></span>
            </div>
            <strong class="kpi-value" data-doctor-kpi="<?= e($clave) ?>">
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
                <h2>Pacientes en tu agenda</h2>
            </div>
            <a class="text-link" href="<?= e(url('/medico/agenda')) ?>">
                Abrir agenda
                <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
            </a>
        </div>
        <div data-doctor-agenda>
            <div class="table-loading">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="skeleton skeleton-row"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Accesos</span>
                    <h2>Tu jornada</h2>
                </div>
            </div>
            <div class="stack-links">
                <a class="stack-link" href="<?= e(url('/medico/consultas')) ?>">
                    <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-clipboard"></use></svg></span>
                    <span><strong>Registrar consulta</strong><small>Signos vitales y diagnóstico</small></span>
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                </a>
                <a class="stack-link" href="<?= e(url('/medico/historia')) ?>">
                    <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
                    <span><strong>Historia clínica</strong><small>Evolución del paciente</small></span>
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                </a>
                <a class="stack-link" href="<?= e(url('/medico/pedidos')) ?>">
                    <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-ticket"></use></svg></span>
                    <span><strong>Recetas y órdenes</strong><small>Documentos de la consulta</small></span>
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                </a>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Kiosco</span>
            <h2>Tu QR de atención</h2>
            <p>Los pacientes te identifican en el kiosco con tu credencial. Mantén tu perfil actualizado.</p>
            <a class="btn btn-light btn-sm" href="<?= e(url('/medico/qr')) ?>">
                Ver mi credencial
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-qr"></use></svg></span>
            </a>
        </section>
    </aside>
</div>
