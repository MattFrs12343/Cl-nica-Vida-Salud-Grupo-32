<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Médicos</h1>
        <p>Perfiles profesionales, especialidades asignadas y recepcionista de cada médico.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-medico-new>
            Nuevo médico
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por nombre, registro o código…" autocomplete="off" aria-label="Buscar médicos" data-medico-search>
        <button class="toolbar-clear" type="button" data-medico-clear hidden aria-label="Limpiar búsqueda">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por especialidad</span>
            <select data-medico-especialidad>
                <option value="">Todas las especialidades</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Filtrar por estado</span>
            <select data-medico-estado>
                <option value="">Todos los estados</option>
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" aria-label="Actualizar listado" data-medico-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-medico-count>—</p>
</section>

<section aria-label="Listado de médicos">
    <div data-medicos-grid>
        <div class="quick-grid quick-grid-3">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="card"><div class="skeleton skeleton-block"></div></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-medico-pagination hidden></div>
</section>

<div class="modal" id="modal-medico" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-medico-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Administración</span>
                <h2 id="modal-medico-title" data-medico-modal-title>Nuevo médico</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-medico-form novalidate>
            <input type="hidden" name="id">
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">01</span>
                        <div>
                            <h3>Datos profesionales</h3>
                            <p>El registro profesional y el nombre aparecen en la agenda y los reportes.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="med-registro">Registro profesional *</label>
                            <input id="med-registro" name="registro_profesional" type="text" maxlength="50" placeholder="Ej. MP-1001" required>
                        </div>
                        <div class="field">
                            <label for="med-duracion">Duración de cita (min) *</label>
                            <input id="med-duracion" name="duracion_cita_min" type="number" min="5" max="120" step="5" value="30" required>
                        </div>
                        <div class="field">
                            <label for="med-nombres">Nombres *</label>
                            <input id="med-nombres" name="nombres" type="text" maxlength="100" autocomplete="given-name" required>
                        </div>
                        <div class="field">
                            <label for="med-apellidos">Apellidos *</label>
                            <input id="med-apellidos" name="apellidos" type="text" maxlength="100" autocomplete="family-name" required>
                        </div>
                        <div class="field">
                            <label for="med-telefono">Teléfono</label>
                            <input id="med-telefono" name="telefono" type="tel" maxlength="20" placeholder="Ej. 70012345">
                        </div>
                        <div class="field">
                            <label for="med-email">Correo electrónico</label>
                            <input id="med-email" name="email" type="email" maxlength="120" placeholder="medico@vidasalud.test">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">02</span>
                        <div>
                            <h3>Especialidades</h3>
                            <p>Selecciona al menos una especialidad activa.</p>
                        </div>
                    </div>
                    <div class="field">
                        <div class="admin-check-grid" data-medico-especialidades></div>
                    </div>
                </div>

                <div class="form-section" data-medico-account>
                    <div class="form-section-head">
                        <span class="form-step">03</span>
                        <div>
                            <h3>Cuenta de acceso</h3>
                            <p>El médico necesita una cuenta para entrar a su panel.</p>
                        </div>
                    </div>
                    <label class="check-card">
                        <input type="checkbox" name="crear_usuario" checked>
                        <span class="check-card-box" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                        <span>
                            <strong>Crear cuenta de médico</strong>
                            <small>Se creará con el rol Médico y podrá iniciar sesión.</small>
                        </span>
                    </label>

                    <div class="field-grid field-grid-2 admin-account-fields" data-medico-account-fields>
                        <div class="field">
                            <label for="med-username">Nombre de usuario *</label>
                            <input id="med-username" name="username" type="text" autocomplete="username" placeholder="Ej. dr.mendez">
                        </div>
                        <div class="field">
                            <label for="med-password">Contraseña *</label>
                            <input id="med-password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                        </div>
                        <div class="field field-span-2">
                            <label for="med-password-confirmation">Confirmar contraseña *</label>
                            <input id="med-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repite la contraseña">
                        </div>
                    </div>
                </div>

                <div class="callout callout-info">
                    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
                    <p>El QR de acceso lo sube el propio médico desde su panel. Un administrador no puede reemplazarlo.</p>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Los cambios se registran en auditoría.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-medico-submit>Crear médico</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="modal-medico-detalle" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-medico-detalle-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Perfil profesional</span>
                <h2 id="modal-medico-detalle-title">Detalle del médico</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>
        <div class="modal-body" data-medico-detalle-body></div>
        <footer class="modal-foot">
            <span class="modal-foot-note">Los datos se actualizan desde la API.</span>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cerrar</button>
        </footer>
    </div>
</div>
