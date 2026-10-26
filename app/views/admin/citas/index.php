<?php
/** @var array<string, mixed> $user */
$citas = [
    ['hora' => '08:00', 'codigo' => 'CM-0041', 'paciente' => 'Juan Pérez', 'ci' => '10000001', 'medico' => 'Dr. Carlos Méndez', 'especialidad' => 'Medicina General', 'origen' => 'Web', 'estado' => 'confirmada'],
    ['hora' => '08:30', 'codigo' => 'CM-0042', 'paciente' => 'María Gómez', 'ci' => '10000002', 'medico' => 'Dr. Carlos Méndez', 'especialidad' => 'Cardiología', 'origen' => 'Kiosco', 'estado' => 'atendida'],
    ['hora' => '09:00', 'codigo' => 'CM-0043', 'paciente' => 'Luis Niño', 'ci' => '10000003', 'medico' => 'Dra. Ana Pérez', 'especialidad' => 'Pediatría', 'origen' => 'Recepción', 'estado' => 'programada'],
    ['hora' => '09:30', 'codigo' => 'CM-0044', 'paciente' => 'Rosa Villarroel', 'ci' => '10000004', 'medico' => 'Dr. Jorge Salinas', 'especialidad' => 'Ginecología', 'origen' => 'Web', 'estado' => 'cancelada'],
    ['hora' => '10:00', 'codigo' => 'CM-0045', 'paciente' => 'Pedro Fernández', 'ci' => '10000005', 'medico' => 'Dra. Ana Pérez', 'especialidad' => 'Pediatría', 'origen' => 'Kiosco', 'estado' => 'inasistencia'],
    ['hora' => '10:30', 'codigo' => 'CM-0046', 'paciente' => 'Lucía Flores', 'ci' => '10000006', 'medico' => 'Dr. Carlos Méndez', 'especialidad' => 'Medicina General', 'origen' => 'Web', 'estado' => 'confirmada'],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Citas</h1>
        <p>Supervisión global de la agenda: todos los médicos, todos los canales de origen.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/admin/reportes')) ?>">
            Ver reportes
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-chart"></use></svg></span>
        </a>
    </div>
</header>

<section class="kpi-grid kpi-grid-5" aria-label="Resumen de hoy">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span><span class="kpi-label">Citas hoy</span></div>
        <strong class="kpi-value">6</strong>
    </article>
    <article class="kpi-card tone-neutral">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-clock"></use></svg></span><span class="kpi-label">Programadas</span></div>
        <strong class="kpi-value">1</strong>
    </article>
    <article class="kpi-card tone-info">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-check-circle"></use></svg></span><span class="kpi-label">Confirmadas</span></div>
        <strong class="kpi-value">2</strong>
    </article>
    <article class="kpi-card tone-success">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-stethoscope"></use></svg></span><span class="kpi-label">Atendidas</span></div>
        <strong class="kpi-value">1</strong>
    </article>
    <article class="kpi-card tone-danger">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span><span class="kpi-label">Inasistencias</span></div>
        <strong class="kpi-value">1</strong>
    </article>
</section>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por paciente, CI o código…" autocomplete="off" aria-label="Buscar citas">
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Médico</span>
            <select>
                <option>Todos los médicos</option>
                <option>Dr. Carlos Méndez</option>
                <option>Dra. Ana Pérez</option>
                <option>Dr. Jorge Salinas</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Estado</span>
            <select>
                <option>Todos los estados</option>
                <option>Programada</option>
                <option>Confirmada</option>
                <option>Atendida</option>
                <option>Cancelada</option>
                <option>Inasistencia</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <input type="date" value="<?= e(date('Y-m-d')) ?>" aria-label="Fecha">
    </div>
    <p class="toolbar-count">6 citas</p>
</section>

<section class="card" aria-labelledby="lista-citas-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Hoy · <?= e(date('d/m/Y')) ?></span>
            <h2 id="lista-citas-title">Agenda global</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Origen</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($citas as $cita): ?>
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <span class="cell-time"><?= e($cita['hora']) ?></span>
                                <small><?= e($cita['codigo']) ?></small>
                            </div>
                        </td>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr($cita['paciente'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= e($cita['paciente']) ?></strong>
                                    <small><?= e($cita['ci']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e($cita['medico']) ?></td>
                        <td><?= e($cita['especialidad']) ?></td>
                        <td><span class="badge badge-muted"><?= e($cita['origen']) ?></span></td>
                        <td>
                            <span class="status status-<?= e($cita['estado']) ?>"><?= e(ucfirst($cita['estado'])) ?></span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Ver detalle" aria-label="Ver detalle">
                                    <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <span class="pagination-info">Mostrando 1–6 de 34</span>
        <div class="pagination-actions">
            <button class="pagination-btn" type="button" disabled>
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
                Anterior
            </button>
            <span class="pagination-page">1 / 6</span>
            <button class="pagination-btn" type="button">
                Siguiente
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
        </div>
    </div>
</section>
