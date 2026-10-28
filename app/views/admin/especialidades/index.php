<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Especialidades</h1>
        <p>Catálogo de especialidades médicas, restricciones de sexo/edad y destacadas en la landing.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-especialidad-new>
            Nueva especialidad
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar especialidad por nombre…" autocomplete="off" aria-label="Buscar especialidades" data-especialidad-search>
        <button class="toolbar-clear" type="button" data-especialidad-clear hidden aria-label="Limpiar búsqueda">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por estado</span>
            <select data-especialidad-estado>
                <option value="">Todos los estados</option>
                <option value="activo">Activas</option>
                <option value="inactivo">Inactivas</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Filtrar por destacadas</span>
            <select data-especialidad-destacada>
                <option value="">Todas</option>
                <option value="1">Solo destacadas</option>
                <option value="0">No destacadas</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" aria-label="Actualizar listado" data-especialidad-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-especialidad-count>—</p>
</section>

<section class="card" aria-labelledby="lista-especialidades-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Catálogo</span>
            <h2 id="lista-especialidades-title">Especialidades registradas</h2>
        </div>
    </div>
    <div class="table-wrap" data-especialidades-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-especialidad-pagination hidden></div>
</section>

<div class="modal" id="modal-especialidad" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-especialidad-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Catálogo</span>
                <h2 id="modal-especialidad-title" data-especialidad-modal-title>Nueva especialidad</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-especialidad-form novalidate>
            <input type="hidden" name="id">
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">01</span>
                        <div>
                            <h3>Identidad</h3>
                            <p>El nombre y la descripción se muestran en la web pública.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="esp-nombre">Nombre *</label>
                            <input id="esp-nombre" name="nombre" type="text" maxlength="80" placeholder="Ej. Cardiología" required>
                        </div>
                        <div class="field">
                            <label for="esp-icono">Icono</label>
                            <div class="select-wrap">
                                <select id="esp-icono" name="icono"></select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                        <div class="field field-span-2">
                            <label for="esp-descripcion">Descripción</label>
                            <textarea id="esp-descripcion" name="descripcion" rows="3" maxlength="255" placeholder="Qué atiende esta especialidad"></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">02</span>
                        <div>
                            <h3>Restricciones</h3>
                            <p>Limita quién puede reservar esta especialidad.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-3">
                        <div class="field">
                            <label for="esp-sexo">Sexo permitido</label>
                            <div class="select-wrap">
                                <select id="esp-sexo" name="sexo_permitido">
                                    <option value="ambos">Ambos</option>
                                    <option value="femenino">Solo femenino</option>
                                    <option value="masculino">Solo masculino</option>
                                </select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                        <div class="field">
                            <label for="esp-edad-min">Edad mínima</label>
                            <input id="esp-edad-min" name="edad_min" type="number" min="0" max="120" placeholder="Sin mínimo">
                        </div>
                        <div class="field">
                            <label for="esp-edad-max">Edad máxima</label>
                            <input id="esp-edad-max" name="edad_max" type="number" min="0" max="120" placeholder="Sin máximo">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">03</span>
                        <div>
                            <h3>Publicación</h3>
                            <p>Controla si aparece en la landing y si acepta nuevas citas.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <label class="check-card">
                            <input type="checkbox" name="destacada">
                            <span class="check-card-box" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                            <span>
                                <strong>Destacar en la landing</strong>
                                <small>Aparece primero en el sitio público.</small>
                            </span>
                        </label>
                        <div class="field">
                            <label for="esp-estado">Estado</label>
                            <div class="select-wrap">
                                <select id="esp-estado" name="estado">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Los cambios se registran en auditoría.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-especialidad-submit>Crear especialidad</button>
            </footer>
        </form>
    </div>
</div>
