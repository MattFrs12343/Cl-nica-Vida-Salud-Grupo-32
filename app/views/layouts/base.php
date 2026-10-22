<?php
/** @var string $title */
/** @var string $content */
/** @var string|null $description */
/** @var string|null $canonical */
/** @var string|null $ogImage */
/** @var string|null $bodyClass */

$usuario = auth();
$descripcion = $description ?? 'Clínica Vida Salud — agenda tu cita médica online.';
$canonicalUrl = $canonical ?? url('/');
$imagenOg = $ogImage ?? asset('landing/img/og-default.svg');
$claseBody = trim('site ' . ($bodyClass ?? ''));
$mensajes = flashes();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Clínica Vida Salud') ?></title>
    <meta name="description" content="<?= e($descripcion) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta name="theme-color" content="#0a6ebd">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_BO">
    <meta property="og:site_name" content="Clínica Vida Salud">
    <meta property="og:title" content="<?= e($title ?? 'Clínica Vida Salud') ?>">
    <meta property="og:description" content="<?= e($descripcion) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($imagenOg) ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="<?= e(asset('landing/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('landing/css/landing.css')) ?>">
</head>
<body class="<?= e($claseBody) ?>">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <header class="site-header" data-site-header>
        <div class="container header-inner">
            <a class="brand" href="<?= e(url('/')) ?>" aria-label="Clínica Vida Salud, inicio">
                <span class="brand-mark" aria-hidden="true">VS</span>
                <span class="brand-text">
                    <strong>Clínica Vida Salud</strong>
                    <small>Cuidamos de ti y tu familia</small>
                </span>
            </a>

            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-principal" data-nav-toggle>
                <span class="sr-only">Abrir menú</span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>

            <nav class="site-nav" id="menu-principal" data-site-nav aria-label="Navegación principal">
                <a href="<?= e(url('/#especialidades')) ?>">Especialidades</a>
                <a href="<?= e(url('/#medicos')) ?>">Médicos</a>
                <a href="<?= e(url('/#nosotros')) ?>">Nosotros</a>
                <a href="<?= e(url('/#contacto')) ?>">Contacto</a>
                <a href="<?= e(url('/kiosco')) ?>">Kiosco</a>

                <?php if ($usuario !== null): ?>
                    <a class="button button-ghost" href="<?= e(url(role_home((string) $usuario['rol']))) ?>">
                        Mi panel
                    </a>
                <?php else: ?>
                    <a class="button button-ghost" href="<?= e(url('/login')) ?>">Iniciar sesión</a>
                <?php endif; ?>

                <a class="button button-primary" href="<?= e(url('/reservar')) ?>">Agendar cita</a>
            </nav>
        </div>
    </header>

    <?php if ($mensajes !== []): ?>
        <div class="container flash-stack" role="status" aria-live="polite">
            <?php foreach ($mensajes as $mensaje): ?>
                <div class="flash flash-<?= e($mensaje['type']) ?>">
                    <?= e($mensaje['message']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main id="contenido">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="brand brand-footer" href="<?= e(url('/')) ?>">
                    <span class="brand-mark" aria-hidden="true">VS</span>
                    <span class="brand-text">
                        <strong>Clínica Vida Salud</strong>
                        <small>Medicina cercana, agenda simple.</small>
                    </span>
                </a>
            </div>

            <div>
                <h2 class="footer-title">Atención</h2>
                <ul class="footer-list">
                    <li>Lunes a viernes · 08:00–18:00</li>
                    <li>Sábados · 08:00–12:00</li>
                    <li><a href="<?= e(url('/kiosco')) ?>">Sacar ficha en el kiosco</a></li>
                </ul>
            </div>

            <div>
                <h2 class="footer-title">Contacto</h2>
                <ul class="footer-list">
                    <li><a href="tel:+59170000000">+591 700 00000</a></li>
                    <li><a href="mailto:contacto@vidasalud.test">contacto@vidasalud.test</a></li>
                    <li>Av. Principal #123, Santa Cruz</li>
                </ul>
            </div>

            <div>
                <h2 class="footer-title">Accesos</h2>
                <ul class="footer-list">
                    <li><a href="<?= e(url('/reservar')) ?>">Agendar cita</a></li>
                    <li><a href="<?= e(url('/activar')) ?>">Activar mi cuenta</a></li>
                    <li><a href="<?= e(url('/login')) ?>">Iniciar sesión</a></li>
                </ul>
            </div>
        </div>

        <div class="container footer-bottom">
            <span>© <?= e(date('Y')) ?> Clínica Vida Salud. Todos los derechos reservados.</span>
            <span>Hecho con cuidado para tu salud.</span>
        </div>
    </footer>

    <script src="<?= e(asset('landing/js/landing.js')) ?>" defer></script>
</body>
</html>
