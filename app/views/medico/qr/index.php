<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Mi QR</h1>
        <p>Tu credencial visual: recepción y el kiosco la muestran junto a tu nombre.</p>
    </div>
</header>

<div class="dashboard-grid">
    <section class="card" aria-labelledby="qr-preview-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Vista previa</span>
                <h2 id="qr-preview-title">Tu código QR</h2>
            </div>
            <span class="status status-activo">Cargado</span>
        </div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:16px;padding:12px 0 4px;">
            <div style="width:180px;height:180px;border-radius:16px;border:1px solid var(--panel-line);background:#fff;display:grid;place-items:center;">
                <svg class="icon" aria-hidden="true" style="width:96px;height:96px;color:var(--panel-ink,#16241f);"><use href="#i-qr"></use></svg>
            </div>
            <div style="text-align:center;">
                <strong style="display:block;font-size:15px;">Dr. Carlos Méndez</strong>
                <small style="color:var(--panel-muted);">MED-DEMO-001 · Medicina General</small>
            </div>
            <div class="row-actions">
                <button class="btn btn-ghost btn-sm" type="button">
                    <svg class="icon" aria-hidden="true"><use href="#i-download"></use></svg>
                    Descargar
                </button>
                <button class="btn btn-ghost btn-sm" type="button">
                    <svg class="icon" aria-hidden="true"><use href="#i-print"></use></svg>
                    Imprimir
                </button>
            </div>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Actualizar</span>
                    <h2>Subir un nuevo QR</h2>
                </div>
            </div>
            <div style="border:1.5px dashed var(--panel-line-strong);border-radius:12px;padding:24px;text-align:center;">
                <svg class="icon" aria-hidden="true" style="width:28px;height:28px;color:var(--panel-muted);"><use href="#i-qr"></use></svg>
                <p style="margin:10px 0 14px;font-size:12.5px;color:var(--panel-muted);">PNG o JPG, máximo 1 MB</p>
                <button class="btn btn-primary btn-sm" type="button">
                    Elegir archivo
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
                </button>
            </div>
        </section>

        <section class="callout callout-info">
            <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
            <p>Solo tú puedes subir o cambiar tu QR. Recepción y el kiosco lo muestran junto a tus datos, pero no pueden editarlo.</p>
        </section>
    </aside>
</div>
