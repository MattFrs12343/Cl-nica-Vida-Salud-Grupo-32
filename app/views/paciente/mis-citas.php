<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Mi salud</span>
        <h1>Mis citas</h1>
        <p>Consulta tus próximas atenciones, revisa su estado y cancela cuando aún estés a tiempo.</p>
    </div>
    <div class="page-actions">
        <div class="segmented" role="tablist" aria-label="Filtrar citas">
            <button class="segmented-item is-active" type="button" role="tab" aria-selected="true" aria-pressed="true" data-citas-filter="proximas">
                Próximas
                <span class="segmented-count" data-citas-proximas>0</span>
            </button>
            <button class="segmented-item" type="button" role="tab" aria-selected="false" aria-pressed="false" data-citas-filter="pasadas">
                Anteriores
                <span class="segmented-count" data-citas-pasadas>0</span>
            </button>
            <button class="segmented-item" type="button" role="tab" aria-selected="false" aria-pressed="false" data-citas-filter="todas">
                Todas
                <span class="segmented-count" data-citas-total>0</span>
            </button>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-citas-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/reservar')) ?>">
            Reservar cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        </a>
    </div>
</header>

<section class="card" aria-labelledby="citas-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Agenda personal</span>
            <h2 id="citas-title">Historial de citas</h2>
        </div>
        <span class="badge badge-muted" data-citas-count>0 citas</span>
    </div>

    <p class="text-muted" style="margin:0 0 12px;font-size:13px;">
        Puedes cancelar una cita programada o confirmada hasta el día anterior a la atención.
    </p>

    <div data-citas-list>
        <div class="table-loading">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
</section>
