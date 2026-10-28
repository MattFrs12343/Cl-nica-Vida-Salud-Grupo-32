<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Mi agenda</h1>
        <p>El día de hoy, hora por hora. Marca la asistencia a medida que atiendes.</p>
    </div>
    <div class="page-actions">
        <div class="segmented" role="tablist" aria-label="Vista de agenda">
            <button class="segmented-item is-active" type="button" role="tab" aria-selected="true" data-agenda-mode="day">Día</button>
            <button class="segmented-item" type="button" role="tab" aria-selected="false" data-agenda-mode="week">Semana</button>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-agenda-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="date-stepper">
        <button class="date-stepper-btn" type="button" aria-label="Período anterior" data-agenda-prev>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
        </button>
        <input type="date" value="<?= e(date('Y-m-d')) ?>" aria-label="Fecha de la agenda" data-agenda-fecha>
        <button class="date-stepper-btn" type="button" aria-label="Período siguiente" data-agenda-next>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
        </button>
        <button class="btn btn-ghost btn-sm" type="button" data-agenda-today>Hoy</button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Estado</span>
            <select data-agenda-estado>
                <option value="">Todos los estados</option>
                <option value="programada">Programadas</option>
                <option value="confirmada">Confirmadas</option>
                <option value="atendida">Atendidas</option>
                <option value="cancelada">Canceladas</option>
                <option value="inasistencia">Inasistencias</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
    </div>
    <p class="toolbar-count" data-agenda-status>Agenda actualizada</p>
</section>

<section class="kpi-grid kpi-grid-5" aria-label="Resumen de la agenda" data-agenda-kpis>
    <?php foreach ([
        ['total', 'Total', 'calendar', 'primary'],
        ['programadas', 'Programadas', 'clock', 'info'],
        ['confirmadas', 'Confirmadas', 'check-circle', 'success'],
        ['atendidas', 'Atendidas', 'stethoscope', 'success'],
        ['inasistencias', 'Inasistencias', 'x-circle', 'danger'],
    ] as [$clave, $etiqueta, $icono, $tono]): ?>
        <article class="kpi-card tone-<?= e($tono) ?>">
            <div class="kpi-top">
                <span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg></span>
                <span class="kpi-label"><?= e($etiqueta) ?></span>
            </div>
            <strong class="kpi-value" data-agenda-kpi="<?= e($clave) ?>">0</strong>
        </article>
    <?php endforeach; ?>
</section>

<section class="card" aria-labelledby="agenda-title">
    <div class="card-head">
        <div>
            <span class="card-kicker" data-agenda-date-label>Hoy</span>
            <h2 id="agenda-title">Agenda del día</h2>
        </div>
        <div class="card-head-tools">
            <span class="badge badge-muted" data-agenda-count>0 citas</span>
            <span class="badge badge-muted" data-agenda-doctor>Mi agenda</span>
        </div>
    </div>
    <div data-agenda-list>
        <div class="table-loading">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
</section>
