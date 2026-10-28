<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1 data-consulta-title>Consultas</h1>
        <p data-consulta-subtitle>Selecciona una cita pendiente para registrar la atención.</p>
    </div>
    <div class="page-actions">
        <span data-consulta-badge></span>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/medico/agenda')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            Mi agenda
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/medico/pedidos')) ?>" data-consulta-pedidos-link hidden>
            <svg class="icon" aria-hidden="true"><use href="#i-ticket"></use></svg>
            Recetas y órdenes
        </a>
    </div>
</header>

<section class="card" aria-label="Citas pendientes de atención" data-consulta-pending>
    <div class="card-head">
        <div>
            <span class="card-kicker">Hoy</span>
            <h2 id="consulta-pending-title">Citas pendientes de atención</h2>
        </div>
        <a class="text-link" href="<?= e(url('/medico/agenda')) ?>">
            Ver agenda completa
            <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
        </a>
    </div>
    <div class="table-loading">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="skeleton skeleton-row"></div>
        <?php endfor; ?>
    </div>
</section>

<div class="dashboard-grid" data-consulta-workspace hidden>
    <section class="card card-span-2" aria-labelledby="consulta-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Registro clínico</span>
                <h2 id="consulta-title">Detalle de la atención</h2>
            </div>
        </div>

        <div data-consulta-existing hidden></div>

        <form data-consulta-form novalidate>
            <div class="table-loading">
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        </form>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Ficha</span>
                    <h2>Datos del paciente</h2>
                </div>
            </div>
            <div data-consulta-patient>
                <p class="text-muted">Cargando datos del paciente…</p>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Antecedentes</span>
                    <h2>Últimas atenciones</h2>
                </div>
            </div>
            <div data-consulta-context>
                <p class="text-muted">Sin información todavía.</p>
            </div>
        </section>

        <section class="callout callout-info">
            <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
            <p>La consulta queda asociada a esta cita. Si ya existe una atención registrada, se muestra en modo lectura.</p>
        </section>
    </aside>
</div>
