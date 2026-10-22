<?php
/** @var int $status */
/** @var string $message */
?>
<section class="not-found">
    <div class="container">
        <span class="error-code" aria-hidden="true"><?= e($status ?? 404) ?></span>
        <h1>Esta página no está aquí.</h1>
        <p><?= e($message ?? 'La página que buscas no existe o cambió de lugar.') ?></p>

        <div class="not-found-actions">
            <a class="btn btn-primary" href="<?= e(url('/')) ?>">
                <span>Volver al inicio</span>
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
            </a>
            <a class="btn btn-ghost" href="<?= e(url('/reservar')) ?>">Agendar cita</a>
        </div>

        <span class="not-found-note">Clínica Vida Salud · Santa Cruz</span>
    </div>
</section>
