<?php
/** @var array<string, mixed> $user */
$porEspecialidad = [
    ['nombre' => 'Medicina General', 'citas' => 142, 'pct' => 100],
    ['nombre' => 'Cardiología', 'citas' => 88, 'pct' => 62],
    ['nombre' => 'Pediatría', 'citas' => 71, 'pct' => 50],
    ['nombre' => 'Ginecología', 'citas' => 46, 'pct' => 32],
    ['nombre' => 'Dermatología', 'citas' => 29, 'pct' => 20],
];
$porMedico = [
    ['nombre' => 'Dr. Carlos Méndez', 'citas' => 112, 'asistencia' => 94],
    ['nombre' => 'Dra. Ana Pérez', 'citas' => 97, 'asistencia' => 91],
    ['nombre' => 'Dr. Jorge Salinas', 'citas' => 64, 'asistencia' => 88],
    ['nombre' => 'Dra. Patricia Rojas', 'citas' => 41, 'asistencia' => 85],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Reportes</h1>
        <p>Demanda, asistencia y ocupación por médico y especialidad — últimos 30 días.</p>
    </div>
    <div class="page-actions">
        <input type="date" value="2026-09-24" aria-label="Desde">
        <input type="date" value="<?= e(date('Y-m-d')) ?>" aria-label="Hasta">
        <button class="btn btn-ghost btn-sm" type="button">
            Exportar CSV
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-download"></use></svg></span>
        </button>
    </div>
</header>

<section class="kpi-grid kpi-grid-4" aria-label="Indicadores del periodo">
    <article class="kpi-card tone-primary">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span><span class="kpi-label">Citas totales</span></div>
        <strong class="kpi-value">376</strong>
        <span class="kpi-foot">+12% vs. mes anterior</span>
    </article>
    <article class="kpi-card tone-success">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-stethoscope"></use></svg></span><span class="kpi-label">Tasa de atención</span></div>
        <strong class="kpi-value">89%</strong>
        <span class="kpi-foot">335 de 376 citas</span>
    </article>
    <article class="kpi-card tone-danger">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span><span class="kpi-label">Inasistencias</span></div>
        <strong class="kpi-value">8%</strong>
        <span class="kpi-foot">30 citas</span>
    </article>
    <article class="kpi-card tone-info">
        <div class="kpi-top"><span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span><span class="kpi-label">Pacientes nuevos</span></div>
        <strong class="kpi-value">54</strong>
        <span class="kpi-foot">14% del total</span>
    </article>
</section>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="reporte-especialidad-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Demanda</span>
                <h2 id="reporte-especialidad-title">Citas por especialidad</h2>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px;">
            <?php foreach ($porEspecialidad as $fila): ?>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;">
                        <strong><?= e($fila['nombre']) ?></strong>
                        <span><?= e((string) $fila['citas']) ?> citas</span>
                    </div>
                    <div style="height:8px;border-radius:6px;background:var(--panel-line,#e4e8df);overflow:hidden;">
                        <div style="height:100%;width:<?= (int) $fila['pct'] ?>%;border-radius:6px;background:var(--pine,#0b3d35);"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Origen</span>
                    <h2>Cómo llegan las citas</h2>
                </div>
            </div>
            <div class="mini-stats">
                <div class="mini-stat">
                    <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                    <div><strong>48%</strong><span>Web</span></div>
                </div>
                <div class="mini-stat">
                    <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-qr"></use></svg></span>
                    <div><strong>31%</strong><span>Kiosco</span></div>
                </div>
                <div class="mini-stat">
                    <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span>
                    <div><strong>21%</strong><span>Recepción</span></div>
                </div>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Caja</span>
            <h2>Ingresos del periodo</h2>
            <p style="font-size:28px;font-weight:700;margin:6px 0 2px;">Bs 18 420</p>
            <p>De 302 pagos registrados (efectivo y tarjeta).</p>
        </section>
    </aside>
</div>

<section class="card" aria-labelledby="reporte-medico-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Ocupación</span>
            <h2 id="reporte-medico-title">Citas y asistencia por médico</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Médico</th>
                    <th>Citas</th>
                    <th>% Asistencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($porMedico as $fila): ?>
                    <tr>
                        <td><strong><?= e($fila['nombre']) ?></strong></td>
                        <td><?= e((string) $fila['citas']) ?></td>
                        <td>
                            <span class="badge <?= $fila['asistencia'] >= 90 ? 'badge-pine' : 'badge-info' ?>"><?= e((string) $fila['asistencia']) ?>%</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
