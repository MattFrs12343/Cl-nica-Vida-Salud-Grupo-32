<?php
/** @var int $status */
/** @var string $message */
?>
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Error <?= e($status ?? 404) ?></span>
        <h1>Página no encontrada</h1>
        <p><?= e($message ?? 'La página que buscas no existe o cambió de lugar.') ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="empty-state">
            <strong>¿Qué puedes hacer?</strong>
            <p>Revisa la dirección, vuelve al inicio o agenda tu cita desde la landing.</p>
            <div class="hero-actions" style="justify-content:center">
                <a class="button button-primary" href="<?= e(url('/')) ?>">Volver al inicio</a>
                <a class="button button-outline" href="<?= e(url('/reservar')) ?>">Agendar cita</a>
            </div>
        </div>
    </div>
</section>
