<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Auditoría</h1>
        <p>Registro de cambios sobre usuarios, agenda y datos clínicos del sistema.</p>
    </div>
    <div class="page-actions">
        <span class="badge badge-pine">
            <svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg>
            Solo lectura
        </span>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por usuario o correo…" autocomplete="off" aria-label="Buscar en auditoría" data-auditoria-search>
        <button class="toolbar-clear" type="button" data-auditoria-clear hidden aria-label="Limpiar búsqueda">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Acción</span>
            <select data-auditoria-accion>
                <option value="">Todas las acciones</option>
                <option value="INSERT">INSERT</option>
                <option value="UPDATE">UPDATE</option>
                <option value="DELETE">DELETE</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Tabla</span>
            <select data-auditoria-tabla>
                <option value="">Todas las tablas</option>
                <option value="usuarios">usuarios</option>
                <option value="pacientes">pacientes</option>
                <option value="medicos">medicos</option>
                <option value="especialidades">especialidades</option>
                <option value="horarios">horarios</option>
                <option value="bloqueos">bloqueos</option>
                <option value="citas">citas</option>
                <option value="citas_estado_historial">citas_estado_historial</option>
                <option value="fichas">fichas</option>
                <option value="consultas">consultas</option>
                <option value="recetas">recetas</option>
                <option value="pagos">pagos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <input type="date" data-auditoria-desde aria-label="Desde">
        <input type="date" data-auditoria-hasta aria-label="Hasta">
        <button class="icon-btn" type="button" aria-label="Actualizar listado" data-auditoria-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-auditoria-count>—</p>
</section>

<section class="card" aria-labelledby="auditoria-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Trazabilidad</span>
            <h2 id="auditoria-title">Actividad registrada</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Acción</th>
                    <th>Registro</th>
                    <th>IP</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody data-auditoria-body>
                <tr><td colspan="7"><div class="skeleton skeleton-row"></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" data-auditoria-pagination hidden></div>
</section>

<div class="modal" id="modal-auditoria" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-auditoria-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Auditoría</span>
                <h2 id="modal-auditoria-title">Detalle del registro</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>
        <div class="modal-body" data-auditoria-detalle></div>
        <footer class="modal-foot">
            <span class="modal-foot-note">Este registro no puede modificarse.</span>
            <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cerrar</button>
        </footer>
    </div>
</div>
