<?php
/** @var string $title */
/** @var string $content */
/** @var string|null $description */
/** @var string|null $canonical */
/** @var string|null $ogImage */
/** @var string|null $bodyClass */

$usuario = auth();
$descripcion = $description ?? 'Clínica Vida Salud — medicina cercana y agenda simple en Santa Cruz.';
$canonicalUrl = $canonical ?? url('/');
$imagenOg = $ogImage ?? asset('landing/img/og-default.jpg');
$claseBody = trim('site ' . ($bodyClass ?? ''));
$mensajes = flashes();
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'MedicalClinic',
    'name' => 'Clínica Vida Salud',
    'url' => url('/'),
    'telephone' => '+591 700 00000',
    'email' => 'contacto@vidasalud.test',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Av. Principal #123',
        'addressLocality' => 'Santa Cruz de la Sierra',
        'addressCountry' => 'BO',
    ],
    'openingHoursSpecification' => [
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => '08:00',
            'closes' => '18:00',
        ],
        [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => 'Saturday',
            'opens' => '08:00',
            'closes' => '12:00',
        ],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Clínica Vida Salud') ?></title>
    <meta name="description" content="<?= e($descripcion) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta name="theme-color" content="#0B3D35">
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
    <link rel="preload" href="<?= e(asset('landing/fonts/bricolage-grotesque-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('landing/fonts/dm-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('landing/css/landing.css')) ?>">

    <script>document.documentElement.classList.add('js');</script>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
