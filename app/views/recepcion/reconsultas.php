<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Continuidad clínica</span>
        <h1>Reconsultas</h1>
        <p>Da continuidad a una atención sin perder la cita original ni el médico tratante.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/recepcion/agenda')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-clock"></use></svg>
            Ver agenda
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/recepcion/citas?nuevo=1')) ?>">
            Nueva cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        </a>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-filters">
        <label class="input-inline" aria-label="Fecha de las citas de origen">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            <input type="date" data-reconsultas-fecha value="<?= e(date('Y-m-d')) ?>">
        </label>
        <div class="segmented" role="tablist" aria-label="Tipo de reconsulta">
            <button class="segmented-item is-active" type="button" role="tab" aria-selected="true"
                    data-reconsultas-tab="seguimiento">
                Seguimiento
                <span class="segmented-count" data-reconsultas-count="seguimiento">—</span>
            </button>
            <button class="segmented-item" type="button" role="tab" aria-selected="false"
                    data-reconsultas-tab="ausencia_medico">
                Ausencia del médico
                <span class="segmented-count" data-reconsultas-count="ausencia_medico">—</span>
            </button>
        </div>
        <button class="icon-btn" type="button" data-reconsultas-refresh aria-label="Actualizar">
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-reconsultas-total>—</p>
</section>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="reconsultas-lista-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Citas elegibles</span>
                <h2 id="reconsultas-lista-title" data-reconsultas-title>Seguimiento</h2>
            </div>
            <span class="badge badge-muted" data-reconsultas-date-label><?= e(date('d/m/Y')) ?></span>
        </div>
        <div class="reconsultas-list" data-reconsultas-list>
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
                    <span class="card-kicker">Reglas claras</span>
                    <h2>¿Cuándo aplica?</h2>
                </div>
            </div>
            <div class="rules-list">
                <div class="rule-item">
                    <span class="rule-icon tone-success" aria-hidden="true">
                        <svg class="icon"><use href="#i-check"></use></svg>
                    </span>
                    <div>
                        <strong>Seguimiento</strong>
                        <p>La cita original ya fue atendida. La reconsulta se crea como una nueva cita vinculada y conserva al médico.</p>
                    </div>
                </div>
                <div class="rule-item">
                    <span class="rule-icon tone-warning" aria-hidden="true">
                        <svg class="icon"><use href="#i-alert"></use></svg>
                    </span>
                    <div>
                        <strong>Ausencia del médico</strong>
                        <p>La cita estaba programada o confirmada. La original se cancela y el paciente recibe una nueva fecha.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Consejo</span>
            <h2>Confirma antes de mover</h2>
            <p>Verifica con el paciente la nueva fecha y el horario disponible. La reconsulta queda registrada en el historial de estados.</p>
            <a class="btn btn-light btn-sm" href="<?= e(url('/recepcion/agenda')) ?>">
                Revisar agenda
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
            </a>
        </section>
    </aside>
</div>

<?php require app_path('views/recepcion/partials/modal-reconsulta.php'); ?>
