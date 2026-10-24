<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Agenda médica</span>
        <h1>Agenda</h1>
        <p>El día de cada médico, ordenado por horario y con las acciones a mano.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/recepcion/reconsultas')) ?>">
            Reconsultas
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/recepcion/citas?nuevo=1')) ?>">
            Nueva cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        </a>
    </div>
</header>

<section class="toolbar card agenda-toolbar">
    <div class="toolbar-filters">
        <label class="select-wrap select-wrap-wide">
            <span class="sr-only">Médico</span>
            <select data-agenda-medico>
                <option value="">Cargando médicos…</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <div class="date-stepper">
            <button class="icon-btn" type="button" data-agenda-prev aria-label="Día anterior">
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
            </button>
            <label class="input-inline" aria-label="Fecha de agenda">
                <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
                <input type="date" data-agenda-fecha value="<?= e(date('Y-m-d')) ?>">
            </label>
            <button class="icon-btn" type="button" data-agenda-next aria-label="Día siguiente">
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-agenda-today>Hoy</button>
    </div>
    <p class="toolbar-count" data-agenda-count>—</p>
</section>

<section class="kpi-grid kpi-grid-5" aria-label="Contadores de la agenda" data-agenda-kpis>
    <?php foreach ([
        ['total', 'Total', 'calendar', 'primary'],
        ['programadas', 'Programadas', 'clock', 'neutral'],
        ['confirmadas', 'En espera', 'check-circle', 'info'],
        ['atendidas', 'Atendidas', 'stethoscope', 'success'],
        ['inasistencias', 'Inasistencias', 'x-circle', 'danger'],
    ] as [$clave, $etiqueta, $icono, $tono]): ?>
        <article class="kpi-card tone-<?= e($tono) ?>">
            <div class="kpi-top">
                <span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg></span>
                <span class="kpi-label"><?= e($etiqueta) ?></span>
            </div>
            <strong class="kpi-value" data-agenda-kpi="<?= e($clave) ?>">
                <span class="skeleton skeleton-value"></span>
            </strong>
            <span class="kpi-foot" data-agenda-doctor>—</span>
        </article>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="agenda-lista-title">
        <div class="card-head">
            <div>
                <span class="card-kicker" data-agenda-date-label><?= e(date('d/m/Y')) ?></span>
                <h2 id="agenda-lista-title">Cronograma del día</h2>
            </div>
            <span class="badge badge-muted" data-agenda-status>Actualizando</span>
        </div>
        <div class="agenda-list" data-agenda-list>
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
                    <span class="card-kicker">Resumen</span>
                    <h2>Estado del día</h2>
                </div>
            </div>
            <div class="stack-links">
                <a class="stack-link" href="<?= e(url('/recepcion/citas?nuevo=1')) ?>">
                    <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
                    <span><strong>Agendar cita</strong><small>Buscar cupo para un paciente</small></span>
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                </a>
                <a class="stack-link" href="<?= e(url('/recepcion/fichas')) ?>">
                    <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-ticket"></use></svg></span>
                    <span><strong>Buscar ficha</strong><small>Reimprimir un ticket emitido</small></span>
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                </a>
                <a class="stack-link" href="<?= e(url('/recepcion/pacientes')) ?>">
                    <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span>
                    <span><strong>Directorio</strong><small>Datos e historial del paciente</small></span>
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                </a>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Buena práctica</span>
            <h2>Ficha antes de pasar</h2>
            <p>La ficha confirma la cita y da acceso al consultorio. Si el paciente llega sin ficha, búscala o genera una desde el kiosco.</p>
            <a class="btn btn-light btn-sm" href="<?= e(url('/recepcion/fichas')) ?>">
                Ir a fichas
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
            </a>
        </section>
    </aside>
</div>

<?php require app_path('views/recepcion/partials/modal-reconsulta.php'); ?>
