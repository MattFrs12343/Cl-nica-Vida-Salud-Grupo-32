<?php
/** @var array<string, mixed> $user */
$medicos = [
    ['nombre' => 'Dr. Carlos Méndez', 'codigo' => 'MED-DEMO-001', 'registro' => 'MP-1001', 'especialidades' => ['Medicina General', 'Cardiología'], 'recepcion' => 'Recepción de Cardiología', 'estado' => 'activo', 'qr' => true],
    ['nombre' => 'Dra. Ana Pérez', 'codigo' => 'MED-DEMO-002', 'registro' => 'MP-1002', 'especialidades' => ['Pediatría'], 'recepcion' => 'Recepción Central', 'estado' => 'activo', 'qr' => true],
    ['nombre' => 'Dr. Jorge Salinas', 'codigo' => 'MED-DEMO-003', 'registro' => 'MP-1003', 'especialidades' => ['Ginecología'], 'recepcion' => 'Sin asignar', 'estado' => 'activo', 'qr' => false],
    ['nombre' => 'Dra. Patricia Rojas', 'codigo' => 'MED-DEMO-004', 'registro' => 'MP-1004', 'especialidades' => ['Dermatología', 'Medicina General'], 'recepcion' => 'Recepción Central', 'estado' => 'inactivo', 'qr' => false],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Médicos</h1>
        <p>Perfiles profesionales, especialidades asignadas y recepcionista de cada médico.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button">
            Nuevo médico
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por nombre o registro profesional…" autocomplete="off" aria-label="Buscar médicos">
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Filtrar por especialidad</span>
            <select>
                <option>Todas las especialidades</option>
                <option>Medicina General</option>
                <option>Cardiología</option>
                <option>Pediatría</option>
                <option>Ginecología</option>
                <option>Dermatología</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
    </div>
    <p class="toolbar-count">4 médicos</p>
</section>

<div class="quick-grid quick-grid-3">
    <?php foreach ($medicos as $medico): ?>
        <article class="card">
            <div class="card-head">
                <div class="cell-person">
                    <span class="avatar"><?= e(mb_strtoupper(mb_substr(explode(' ', $medico['nombre'])[1] ?? 'D', 0, 1))) ?></span>
                    <div>
                        <strong><?= e($medico['nombre']) ?></strong>
                        <small><?= e($medico['codigo']) ?> · <?= e($medico['registro']) ?></small>
                    </div>
                </div>
                <?php if ($medico['estado'] === 'activo'): ?>
                    <span class="status status-activo">Activo</span>
                <?php else: ?>
                    <span class="status status-inactivo">Inactivo</span>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:12px 0;">
                <?php foreach ($medico['especialidades'] as $especialidad): ?>
                    <span class="badge badge-accent"><?= e($especialidad) ?></span>
                <?php endforeach; ?>
            </div>
            <p style="display:flex;align-items:center;gap:8px;margin:0 0 6px;font-size:12.5px;color:var(--panel-muted);">
                <svg class="icon" aria-hidden="true" style="width:15px;height:15px;flex:none;"><use href="#i-users"></use></svg>
                <?= e($medico['recepcion']) ?>
            </p>
            <p style="display:flex;align-items:center;gap:8px;margin:0 0 14px;font-size:12.5px;color:var(--panel-muted);">
                <svg class="icon" aria-hidden="true" style="width:15px;height:15px;flex:none;"><use href="#i-qr"></use></svg>
                <?= $medico['qr'] ? 'QR cargado' : 'Sin QR subido' ?>
            </p>
            <div class="row-actions">
                <button class="row-action" type="button" title="Ver detalle" aria-label="Ver detalle">
                    <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                </button>
                <button class="row-action" type="button" title="Editar" aria-label="Editar">
                    <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                </button>
                <button class="row-action is-danger" type="button" title="Desactivar" aria-label="Desactivar">
                    <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                </button>
            </div>
        </article>
    <?php endforeach; ?>
</div>
