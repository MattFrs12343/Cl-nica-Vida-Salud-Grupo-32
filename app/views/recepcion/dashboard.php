<?php
/** @var array<string, mixed> $user */
$nombre = (string) ($user['nombre'] ?? 'Recepción');
$rol = (string) ($user['rol'] ?? 'recepcionista');
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$esRecepcionMedico = $rol === 'recepcionista_medico';
$medicoIds = array_values(array_filter(array_map('intval', (array) ($user['medico_ids'] ?? []))));
$diasSemana = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fechaHoy = $diasSemana[(int) date('w')] . ' ' . date('j') . ' de ' . $meses[(int) date('n')];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Recepción · <?= e(ucfirst($fechaHoy)) ?></span>
        <h1><?= e($saludo) ?>, <em><?= e(explode(' ', trim($nombre))[0] ?? 'equipo') ?>.</em></h1>
        <p data-today-summary>Estamos cargando la agenda de hoy.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/recepcion/fichas')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
            Buscar ficha
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/recepcion/citas?nuevo=1')) ?>">
            Nueva cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </a>
    </div>
</header>

<section class="kpi-grid kpi-grid-5" aria-label="Indicadores de hoy" data-today-kpis>
    <?php foreach ([
        ['total', 'Citas de hoy', 'calendar', 'primary'],
        ['programadas', 'Por confirmar', 'clock', 'neutral'],
        ['confirmadas', 'En espera', 'check-circle', 'info'],
        ['atendidas', 'Atendidas', 'stethoscope', 'success'],
        ['inasistencias', 'Inasistencias', 'x-circle', 'danger'],
    ] as [$clave, $etiqueta, $icono, $tono]): ?>
        <article class="kpi-card tone-<?= e($tono) ?>">
            <div class="kpi-top">
                <span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg></span>
                <span class="kpi-label"><?= e($etiqueta) ?></span>
            </div>
            <strong class="kpi-value" data-today-kpi="<?= e($clave) ?>">
                <span class="skeleton skeleton-value"></span>
            </strong>
            <span class="kpi-foot">Agenda de hoy</span>
        </article>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="agenda-hoy-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">En vivo</span>
                <h2 id="agenda-hoy-title">Agenda de hoy</h2>
            </div>
            <a class="text-link" href="<?= e(url('/recepcion/agenda')) ?>">
                Ver agenda completa
                <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
            </a>
        </div>
        <div class="table-wrap" data-today-agenda>
            <div class="table-loading">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="skeleton skeleton-row"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Atajos</span>
                    <h2>¿Qué necesitas?</h2>
                </div>
            </div>
            <div class="quick-actions">
                <a class="quick-action" href="<?= e(url('/recepcion/pacientes?nuevo=1')) ?>">
                    <span class="quick-icon tone-primary" aria-hidden="true"><svg class="icon"><use href="#i-user-plus"></use></svg></span>
                    <span><strong>Nuevo paciente</strong><small>Alta y datos clínicos</small></span>
                </a>
                <a class="quick-action" href="<?= e(url('/recepcion/citas?nuevo=1')) ?>">
                    <span class="quick-icon tone-accent" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
                    <span><strong>Agendar cita</strong><small>Buscar cupo disponible</small></span>
                </a>
                <a class="quick-action" href="<?= e(url('/recepcion/pagos?nuevo=1')) ?>">
                    <span class="quick-icon tone-success" aria-hidden="true"><svg class="icon"><use href="#i-card"></use></svg></span>
                    <span><strong>Registrar pago</strong><small>Cobro de la consulta</small></span>
                </a>
                <a class="quick-action" href="<?= e(url('/recepcion/reconsultas')) ?>">
                    <span class="quick-icon tone-info" aria-hidden="true"><svg class="icon"><use href="#i-refresh"></use></svg></span>
                    <span><strong>Reconsulta</strong><small>Seguimiento o ausencia</small></span>
                </a>
            </div>
        </section>

        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Recientes</span>
                    <h2>Pacientes</h2>
                </div>
                <a class="text-link" href="<?= e(url('/recepcion/pacientes')) ?>">Ver todos</a>
            </div>
            <div class="recent-patients" data-recent-patients>
                <div class="table-loading">
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                </div>
            </div>
        </section>

        <section class="card <?= $esRecepcionMedico ? 'card-scope' : 'card-accent' ?>">
            <?php if ($esRecepcionMedico): ?>
                <span class="card-kicker">Mi alcance</span>
                <h2>Trabajas con <?= count($medicoIds) ?> médico<?= count($medicoIds) === 1 ? '' : 's' ?></h2>
                <p>La agenda y los pacientes que ves están limitados a tus médicos asignados. Las acciones se validan también en el servidor.</p>
                <span class="badge badge-pine"><svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg> Alcance restringido</span>
            <?php else: ?>
                <span class="card-kicker">Recepción general</span>
                <h2>Toda la clínica, a la vista</h2>
                <p>Puedes gestionar la agenda de todos los médicos activos y registrar pacientes nuevos.</p>
                <a class="btn btn-light btn-sm" href="<?= e(url('/recepcion/pacientes')) ?>">
                    Ir a pacientes
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
                </a>
            <?php endif; ?>
        </section>
    </aside>
</div>

