<?php
/**
 * Layout de autenticación.
 *
 * @var string $title
 * @var string $content
 * @var array<int, array{type: string, message: string}> $flashes
 */

$mensajes = $flashes ?? [];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Clínica Vida Salud</title>
    <meta name="theme-color" content="#0B3D35">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('landing/img/favicon.svg')) ?>">
    <link rel="preload" href="<?= e(asset('landing/fonts/bricolage-grotesque-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('landing/fonts/dm-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('landing/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('panel/css/panel.css')) ?>">
</head>
<body class="auth-body" data-page="auth">
<a class="skip-link" href="#auth-form">Saltar al formulario</a>
<?php require app_path('views/partials/panel/icons.php'); ?>

<main class="auth-shell">
    <section class="auth-visual" aria-label="Clínica Vida Salud">
        <div class="auth-visual-bg" style="background-image: url('<?= e(asset('landing/img/hero-doctor.jpg')) ?>')"></div>
        <div class="auth-visual-content">
            <a class="auth-brand" href="<?= e(url('/')) ?>" aria-label="Clínica Vida Salud, inicio">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" focusable="false">
                        <rect width="40" height="40" rx="14" fill="currentColor"/>
                        <path d="M20 11.5v17M11.5 20h17" stroke="#0B3D35" stroke-width="4.2" stroke-linecap="round"/>
                        <circle cx="30.5" cy="9.5" r="4" fill="#D95E3C"/>
                    </svg>
                </span>
                <span class="brand-type"><strong>vida</strong><em>salud</em></span>
            </a>

            <div class="auth-visual-copy">
                <span class="auth-kicker">Panel interno · Santa Cruz</span>
                <h1>La atención empieza<br><em>mucho antes</em> del consultorio.</h1>
                <p>Agenda, pacientes y fichas en un mismo lugar. Menos fricción para tu equipo, más tiempo para las personas.</p>
            </div>

            <div class="auth-visual-foot">
                <div class="auth-proof">
                    <span class="auth-proof-icon" aria-hidden="true">
                        <svg class="icon"><use href="#i-shield"></use></svg>
                    </span>
                    <div>
                        <strong>Acceso protegido</strong>
                        <p>Sesión cifrada y roles por área.</p>
                    </div>
                </div>
                <a class="auth-site-link" href="<?= e(url('/')) ?>">
                    Volver al sitio
                    <svg class="icon" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
                </a>
            </div>
        </div>
    </section>

    <section class="auth-panel" aria-labelledby="auth-title">
        <div class="auth-panel-top">
            <a class="auth-mobile-brand" href="<?= e(url('/')) ?>">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" focusable="false">
                        <rect width="40" height="40" rx="14" fill="currentColor"/>
                        <path d="M20 11.5v17M11.5 20h17" stroke="#0B3D35" stroke-width="4.2" stroke-linecap="round"/>
                        <circle cx="30.5" cy="9.5" r="4" fill="#D95E3C"/>
                    </svg>
                </span>
                <span class="brand-type"><strong>vida</strong><em>salud</em></span>
            </a>
            <a class="auth-back" href="<?= e(url('/')) ?>">
                <svg class="icon" aria-hidden="true"><use href="#i-arrow-left"></use></svg>
                Ir al sitio
            </a>
        </div>

        <div class="auth-card" id="auth-form" tabindex="-1">
            <?php if ($mensajes !== []): ?>
                <div class="auth-flashes" role="status" aria-live="polite">
                    <?php foreach ($mensajes as $mensaje): ?>
                        <?php
                        $tipo = (string) ($mensaje['type'] ?? 'info');
                        $icono = match ($tipo) {
                            'success' => 'check-circle',
                            'error', 'danger' => 'x-circle',
                            'warning' => 'alert',
                            default => 'info',
                        };
                        ?>
                        <div class="panel-flash flash-<?= e($tipo) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#i-<?= e($icono) ?>"></use></svg>
                            <span><?= e($mensaje['message'] ?? '') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>

        <p class="auth-legal">
            Uso exclusivo del personal autorizado. Si necesitas ayuda, escribe a
            <a href="mailto:soporte@vidasalud.test">soporte@vidasalud.test</a>.
        </p>
    </section>
</main>

<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="false"></div>
<script type="module" src="<?= e(asset('panel/js/auth.js')) ?>"></script>
</body>
</html>
