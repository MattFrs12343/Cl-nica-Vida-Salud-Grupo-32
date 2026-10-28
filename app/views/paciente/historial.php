<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Mi salud</span>
        <h1>Mi historial</h1>
        <p>Revisa tus atenciones, diagnósticos, indicaciones y signos vitales registrados.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-historial-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/portal/mis-citas')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            Mis citas
        </a>
    </div>
</header>

<section class="card" aria-labelledby="historial-profile-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Mi ficha</span>
            <h2 id="historial-profile-title">Datos del paciente</h2>
        </div>
        <a class="text-link" href="<?= e(url('/portal/perfil')) ?>">Editar mis datos</a>
    </div>
    <div data-historial-profile>
        <div class="table-loading">
            <div class="skeleton skeleton-row"></div>
            <div class="skeleton skeleton-row"></div>
        </div>
    </div>
</section>

<section class="card" aria-labelledby="historial-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Evolución</span>
            <h2 id="historial-title">Atenciones registradas</h2>
        </div>
        <span class="badge badge-muted" data-historial-count>0 atenciones</span>
    </div>

    <div data-historial-list>
        <div class="table-loading">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
</section>
