<?php
/** @var string $title */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Clínica Vida Salud') ?></title>
    <style>
        :root {
            --blue: #0a6ebd;
            --teal: #0f9d8a;
            --ink: #102a43;
            --muted: #627d98;
            --bg: #f4f8fb;
            --card: #ffffff;
            --line: #d9e2ec;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 10% 10%, rgba(10, 110, 189, .12), transparent 28rem),
                radial-gradient(circle at 90% 0%, rgba(15, 157, 138, .10), transparent 24rem),
                var(--bg);
            display: grid;
            place-items: center;
            padding: 2rem;
        }
        main { width: min(760px, 100%); }
        .brand { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.5rem; }
        .logo {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, var(--blue), var(--teal));
            display: grid; place-items: center; color: white; font-weight: 800;
            box-shadow: 0 8px 20px rgba(10, 110, 189, .25);
        }
        .brand strong { display: block; font-size: 1.05rem; }
        .brand span { color: var(--muted); font-size: .85rem; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 18px 50px rgba(16, 42, 67, .08);
        }
        .badge {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .35rem .7rem; border-radius: 999px;
            background: rgba(15, 157, 138, .10); color: #0b6b5e;
            font-size: .78rem; font-weight: 700; letter-spacing: .02em;
        }
        .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--teal); }
        h1 { margin: 1rem 0 .5rem; font-size: clamp(1.8rem, 4vw, 2.5rem); line-height: 1.1; }
        p { color: var(--muted); line-height: 1.6; margin: 0 0 1.25rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; margin-top: 1.5rem; }
        a {
            display: block; padding: 1rem; border: 1px solid var(--line); border-radius: 14px;
            color: var(--ink); text-decoration: none; background: #fbfdff; transition: .18s ease;
        }
        a:hover { border-color: var(--blue); transform: translateY(-2px); box-shadow: 0 10px 24px rgba(16, 42, 67, .08); }
        a strong { display: block; margin-bottom: .2rem; }
        a span { color: var(--muted); font-size: .85rem; }
        footer { margin-top: 1.25rem; color: var(--muted); font-size: .8rem; text-align: center; }
    </style>
</head>
<body>
<main>
    <div class="brand">
        <div class="logo">VS</div>
        <div>
            <strong>Clínica Vida Salud</strong>
            <span>Sistema de gestión de citas médicas</span>
        </div>
    </div>

    <section class="card">
        <span class="badge"><span class="dot"></span> Backend base listo</span>
        <h1>Fase 0 en marcha</h1>
        <p>
            El esqueleto PHP, el router, la sesión, la base de datos y los contratos de integración
            están preparados. La landing, el kiosco y los paneles se construyen en las siguientes fases.
        </p>

        <div class="grid">
            <a href="<?= e(url('/health')) ?>">
                <strong>Health check</strong>
                <span>Estado de la aplicación (HTML)</span>
            </a>
            <a href="<?= e(url('/api/health')) ?>">
                <strong>API health</strong>
                <span>Respuesta JSON del backend</span>
            </a>
            <a href="<?= e(url('/api/csrf')) ?>">
                <strong>Token CSRF</strong>
                <span>Para pruebas del panel</span>
            </a>
            <a href="<?= e(url('/login')) ?>">
                <strong>Login</strong>
                <span>Se implementa en la Fase 1</span>
            </a>
        </div>
    </section>

    <footer>PHP <?= e(PHP_VERSION) ?> · PostgreSQL 16 · <?= e(date('d/m/Y H:i')) ?></footer>
</main>
</body>
</html>