</head>
<body class="<?= e($claseBody) ?>" data-base-url="<?= e(url('/')) ?>">
    <a class="skip-link" href="#contenido">Saltar al contenido</a>

    <svg class="icon-sprite" aria-hidden="true" focusable="false">
        <symbol id="i-arrow-up-right" viewBox="0 0 24 24"><path d="M7 17 17 7M8 7h9v9"/></symbol>
        <symbol id="i-arrow-right" viewBox="0 0 24 24"><path d="M4 12h16M14 6l6 6-6 6"/></symbol>
        <symbol id="i-arrow-left" viewBox="0 0 24 24"><path d="M20 12H4M10 6l-6 6 6 6"/></symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="15" rx="3"/><path d="M8 3.5v4M16 3.5v4M3.5 10h17"/><path d="M8 14h2M14 14h2M8 17.5h2M14 17.5h2"/></symbol>
        <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></symbol>
        <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.2 4.2L19 7"/></symbol>
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4 4"/></symbol>
        <symbol id="i-phone" viewBox="0 0 24 24"><path d="M7.5 3.8 10 8.6 8.2 10a15 15 0 0 0 5.8 5.8l1.4-1.8 4.8 2.5-.5 2.3c-.2.8-.9 1.4-1.7 1.4C10.5 20.2 3.8 13.5 3.8 6c0-.8.6-1.5 1.4-1.7l2.3-.5Z"/></symbol>
        <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3.5" y="5.5" width="17" height="13" rx="3"/><path d="m4.5 7.5 7.5 5.5 7.5-5.5"/></symbol>
        <symbol id="i-pin" viewBox="0 0 24 24"><path d="M19 10.5c0 5-7 10-7 10s-7-5-7-10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10.5" r="2.5"/></symbol>
        <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></symbol>
        <symbol id="i-stethoscope" viewBox="0 0 24 24"><path d="M5 4v5a4 4 0 0 0 8 0V4"/><path d="M3.5 4h3M11.5 4h3"/><path d="M9 13v2.5a4.5 4.5 0 0 0 9 0V12"/><circle cx="18" cy="9.5" r="2.5"/></symbol>
        <symbol id="i-child" viewBox="0 0 24 24"><circle cx="12" cy="6.5" r="3"/><path d="M6.5 20v-3.5a5.5 5.5 0 0 1 11 0V20"/><path d="M9 13.5 7.5 20M15 13.5 16.5 20"/></symbol>
        <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20 8.6c0 5-8 10.4-8 10.4S4 13.6 4 8.6a4.1 4.1 0 0 1 7.2-2.7L12 6.7l.8-.8A4.1 4.1 0 0 1 20 8.6Z"/></symbol>
        <symbol id="i-female" viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="5"/><path d="M12 13.5V21M9 18h6"/></symbol>
        <symbol id="i-elderly" viewBox="0 0 24 24"><circle cx="10" cy="5.5" r="2.5"/><path d="M10 8.5v6M10 11l4 2M10 14.5 7 21M10 14.5 13 17l3 4"/><path d="M19 9v12"/></symbol>
        <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3.5c.7 4.7 3.3 7.3 8 8-4.7.7-7.3 3.3-8 8-.7-4.7-3.3-7.3-8-8 4.7-.7 7.3-3.3 8-8Z"/></symbol>
        <symbol id="i-ticket" viewBox="0 0 24 24"><path d="M4 6.5h16v4a2 2 0 0 0 0 3v4H4v-4a2 2 0 0 0 0-3v-4Z"/><path d="M9 6.5v11M13 10h3M13 14h3"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3.5 19 6v5.5c0 4.4-3 7.5-7 9-4-1.5-7-4.6-7-9V6l7-2.5Z"/><path d="m8.8 11.8 2.1 2.1 4.3-4.3"/></symbol>
        <symbol id="i-qr" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4z"/><path d="M14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/></symbol>
        <symbol id="i-whatsapp" viewBox="0 0 24 24"><path d="M20 11.7a8 8 0 0 1-11.8 7L4 20l1.3-4.1A8 8 0 1 1 20 11.7Z"/><path d="M9 8.5c.2 2.8 2.2 4.8 5 5l1-1.4 2 .8-.3 1.7c-.1.5-.6.9-1.1.9-3.9 0-7.1-3.2-7.1-7.1 0-.5.4-1 .9-1.1l1.7-.3.8 2L9.5 10Z"/></symbol>
        <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></symbol>
        <symbol id="i-chevron-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
        <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4.5" y="10" width="15" height="10.5" rx="3"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/></symbol>
    </svg>

    <header class="site-header" data-site-header>
        <div class="container nav-wrap">
            <div class="nav-pill">
                <a class="brand" href="<?= e(url('/')) ?>" aria-label="Clínica Vida Salud, inicio">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" focusable="false">
                            <rect width="40" height="40" rx="14" fill="currentColor"/>
                            <path d="M20 11.5v17M11.5 20h17" stroke="#F4F6F0" stroke-width="4.2" stroke-linecap="round"/>
                            <circle cx="30.5" cy="9.5" r="4" fill="#D95E3C"/>
                        </svg>
                    </span>
                    <span class="brand-type"><strong>vida</strong><em>salud</em></span>
                </a>

                <nav class="desktop-nav" aria-label="Navegación principal">
                    <a href="<?= e(url('/#especialidades')) ?>" data-nav-section="especialidades">Especialidades</a>
                    <a href="<?= e(url('/#medicos')) ?>" data-nav-section="medicos">Médicos</a>
                    <a href="<?= e(url('/#nosotros')) ?>" data-nav-section="nosotros">Nosotros</a>
                    <a href="<?= e(url('/#kiosco')) ?>" data-nav-section="kiosco">Kiosco</a>
                    <a href="<?= e(url('/#contacto')) ?>" data-nav-section="contacto">Contacto</a>
                </nav>

                <div class="nav-actions">
                    <?php if ($usuario !== null): ?>
                        <a class="nav-login" href="<?= e(url(role_home((string) $usuario['rol']))) ?>">
                            <svg class="icon" aria-hidden="true"><use href="#i-user"></use></svg>
                            Mi panel
                        </a>
                    <?php else: ?>
                        <a class="nav-login" href="<?= e(url('/login')) ?>">Ingresar</a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('/reservar')) ?>">
                        <span>Reservar cita</span>
                        <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
                    </a>
                </div>

                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menu-movil" data-nav-toggle>
                    <span class="sr-only">Abrir menú</span>
                    <span class="nav-toggle-lines" aria-hidden="true"><i></i><i></i></span>
                </button>
            </div>
        </div>
    </header>

    <div class="mobile-menu" id="menu-movil" data-mobile-menu aria-hidden="true">
        <div class="mobile-menu-head">
            <span class="mobile-menu-kicker">Clínica Vida Salud</span>
            <button class="mobile-menu-close" type="button" data-menu-close>
                <span class="sr-only">Cerrar menú</span>
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </div>

        <nav class="mobile-nav" aria-label="Navegación móvil">
            <a href="<?= e(url('/#especialidades')) ?>" style="--i:0" data-nav-section="especialidades">Especialidades</a>
            <a href="<?= e(url('/#medicos')) ?>" style="--i:1" data-nav-section="medicos">Médicos</a>
            <a href="<?= e(url('/#nosotros')) ?>" style="--i:2" data-nav-section="nosotros">Nosotros</a>
            <a href="<?= e(url('/#kiosco')) ?>" style="--i:3" data-nav-section="kiosco">Kiosco</a>
            <a href="<?= e(url('/#contacto')) ?>" style="--i:4" data-nav-section="contacto">Contacto</a>
        </nav>

        <div class="mobile-menu-foot">
            <?php if ($usuario !== null): ?>
                <a class="btn btn-ghost-light" href="<?= e(url(role_home((string) $usuario['rol']))) ?>">Mi panel</a>
            <?php else: ?>
                <a class="btn btn-ghost-light" href="<?= e(url('/login')) ?>">Ingresar</a>
            <?php endif; ?>
            <a class="btn btn-light" href="<?= e(url('/reservar')) ?>">
                <span>Reservar cita</span>
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-up-right"></use></svg></span>
            </a>
        </div>

        <div class="mobile-menu-contact">
            <a href="tel:+59170000000">+591 700 00000</a>
            <span>Lun a vie · 08:00–18:00</span>
        </div>
    </div>

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
        <div class="container footer-main">
            <div class="footer-brand">
                <a class="brand brand-footer" href="<?= e(url('/')) ?>" aria-label="Clínica Vida Salud, inicio">
                    <span class="brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" focusable="false">
                            <rect width="40" height="40" rx="14" fill="currentColor"/>
                            <path d="M20 11.5v17M11.5 20h17" stroke="#0B3D35" stroke-width="4.2" stroke-linecap="round"/>
                            <circle cx="30.5" cy="9.5" r="4" fill="#D95E3C"/>
                        </svg>
                    </span>
                    <span class="brand-type"><strong>vida</strong><em>salud</em></span>
                </a>
                <p class="footer-statement">Medicina cercana para las etapas que importan.</p>
                <a class="footer-cta" href="<?= e(url('/reservar')) ?>">
                    Reservar cita
                    <svg class="icon" aria-hidden="true"><use href="#i-arrow-up-right"></use></svg>
                </a>
            </div>

            <div class="footer-col">
                <h2 class="footer-title">Explorar</h2>
                <a href="<?= e(url('/#especialidades')) ?>">Especialidades</a>
                <a href="<?= e(url('/#medicos')) ?>">Médicos</a>
                <a href="<?= e(url('/#nosotros')) ?>">Nosotros</a>
                <a href="<?= e(url('/#noticias')) ?>">Campañas</a>
            </div>

            <div class="footer-col">
                <h2 class="footer-title">Atención</h2>
                <a href="<?= e(url('/reservar')) ?>">Reserva online</a>
                <a href="<?= e(url('/kiosco')) ?>">Kiosco de fichas</a>
                <a href="<?= e(url('/activar')) ?>">Activar mi cuenta</a>
                <a href="<?= e(url('/login')) ?>">Iniciar sesión</a>
            </div>

            <div class="footer-col footer-col-contact">
                <h2 class="footer-title">Visítanos</h2>
                <p>Av. Principal #123<br>Santa Cruz de la Sierra</p>
                <a href="tel:+59170000000">+591 700 00000</a>
                <a href="mailto:contacto@vidasalud.test">contacto@vidasalud.test</a>
                <p class="footer-hours">Lun a vie 08:00–18:00<br>Sáb 08:00–12:00</p>
            </div>
        </div>

        <div class="footer-wordmark" aria-hidden="true">vida salud</div>

        <div class="container footer-bottom">
            <span>© <?= e(date('Y')) ?> Clínica Vida Salud</span>
            <span>Santa Cruz de la Sierra · Bolivia</span>
            <span>Fotografías: Unsplash</span>
        </div>
    </footer>

    <noscript>
        <style>.reveal{opacity:1!important;transform:none!important}</style>
    </noscript>
    <script src="<?= e(asset('landing/js/landing.js')) ?>" defer></script>
</body>
</html>
