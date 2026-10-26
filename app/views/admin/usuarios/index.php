<?php
/** @var array<string, mixed> $user */
$usuarios = [
    ['nombre' => 'Admin Principal', 'usuario' => 'admin', 'email' => 'admin@vidasalud.test', 'rol' => 'Admin', 'badge' => 'badge-pine', 'estado' => 'activo'],
    ['nombre' => 'Recepción Central', 'usuario' => 'recepcion', 'email' => 'recepcion@vidasalud.test', 'rol' => 'Recepcionista', 'badge' => 'badge-info', 'estado' => 'activo'],
    ['nombre' => 'Recepción de Cardiología', 'usuario' => 'recepcion.medico', 'email' => 'recepcion.medico@vidasalud.test', 'rol' => 'Recepción médica', 'badge' => 'badge-info', 'estado' => 'activo'],
    ['nombre' => 'Dr. Carlos Méndez', 'usuario' => 'medico', 'email' => 'carlos.mendez@vidasalud.test', 'rol' => 'Médico', 'badge' => 'badge-accent', 'estado' => 'activo'],
    ['nombre' => 'Dra. Ana Pérez', 'usuario' => 'ana.perez', 'email' => 'ana.perez@vidasalud.test', 'rol' => 'Médico', 'badge' => 'badge-accent', 'estado' => 'activo'],
    ['nombre' => 'Juan Pérez', 'usuario' => '12345678', 'email' => 'juan.perez@example.test', 'rol' => 'Paciente', 'badge' => 'badge-muted', 'estado' => 'activo'],
    ['nombre' => 'María Gómez', 'usuario' => '10000002', 'email' => 'maria.gomez@example.test', 'rol' => 'Paciente', 'badge' => 'badge-muted', 'estado' => 'inactivo'],
    ['nombre' => 'Rosa Villarroel', 'usuario' => '10000004', 'email' => 'rosa.villarroel@example.test', 'rol' => 'Paciente', 'badge' => 'badge-muted', 'estado' => 'bloqueado'],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Usuarios</h1>
        <p>Cuentas del sistema: roles, bloqueos por intentos fallidos y restablecimiento de acceso.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button">
            Nuevo usuario
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="kpi-grid kpi-grid-4" aria-label="Resumen de cuentas">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span><span class="kpi-label">Cuentas totales</span></div>
        <strong class="kpi-value">24</strong>
        <span class="kpi-foot">6 roles activos</span>
    </article>
    <article class="kpi-card tone-success">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-check-circle"></use></svg></span><span class="kpi-label">Activas</span></div>
        <strong class="kpi-value">21</strong>
        <span class="kpi-foot">87% del total</span>
    </article>
    <article class="kpi-card tone-danger">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-lock"></use></svg></span><span class="kpi-label">Bloqueadas</span></div>
        <strong class="kpi-value">1</strong>
        <span class="kpi-foot">3 intentos fallidos</span>
    </article>
    <article class="kpi-card tone-neutral">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-stethoscope"></use></svg></span><span class="kpi-label">Médicos</span></div>
        <strong class="kpi-value">6</strong>
        <span class="kpi-foot">4 especialidades</span>
    </article>
</section>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por nombre, usuario o correo…" autocomplete="off" aria-label="Buscar usuarios">
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por rol</span>
            <select>
                <option>Todos los roles</option>
                <option>Admin</option>
                <option>Recepcionista</option>
                <option>Médico</option>
                <option>Paciente</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" aria-label="Actualizar listado">
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count">24 cuentas</p>
</section>

<section class="card" aria-labelledby="lista-usuarios-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Cuentas del sistema</span>
            <h2 id="lista-usuarios-title">Usuarios registrados</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $fila): ?>
                    <tr>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr((string) $fila['nombre'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= e($fila['nombre']) ?></strong>
                                    <small><?= e($fila['usuario']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e($fila['email']) ?></td>
                        <td><span class="badge <?= e($fila['badge']) ?>"><?= e($fila['rol']) ?></span></td>
                        <td>
                            <?php if ($fila['estado'] === 'activo'): ?>
                                <span class="status status-activo">Activo</span>
                            <?php elseif ($fila['estado'] === 'bloqueado'): ?>
                                <span class="status status-cancelada">Bloqueado</span>
                            <?php else: ?>
                                <span class="status status-inactivo">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Editar" aria-label="Editar">
                                    <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                </button>
                                <?php if ($fila['estado'] === 'bloqueado'): ?>
                                    <button class="row-action" type="button" title="Desbloquear" aria-label="Desbloquear">
                                        <svg class="icon" aria-hidden="true"><use href="#i-lock"></use></svg>
                                    </button>
                                <?php else: ?>
                                    <button class="row-action is-danger" type="button" title="Desactivar" aria-label="Desactivar">
                                        <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <span class="pagination-info">Mostrando 1–8 de 24</span>
        <div class="pagination-actions">
            <button class="pagination-btn" type="button" disabled>
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
                Anterior
            </button>
            <span class="pagination-page">1 / 3</span>
            <button class="pagination-btn" type="button">
                Siguiente
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
        </div>
    </div>
</section>
