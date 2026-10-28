<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Mi QR</h1>
        <p>Tu credencial visual: recepción y el kiosco la muestran junto a tu nombre.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-qr-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
    </div>
</header>

<div class="dashboard-grid">
    <section class="card qr-card" aria-labelledby="qr-preview-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Vista previa</span>
                <h2 id="qr-preview-title">Tu código QR</h2>
            </div>
            <span class="status status-activo">Credencial</span>
        </div>
        <div class="qr-preview-body">
            <div class="qr-preview-frame">
                <img class="qr-image" src="" alt="Código QR del médico" data-qr-image hidden>
                <div class="qr-empty" data-qr-empty>
                    <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
                    <p>Aún no has subido tu QR.</p>
                </div>
            </div>
            <div class="qr-copy">
                <strong class="qr-name" data-qr-name>Dr(a). Médico</strong>
                <small class="qr-meta" data-qr-code>—</small>
                <small class="qr-meta" data-qr-specialties>Sin especialidad asignada</small>
                <small class="qr-meta" data-qr-meta></small>
            </div>
            <div class="row-actions qr-actions">
                <a class="btn btn-ghost btn-sm" href="#" data-qr-download hidden>
                    <svg class="icon" aria-hidden="true"><use href="#i-download"></use></svg>
                    Descargar
                </a>
                <button class="btn btn-ghost btn-sm" type="button" data-qr-print hidden>
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
            <form class="qr-form" data-qr-form novalidate>
                <label class="upload-zone qr-upload-zone" for="qr-file">
                    <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
                    <span class="upload-title">Elegir archivo</span>
                    <span class="upload-meta">PNG o JPG, máximo 1 MB</span>
                    <span class="upload-file" data-qr-file-name aria-live="polite" hidden></span>
                    <input id="qr-file" class="sr-only" type="file" accept="image/png,image/jpeg" data-qr-file>
                </label>
                <img class="qr-preview-image" src="" alt="Vista previa del nuevo QR" data-qr-preview hidden>
                <button class="btn btn-primary btn-sm btn-block qr-upload-submit" type="submit" data-qr-submit disabled>
                    <span class="btn-label">Subir QR</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                </button>
            </form>
        </section>

        <section class="callout callout-info">
            <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
            <p>Solo tú puedes subir o cambiar tu QR. Recepción y el kiosco lo muestran junto a tus datos, pero no pueden editarlo.</p>
        </section>
    </aside>
</div>
