<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Citas</h1>
        <p>Supervisión global de la agenda: todos los médicos, todos los canales de origen.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/reportes')) ?>">
            Ver reportes
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-chart"></use></svg></span>
        </a>
    </div>
</header>

<section class="kpi-grid kpi-grid-5" aria-label="Resumen de citas">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span><span class="kpi-label">Citas</span></div>
        <strong class="kpi-value" data-cita-kpi="total">—</strong>
        <span class="kpi-foot" data-cita-day-label>—</span>
    </article>
    <article class="kpi-card tone-neutral">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-clock"></use></svg></span><span class="kpi-label">Programadas</span></div>
        <strong class="kpi-value" data-cita-kpi="programadas">—</strong>
    </article>
    <article class="kpi-card tone-info">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-check-circle"></use></svg></span><span class="kpi-label">Confirmadas</span></div>
        <strong class="kpi-value" data-cita-kpi="confirmadas">—</strong>
    </article>
    <article class="kpi-card tone-success">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-stethoscope"></use></svg></span><span class="kpi-label">Atendidas</span></div>
        <strong class="kpi-value" data-cita-kpi="atendidas">—</strong>
    </article>
    <article class="kpi-card tone-danger">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span><span class="kpi-label">Inasistencias</span></div>
        <strong class="kpi-value" data-cita-kpi="inasistencias">—</strong>
    </article>
</section>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por paciente, CI o código…" autocomplete="off" aria-label="Buscar citas" data-cita-search>
        <button class="toolbar-clear" type="button" data-cita-clear hidden aria-label="Limpiar búsqueda">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Médico</span>
            <select data-cita-medico>
                <option value="">Todos los médicos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Estado</span>
            <select data-cita-estado>
                <option value="">Todos los estados</option>
                <option value="programada">Programadas</option>
                <option value="confirmada">Confirmadas</option>
                <option value="atendida">Atendidas</option>
                <option value="cancelada">Canceladas</option>
                <option value="inasistencia">Inasistencias</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <input type="date" data-cita-fecha value="<?= e(date('Y-m-d')) ?>" aria-label="Fecha">
        <button class="icon-btn" type="button" aria-label="Actualizar listado" data-cita-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-cita-count>—</p>
</section>

<section class="card" aria-labelledby="lista-citas-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Agenda global</span>
            <h2 id="lista-citas-title">Citas registradas</h2>
        </div>
    </div>
    <div class="table-wrap" data-citas-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-cita-pagination hidden></div>
</section>

<div class="modal" id="modal-cita-detalle" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-cita-detalle-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Cita</span>
                <h2 id="modal-cita-detalle-title">Detalle de la cita</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>
        <div class="modal-body" data-cita-detalle-body></div>
        <footer class="modal-foot">
            <span class="modal-foot-note">Los cambios de estado se registran en auditoría.</span>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cerrar</button>
        </footer>
    </div>
</div>

<div class="modal" id="modal-reprogramar" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-reprogramar-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Reprogramación</span>
                <h2 id="modal-reprogramar-title">Reprogramar cita</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-reprogramar-form novalidate>
            <div class="modal-body">
                <div class="appointment-summary" data-reprogramar-info></div>

                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label for="reprog-fecha">Nueva fecha *</label>
                        <input id="reprog-fecha" name="fecha" type="date" data-reprogramar-fecha required>
                    </div>
                    <div class="field">
                        <label for="reprog-motivo">Motivo</label>
                        <input id="reprog-motivo" name="motivo" type="text" maxlength="255" placeholder="Motivo de la reprogramación">
                    </div>
                </div>

                <div class="field">
                    <label>Horario disponible *</label>
                    <div class="slot-grid" data-reprogramar-slots></div>
                    <p class="field-hint" data-reprogramar-slot-hint>Elige una fecha para ver cupos.</p>
                    <input type="hidden" name="hora" data-reprogramar-hora>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Se validará la disponibilidad del médico.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-reprogramar-submit>Reprogramar</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="modal-cancelar" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-cancelar-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Cancelación</span>
                <h2 id="modal-cancelar-title">Cancelar cita</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-cancelar-form novalidate>
            <div class="modal-body">
                <div class="appointment-summary" data-cancelar-info></div>

                <div class="field">
                    <label for="cancel-motivo">Motivo de la cancelación *</label>
                    <textarea id="cancel-motivo" name="motivo" rows="3" maxlength="255" placeholder="Explica brevemente por qué se cancela" required></textarea>
                </div>

                <div class="callout callout-warning">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg>
                    <p>Solo se puede cancelar hasta el día anterior a la cita. La acción queda en el historial.</p>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">La cita pasará a estado cancelada.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Volver</button>
                <button class="btn btn-danger btn-sm" type="submit" data-cancelar-submit>Cancelar cita</button>
            </footer>
        </form>
    </div>
</div>
