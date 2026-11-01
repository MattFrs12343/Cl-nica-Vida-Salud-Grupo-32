<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Reportes</h1>
        <p>Indicadores de asistencia, demanda por especialidad y desempeño por médico.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-reporte-export>
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-download"></use></svg></span>
            Exportar CSV
        </button>
    </div>
</header>

<section class="card report-filter">
    <div class="report-filter-row">
        <div class="field">
            <label for="reporte-desde">Desde</label>
            <input id="reporte-desde" type="date" data-reporte-desde>
        </div>
        <div class="field">
            <label for="reporte-hasta">Hasta</label>
            <input id="reporte-hasta" type="date" data-reporte-hasta>
        </div>
        <button class="btn btn-primary btn-sm" type="button" data-reporte-apply>
            <svg class="icon" aria-hidden="true"><use href="#i-filter"></use></svg>
            Aplicar
        </button>
        <p class="report-period" data-reporte-periodo>—</p>
    </div>
</section>

<section class="kpi-grid kpi-grid-5" aria-label="Indicadores del periodo">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span><span class="kpi-label">Citas</span></div>
        <strong class="kpi-value" data-reporte-kpi="total">—</strong>
        <span class="kpi-foot">En el periodo</span>
    </article>
    <article class="kpi-card tone-success">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-check-circle"></use></svg></span><span class="kpi-label">Atendidas</span></div>
        <strong class="kpi-value" data-reporte-kpi="atendidas">—</strong>
    </article>
    <article class="kpi-card tone-danger">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span><span class="kpi-label">Inasistencias</span></div>
        <strong class="kpi-value" data-reporte-kpi="inasistencias">—</strong>
    </article>
    <article class="kpi-card tone-info">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-activity"></use></svg></span><span class="kpi-label">Asistencia</span></div>
        <strong class="kpi-value" data-reporte-kpi="asistencia">—</strong>
        <span class="kpi-foot">Sobre citas cerradas</span>
    </article>
    <article class="kpi-card tone-accent">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span><span class="kpi-label">Pacientes nuevos</span></div>
        <strong class="kpi-value" data-reporte-kpi="pacientes">—</strong>
    </article>
</section>

<div class="report-grid">
    <section class="card" aria-labelledby="reporte-especialidades-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Demanda</span>
                <h2 id="reporte-especialidades-title">Citas por especialidad</h2>
            </div>
        </div>
        <div class="card-body" data-reporte-especialidades>
            <div class="table-loading">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="skeleton skeleton-row"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <section class="card" aria-labelledby="reporte-estados-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Cierre</span>
                <h2 id="reporte-estados-title">Citas por estado</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="mini-stat-grid" data-reporte-estados></div>
            <p class="report-note">El porcentaje de asistencia se calcula sobre citas atendidas e inasistencias del periodo.</p>
        </div>
    </section>
</div>

<section class="card" aria-labelledby="reporte-medicos-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Desempeño</span>
            <h2 id="reporte-medicos-title">Citas por médico</h2>
        </div>
        <span class="card-meta">Periodo seleccionado</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Médico</th>
                    <th>Citas</th>
                    <th>Atendidas</th>
                    <th>Inasistencias</th>
                    <th>Asistencia</th>
                </tr>
            </thead>
            <tbody data-reporte-medicos>
                <tr><td colspan="5"><div class="skeleton skeleton-row"></div></td></tr>
            </tbody>
        </table>
    </div>
</section>
