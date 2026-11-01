<?php
/** @var string $content */
/** @var string|null $title */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Documento — Clínica Vida Salud') ?></title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            color: #16241f;
            background: #f4f6f0;
        }
        .hoja {
            max-width: 760px;
            margin: 2rem auto;
            background: #fff;
            padding: 2.25rem 2.5rem;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
        }
        .hoja-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            border-bottom: 2px solid #0b3d35;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .hoja-marca { display: flex; align-items: center; gap: .6rem; }
        .hoja-marca svg { width: 36px; height: 36px; }
        .hoja-marca strong { font-size: 1.1rem; color: #0b3d35; }
        .hoja-tipo {
            text-align: right;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6c7a70;
        }
        .hoja-tipo strong { display: block; font-size: 1rem; color: #16241f; letter-spacing: normal; text-transform: none; }
        .hoja-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem 1.5rem; margin-bottom: 1.5rem; }
        .hoja-meta div { font-size: .9rem; }
        .hoja-meta dt { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c7a70; margin: 0; }
        .hoja-meta dd { margin: 0; font-weight: 600; }
        .hoja-seccion { margin-bottom: 1.25rem; }
        .hoja-seccion h2 { font-size: .95rem; color: #0b3d35; margin: 0 0 .5rem; }
        table.hoja-tabla { width: 100%; border-collapse: collapse; font-size: .9rem; }
        table.hoja-tabla th, table.hoja-tabla td {
            text-align: left; padding: .5rem .6rem; border-bottom: 1px solid #e4e8df;
        }
        .hoja-codigos {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 2rem; padding-top: 1rem; border-top: 1px dashed #c7cec0;
            font-size: .85rem; color: #6c7a70;
        }
        .hoja-codigos strong { color: #16241f; }
        .hoja-acciones { text-align: center; margin: 1.25rem auto 0; max-width: 760px; }
        .hoja-acciones button {
            font: inherit; padding: .55rem 1.4rem; border-radius: .6rem; border: none;
            background: #0b3d35; color: #fff; cursor: pointer;
        }
        .hoja-acciones button:focus-visible {
            outline: 2px solid #d95e3c; outline-offset: 3px;
        }
        @media (max-width: 600px) {
            .hoja { margin: 0; padding: 1.25rem 1rem; border-radius: 0; }
            .hoja-head { flex-direction: column; gap: .75rem; }
            .hoja-tipo { text-align: left; }
            .hoja-meta { grid-template-columns: minmax(0, 1fr); }
            .hoja-codigos { flex-direction: column; align-items: flex-start; gap: .35rem; }
            table.hoja-tabla { font-size: .82rem; }
            table.hoja-tabla th, table.hoja-tabla td { padding: .4rem; }
            .hoja-acciones { padding: 0 1rem; }
        }
        @page { size: A4 portrait; margin: 14mm; }
        @media print {
            html, body { background: #fff !important; }
            body {
                padding: 0;
                color: #111;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .hoja {
                box-shadow: none; margin: 0; max-width: none;
                padding: 0; border-radius: 0;
            }
            .hoja-head { border-bottom-color: #0b3d35 !important; }
            .hoja-acciones { display: none !important; }
            .hoja-seccion { break-inside: avoid; page-break-inside: avoid; }
            table.hoja-tabla thead { display: table-header-group; }
            table.hoja-tabla tr { break-inside: avoid; page-break-inside: avoid; }
            table.hoja-tabla th,
            table.hoja-tabla td { border-bottom-color: #ccc !important; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="hoja">
        <?= $content ?>
    </div>
    <div class="hoja-acciones">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
</body>
</html>
