<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Catálogo clínico</span>
        <h1>Exámenes</h1>
        <p>Define los estudios de laboratorio e imagen y el precio que se cobra en caja.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-examen-new>
            Nuevo examen
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por nombre o código…" autocomplete="off"
               aria-label="Buscar exámenes" data-examen-search>
        <button class="toolbar-clear" type="button" data-examen-clear hidden aria-label="Limpiar búsqueda">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por tipo</span>
            <select data-examen-tipo>
                <option value="">Todos los tipos</option>
                <option value="laboratorio">Laboratorio</option>
                <option value="imagen">Imagen</option>
                <option value="otro">Otro</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Filtrar por estado</span>
            <select data-examen-estado>
                <option value="">Todos los estados</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" aria-label="Actualizar listado" data-examen-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-examen-count>—</p>
</section>

<section class="card" aria-labelledby="lista-examenes-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Precios de la clínica</span>
            <h2 id="lista-examenes-title">Catálogo de exámenes</h2>
        </div>
        <span class="badge badge-pine">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg>
            Precio copiado a cada orden
        </span>
    </div>
    <div class="table-wrap" data-examenes-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-examen-pagination hidden></div>
</section>

<div class="modal" id="modal-examen" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-examen-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Catálogo</span>
                <h2 id="modal-examen-title" data-examen-modal-title>Nuevo examen</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-examen-form novalidate>
            <input type="hidden" name="id">
            <div class="modal-body">
                <div class="field-grid field-grid-2">
                    <div class="field field-span-2">
                        <label for="examen-nombre">Nombre *</label>
                        <input id="examen-nombre" name="nombre" type="text" maxlength="150"
                               placeholder="Ej. Hemograma completo" required>
                    </div>
                    <div class="field">
                        <label for="examen-codigo">Código <span class="label-hint">(opcional)</span></label>
                        <input id="examen-codigo" name="codigo" type="text" maxlength="20"
                               placeholder="Ej. LAB-HEMO">
                    </div>
                    <div class="field">
                        <label for="examen-tipo">Tipo *</label>
                        <div class="select-wrap">
                            <select id="examen-tipo" name="tipo" required>
                                <option value="laboratorio">Laboratorio</option>
                                <option value="imagen">Imagen</option>
                                <option value="otro">Otro</option>
                            </select>
                            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                        </div>
                    </div>
                    <div class="field">
                        <label for="examen-precio">Precio *</label>
                        <div class="input-wrap input-money">
                            <span class="input-prefix">Bs</span>
                            <input id="examen-precio" name="precio" type="number" inputmode="decimal"
                                   min="0" step="0.01" placeholder="0,00" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="examen-activo">Estado</label>
                        <div class="select-wrap">
                            <select id="examen-activo" name="activo">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                        </div>
                    </div>
                </div>

                <div class="callout callout-info">
                    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
                    <p>El precio se copia al detalle de cada orden emitida. Cambiarlo aquí no altera órdenes anteriores.</p>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Solo recepción y administración pueden editar precios.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-examen-submit>Guardar examen</button>
            </footer>
        </form>
    </div>
</div>
