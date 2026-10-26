<?php
/**
 * Layout del kiosco táctil. Es una pantalla completa, sin la navegación del
 * sitio público, pensada para iPad horizontal (1024×768) y pantallas táctiles.
 *
 * @var string $title
 * @var string $content
 * @var array{token: string, expires_in: int} $kiosk
 */
$token = (string) ($kiosk['token'] ?? '');
$ttl = (int) ($kiosk['expires_in'] ?? 600);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b3d35">
    <meta name="robots" content="noindex, nofollow">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= e($title) ?></title>
    <link rel="icon" href="<?= e(asset('landing/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preload" href="<?= e(asset('landing/fonts/bricolage-grotesque-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(asset('landing/fonts/dm-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('kiosco/css/kiosco.css')) ?>">
</head>
<body class="kiosk-body">
    <div
        id="kiosk-app"
        class="kiosk-app"
        data-token="<?= e($token) ?>"
        data-token-ttl="<?= $ttl ?>"
        data-token-url="<?= e(url('/kiosco/token')) ?>"
        data-api-base="<?= e(url('/api/kiosco')) ?>"
        data-img-base="<?= e(asset('kiosco/img')) ?>"
        data-demo-ci="10000001"
    >
        <?= $content ?>
    </div>

    <noscript>
        <div class="kiosk-noscript">Activa JavaScript para usar el kiosco de fichas.</div>
    </noscript>

    <script src="<?= e(asset('kiosco/vendor/qrcode.js')) ?>"></script>
    <script src="<?= e(asset('kiosco/js/kiosco.js')) ?>"></script>
</body>
</html>
