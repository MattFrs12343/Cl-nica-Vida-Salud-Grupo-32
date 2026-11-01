<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Pacientes</h1>
        <p>Pacientes que has atendido alguna vez. Busca por nombre o CI para abrir su historia.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-pacientes-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por nombre o CI…" autocomplete="off" aria-label="Buscar pacientes" data-pacientes-search>
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Estado</span>
            <select data-pacientes-estado>
                <option value="">Todos</option>
                <option value="activo">Activos</option>
                <option value="inactivo">Inactivos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
    </div>
    <p class="toolbar-count" data-pacientes-count>0 pacientes atendidos</p>
</section>

<section class="card" aria-labelledby="pacientes-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Tu consulta</span>
            <h2 id="pacientes-title">Pacientes atendidos</h2>
        </div>
    </div>
    <div data-pacientes-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-pacientes-pagination></div>
</section>
