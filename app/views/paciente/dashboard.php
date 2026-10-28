<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Mi espacio</span>
        <h1>Hola, <em data-patient-first-name>paciente</em>.</h1>
        <p data-patient-meta>Tu salud, tus citas y tus documentos, siempre a mano.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-patient-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/portal/mis-citas')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            Mis citas
        </a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('/reservar')) ?>">
            Reservar cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        </a>
    </div>
</header>

<section class="kpi-grid kpi-grid-4" aria-label="Resumen de tu salud" data-patient-kpis>
    <?php foreach ([
        ['proximas', 'Próximas citas', 'calendar', 'primary'],
        ['atendidas', 'Citas atendidas', 'check-circle', 'success'],
        ['consultas', 'Atenciones registradas', 'file', 'info'],
        ['total', 'Citas en total', 'clock', 'accent'],
    ] as [$clave, $etiqueta, $icono, $tono]): ?>
        <article class="kpi-card tone-<?= e($tono) ?>">
            <div class="kpi-top">
                <span class="kpi-icon" aria-hidden="true"><svg class="icon"><use href="#i-<?= e($icono) ?>"></use></svg></span>
                <span class="kpi-label"><?= e($etiqueta) ?></span>
            </div>
            <strong class="kpi-value" data-patient-kpi="<?= e($clave) ?>">
                <span class="skeleton skeleton-value"></span>
            </strong>
            <span class="kpi-foot">Hoy · <span data-patient-date><?= e(date('d/m/Y')) ?></span></span>
        </article>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="patient-appointments-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Agenda personal</span>
                <h2 id="patient-appointments-title" data-patient-appointments-title>Tus próximas citas</h2>
            </div>
            <a class="text-link" href="<?= e(url('/portal/mis-citas')) ?>">
                Ver todas
                <svg class="icon" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
            </a>
        </div>
        <div data-patient-appointments>
            <div class="table-loading">
                <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="skeleton skeleton-row"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="card" aria-labelledby="patient-next-title">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Ahora</span>
                    <h2 id="patient-next-title">Siguiente cita</h2>
                </div>
            </div>
            <div data-patient-next>
                <div class="table-loading">
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="patient-profile-title">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Mi ficha</span>
                    <h2 id="patient-profile-title">Mis datos</h2>
                </div>
                <a class="text-link" href="<?= e(url('/portal/perfil')) ?>">Editar</a>
            </div>
            <div data-patient-profile>
                <div class="table-loading">
                    <div class="skeleton skeleton-row"></div>
                    <div class="skeleton skeleton-row"></div>
                </div>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Mi historial</span>
            <h2>Consulta tus atenciones</h2>
            <p>Revisa diagnósticos, indicaciones y signos vitales de tus consultas anteriores.</p>
            <a class="btn btn-light btn-sm" href="<?= e(url('/portal/historial')) ?>">
                Abrir historial
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
            </a>
        </section>
    </aside>
</div>
