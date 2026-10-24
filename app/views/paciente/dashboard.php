<?php
/** @var array<string, mixed> $user */
$nombre = (string) ($user['nombre'] ?? 'Paciente');
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Mi espacio</span>
        <h1>Hola, <em><?= e(explode(' ', trim($nombre))[0] ?? 'paciente') ?>.</em></h1>
        <p>Tu salud, tus citas y tus documentos, siempre a mano.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary btn-sm" href="<?= e(url('/reservar')) ?>">
            Reservar cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        </a>
    </div>
</header>

<div class="portal-hero card">
    <div class="portal-hero-copy">
        <span class="card-kicker">Portal del paciente</span>
        <h2>Tu próxima cita, sin llamadas.</h2>
        <p>Revisa tus horarios, tu historial y actualiza tus datos de contacto cuando lo necesites.</p>
        <div class="portal-hero-actions">
            <a class="btn btn-primary btn-sm" href="<?= e(url('/portal/mis-citas')) ?>">
                Ver mis citas
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
            </a>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/portal/perfil')) ?>">Mi perfil</a>
        </div>
    </div>
    <span class="portal-hero-mark" aria-hidden="true">
        <svg class="icon"><use href="#i-heart"></use></svg>
    </span>
</div>

<div class="quick-grid quick-grid-3">
    <a class="quick-card" href="<?= e(url('/portal/mis-citas')) ?>">
        <span class="quick-icon tone-primary" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span>
        <div><strong>Mis citas</strong><p>Próximas atenciones y estado de cada reserva.</p></div>
        <svg class="icon quick-arrow" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
    </a>
    <a class="quick-card" href="<?= e(url('/portal/historial')) ?>">
        <span class="quick-icon tone-success" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
        <div><strong>Mi historial</strong><p>Atenciones anteriores, indicaciones y recetas.</p></div>
        <svg class="icon quick-arrow" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
    </a>
    <a class="quick-card" href="<?= e(url('/portal/perfil')) ?>">
        <span class="quick-icon tone-accent" aria-hidden="true"><svg class="icon"><use href="#i-user"></use></svg></span>
        <div><strong>Mis datos</strong><p>Contacto, emergencia y preferencias de atención.</p></div>
        <svg class="icon quick-arrow" aria-hidden="true"><use href="#i-arrow-right"></use></svg>
    </a>
</div>

<section class="card">
    <div class="empty-state">
        <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-spark"></use></svg></span>
        <h2>Estamos preparando tu portal</h2>
        <p>La reserva web y el kiosco ya funcionan. En la siguiente entrega podrás ver tus citas y tu historia clínica desde aquí.</p>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/reservar')) ?>">Reservar una cita mientras tanto</a>
    </div>
</section>
