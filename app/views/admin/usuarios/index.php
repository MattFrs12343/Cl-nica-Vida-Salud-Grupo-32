<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Usuarios</h1>
        <p>Cuentas del sistema: roles, bloqueos por intentos fallidos y restablecimiento de acceso.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-user-new>
            Nuevo usuario
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="kpi-grid kpi-grid-4" aria-label="Resumen de cuentas">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span><span class="kpi-label">Cuentas totales</span></div>
        <strong class="kpi-value" data-user-kpi="total">—</strong>
        <span class="kpi-foot">Todas las cuentas registradas</span>
    </article>
    <article class="kpi-card tone-success">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-check-circle"></use></svg></span><span class="kpi-label">Activas</span></div>
        <strong class="kpi-value" data-user-kpi="activos">—</strong>
        <span class="kpi-foot">Pueden iniciar sesión</span>
    </article>
    <article class="kpi-card tone-danger">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-lock"></use></svg></span><span class="kpi-label">Bloqueadas</span></div>
        <strong class="kpi-value" data-user-kpi="bloqueados">—</strong>
        <span class="kpi-foot">Por intentos fallidos</span>
    </article>
    <article class="kpi-card tone-neutral">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-shield"></use></svg></span><span class="kpi-label">Roles</span></div>
        <strong class="kpi-value">6</strong>
        <span class="kpi-foot">Perfiles del sistema</span>
    </article>
</section>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por usuario o correo…" autocomplete="off" aria-label="Buscar usuarios" data-user-search>
        <button class="toolbar-clear" type="button" data-user-clear hidden aria-label="Limpiar búsqueda">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por rol</span>
            <select data-user-role>
                <option value="">Todos los roles</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Filtrar por estado</span>
            <select data-user-estado>
                <option value="">Todos los estados</option>
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" aria-label="Actualizar listado" data-user-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-user-count>—</p>
</section>

<section class="card" aria-labelledby="lista-usuarios-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Cuentas del sistema</span>
            <h2 id="lista-usuarios-title">Usuarios registrados</h2>
        </div>
    </div>
    <div class="table-wrap" data-users-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-user-pagination hidden></div>
</section>

<div class="modal" id="modal-usuario" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-usuario-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Administración</span>
                <h2 id="modal-usuario-title" data-user-modal-title>Nuevo usuario</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-user-form novalidate>
            <input type="hidden" name="id">
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">01</span>
                        <div>
                            <h3>Identidad de la cuenta</h3>
                            <p>El usuario o el correo permiten iniciar sesión.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="user-username">Nombre de usuario</label>
                            <input id="user-username" name="username" type="text" autocomplete="username" placeholder="Ej. recepcion.cardiologia">
                            <p class="field-hint">3–60 caracteres: letras, números, punto, guion o guion bajo.</p>
                        </div>
                        <div class="field">
                            <label for="user-email">Correo electrónico</label>
                            <input id="user-email" name="email" type="email" autocomplete="email" placeholder="usuario@vidasalud.test">
                        </div>
                        <div class="field">
                            <label for="user-rol">Rol *</label>
                            <div class="select-wrap">
                                <select id="user-rol" name="rol" required></select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                        <div class="field">
                            <label class="field-label-row">
                                <span>Estado</span>
                            </label>
                            <label class="check-card">
                                <input type="checkbox" name="activo" checked>
                                <span class="check-card-box" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                                <span>
                                    <strong>Cuenta activa</strong>
                                    <small>Puede iniciar sesión en el panel.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">02</span>
                        <div>
                            <h3>Contraseña</h3>
                            <p>Al editar, deja los campos vacíos para conservar la contraseña actual.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="user-password">Contraseña</label>
                            <input id="user-password" name="password" type="password" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
                            <p class="field-hint">Debe incluir al menos una letra y un número.</p>
                        </div>
                        <div class="field">
                            <label for="user-password-confirmation">Confirmar contraseña</label>
                            <input id="user-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repite la contraseña">
                        </div>
                    </div>
                </div>

                <div class="callout callout-info">
                    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
                    <p>Los pacientes y médicos también pueden tener cuentas propias. Para un médico, primero crea su perfil en la sección Médicos.</p>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Los cambios se registran en auditoría.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-user-submit>Crear usuario</button>
            </footer>
        </form>
    </div>
</div>
