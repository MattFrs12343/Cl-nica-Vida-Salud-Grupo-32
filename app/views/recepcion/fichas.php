<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Fichas y accesos</span>
        <h1>Fichas</h1>
        <p>Busca un ticket emitido, revisa su detalle y reimprímelo cuando el paciente lo necesite.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/kiosco')) ?>" target="_blank" rel="noopener">
            <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
            Abrir kiosco
        </a>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Código de ficha, CI o nombre del paciente…"
               autocomplete="off" aria-label="Buscar fichas" data-ficha-search>
        <button class="toolbar-clear" type="button" data-ficha-clear aria-label="Limpiar búsqueda" hidden>
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="input-inline" aria-label="Fecha de emisión">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            <input type="date" data-ficha-fecha aria-label="Fecha de emisión">
        </label>
        <button class="icon-btn" type="button" data-ficha-refresh aria-label="Actualizar listado">
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-ficha-count>—</p>
</section>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="lista-fichas-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Tickets emitidos</span>
                <h2 id="lista-fichas-title">Resultados</h2>
            </div>
            <span class="badge badge-muted" data-ficha-day-label>Todas las fechas</span>
        </div>
        <div class="table-wrap" data-fichas-table>
            <div class="table-loading">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton skeleton-row"></div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="pagination" data-ficha-pagination hidden></div>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Cómo funciona</span>
                    <h2>La ficha es el pase</h2>
                </div>
            </div>
            <div class="rules-list">
                <div class="rule-item">
                    <span class="rule-icon tone-primary" aria-hidden="true">
                        <svg class="icon"><use href="#i-qr"></use></svg>
                    </span>
                    <div>
                        <strong>Se emite en el kiosco</strong>
                        <p>El paciente escanea su CI y recibe una ficha con un código de acceso de 4 dígitos.</p>
                    </div>
                </div>
                <div class="rule-item">
                    <span class="rule-icon tone-info" aria-hidden="true">
                        <svg class="icon"><use href="#i-print"></use></svg>
                    </span>
                    <div>
                        <strong>Se reimprime aquí</strong>
                        <p>Busca por código o paciente y registra la reimpresión. La ficha original no se modifica.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Sin ficha</span>
            <h2>¿El paciente no la tiene?</h2>
            <p>Puede generarla en el kiosco con su cédula. Si el kiosco no está disponible, pide apoyo a soporte.</p>
            <a class="btn btn-light btn-sm" href="<?= e(url('/kiosco')) ?>" target="_blank" rel="noopener">
                Ir al kiosco
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
            </a>
        </section>
    </aside>
</div>

<div class="modal" id="modal-ficha" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-ficha-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Ficha emitida</span>
                <h2 id="modal-ficha-title" data-ficha-modal-title>Detalle de la ficha</h2>
            </div>
            <div class="modal-head-actions">
                <button class="btn btn-ghost btn-sm" type="button" data-ficha-print>
                    <svg class="icon" aria-hidden="true"><use href="#i-print"></use></svg>
                    Reimprimir
                </button>
                <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                    <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
                </button>
            </div>
        </header>
        <div class="modal-body" data-ficha-detail>
            <div class="table-loading">
                <div class="skeleton skeleton-block"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        </div>
        <footer class="modal-foot">
            <span class="modal-foot-note" data-ficha-reprints>—</span>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cerrar</button>
        </footer>
    </div>
</div>
