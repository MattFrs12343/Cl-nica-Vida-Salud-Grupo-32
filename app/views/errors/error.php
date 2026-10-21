<?php
/** @var int $status */
/** @var string $message */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Error') . ' · Clínica Vida Salud') ?></title>
    <style>
        :root { --blue: #0a6ebd; --ink: #102a43; --muted: #627d98; --line: #d9e2ec; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--ink); background: #f4f8fb; padding: 2rem;
        }
        .card {
            width: min(560px, 100%); background: #fff; border: 1px solid var(--line);
            border-radius: 20px; padding: 2.25rem; box-shadow: 0 18px 50px rgba(16, 42, 67, .08);
            text-align: center;
        }
        .code {
            display: inline-grid; place-items: center; width: 74px; height: 74px;
            border-radius: 20px; background: rgba(10, 110, 189, .10); color: var(--blue);
            font-size: 1.35rem; font-weight: 800; margin-bottom: 1.25rem;
        }
        h1 { margin: 0 0 .6rem; font-size: 1.5rem; }
        p { color: var(--muted); line-height: 1.6; margin: 0 0 1.5rem; }
        a {
            display: inline-block; padding: .75rem 1.2rem; border-radius: 12px;
            background: linear-gradient(135deg, var(--blue), #0f9d8a); color: #fff;
            text-decoration: none; font-weight: 700;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="code"><?= e($status ?? 500) ?></div>
        <h1><?= e($title ?? 'Algo salió mal') ?></h1>
        <p><?= e($message ?? 'Ocurrió un error inesperado.') ?></p>
        <a href="<?= e(url('/')) ?>">Volver al inicio</a>
    </main>
</body>
</html>
