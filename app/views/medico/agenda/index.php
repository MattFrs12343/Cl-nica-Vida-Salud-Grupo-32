<?php
/** @var array<string, mixed> $user */
$citas = [
    ['hora' => '08:00', 'codigo' => 'CM-0041', 'paciente' => 'Juan Pérez', 'ci' => '10000001', 'motivo' => 'Control de presión arterial', 'estado' => 'confirmada'],
    ['hora' => '08:30', 'codigo' => 'CM-0042', 'paciente' => 'María Gómez', 'ci' => '10000002', 'motivo' => 'Dolor de cabeza persistente', 'estado' => 'confirmada'],
    ['hora' => '09:00', 'codigo' => 'CM-0043', 'paciente' => 'Lucía Flores', 'ci' => '10000006', 'motivo' => 'Control prenatal', 'estado' => 'programada'],
    ['hora' => '09:30', 'codigo' => 'CM-0039', 'paciente' => 'Pedro Fernández', 'ci' => '10000005', 'motivo' => 'Chequeo general', 'estado' => 'atendida'],
    ['hora' => '10:00', 'codigo' => 'CM-0044', 'paciente' => 'Rosa Villarroel', 'ci' => '10000004', 'motivo' => 'Dolor articular', 'estado' => 'inasistencia'],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Mi agenda</h1>
        <p>El día de hoy, hora por hora. Marca la asistencia a medida que atiendes.</p>
    </div>
    <div class="page-actions">
        <div class="segmented">
            <button class="segmented-item is-active" type="button">Día</button>
            <button class="segmented-item" type="button">Semana</button>
        </div>
        <input type="date" value="<?= e(date('Y-m-d')) ?>" aria-label="Fecha">
    </div>
</header>

<section class="kpi-grid kpi-grid-4" aria-label="Resumen del día">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span><span class="kpi-label">Citas hoy</span></div>
        <strong class="kpi-value">5</strong>
    </article>
    <article class="kpi-card tone-info">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-clock"></use></svg></span><span class="kpi-label">En espera</span></div>
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

<section class="card" aria-labelledby="agenda-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Hoy · <?= e(date('d/m/Y')) ?></span>
            <h2 id="agenda-title">Agenda del día</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Motivo</th>
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
                        <td><?= e($cita['motivo']) ?></td>
                        <td><span class="status status-<?= e($cita['estado']) ?>"><?= e(ucfirst($cita['estado'])) ?></span></td>
                        <td>
                            <div class="row-actions">
                                <?php if ($cita['estado'] === 'confirmada'): ?>
                                    <button class="row-action" type="button" title="Atender" aria-label="Atender">
                                        <svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg>
                                    </button>
                                    <button class="row-action is-danger" type="button" title="Inasistencia" aria-label="Inasistencia">
                                        <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                                    </button>
                                <?php elseif ($cita['estado'] === 'atendida'): ?>
                                    <button class="row-action" type="button" title="Ver consulta" aria-label="Ver consulta">
                                        <svg class="icon" aria-hidden="true"><use href="#i-clipboard"></use></svg>
                                    </button>
                                <?php else: ?>
                                    <button class="row-action" type="button" title="Ver detalle" aria-label="Ver detalle">
                                        <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
