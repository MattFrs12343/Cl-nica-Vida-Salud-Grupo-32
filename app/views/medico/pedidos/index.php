<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Recetas y órdenes</h1>
        <p>Emite recetas, documentos médicos y órdenes de estudio desde una consulta.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/medico/consultas')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-stethoscope"></use></svg>
            Registrar consulta
        </a>
    </div>
</header>

<section class="card" aria-labelledby="pedido-paciente-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Paso 1</span>
            <h2 id="pedido-paciente-title">Selecciona paciente y consulta</h2>
        </div>
    </div>

    <div class="field">
        <label for="pedido-search">Nombre o CI</label>
        <div class="input-wrap">
            <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
            <input id="pedido-search" type="search" placeholder="Buscar paciente por nombre o CI…" autocomplete="off" data-pedido-search>
        </div>
    </div>
    <div class="picker-results" data-pedido-results hidden></div>
    <div class="picker-selected" data-pedido-selected hidden></div>
    <input type="hidden" data-pedido-patient-id>

    <div class="stack-links" style="margin-top:14px;" data-pedido-consultas>
        <div class="empty-state" style="padding:18px 12px;">
            <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-clipboard"></use></svg></span>
            <h2>Sin consultas</h2>
            <p>Selecciona un paciente para ver sus consultas recientes.</p>
        </div>
    </div>
</section>

<div class="dashboard-grid" data-pedido-workspace hidden>
    <section class="card card-span-2" aria-labelledby="pedido-form-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Paso 2</span>
                <h2 id="pedido-form-title">Emitir pedido</h2>
                <p class="card-subtitle" data-pedido-meta>Selecciona una consulta.</p>
            </div>
            <span class="badge badge-muted" data-pedido-patient>Paciente</span>
        </div>

        <div data-pedido-result hidden></div>

        <div class="segmented" role="tablist" aria-label="Tipo de pedido">
            <button class="segmented-item is-active" type="button" role="tab" aria-selected="true" data-pedido-tab="receta">Receta</button>
            <button class="segmented-item" type="button" role="tab" aria-selected="false" data-pedido-tab="documento">Documento</button>
            <button class="segmented-item" type="button" role="tab" aria-selected="false" data-pedido-tab="orden">Orden</button>
        </div>

        <div data-pedido-panel="receta">
            <form data-receta-form novalidate>
                <div data-receta-detalle></div>
                <div class="row-actions" style="margin:8px 0 14px;">
                    <button class="btn btn-ghost btn-sm" type="button" data-add-receta>
                        <svg class="icon" aria-hidden="true"><use href="#i-plus"></use></svg>
                        Agregar medicamento
                    </button>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">
                    <span class="btn-label">Emitir receta</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-ticket"></use></svg></span>
                </button>
            </form>
        </div>

        <div data-pedido-panel="documento" hidden>
            <form data-documento-form novalidate>
                <div class="field-grid">
                    <div class="field field-span-2">
                        <label for="doc-tipo">Tipo de documento *</label>
                        <label class="select-wrap">
                            <select id="doc-tipo" name="tipo">
                                <option value="certificado">Certificado médico</option>
                                <option value="informe">Informe médico</option>
                                <option value="constancia">Constancia de atención</option>
                                <option value="interconsulta">Interconsulta</option>
                            </select>
                            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                        </label>
                    </div>
                    <div class="field field-span-2">
                        <label for="doc-contenido">Contenido *</label>
                        <textarea id="doc-contenido" name="contenido" rows="6" maxlength="4000" placeholder="Redacta el documento médico…"></textarea>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm" type="submit" style="margin-top:12px;">
                    <span class="btn-label">Emitir documento</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
                </button>
            </form>
        </div>

        <div data-pedido-panel="orden" hidden>
            <form data-orden-form novalidate>
                <div class="field-grid">
                    <div class="field field-span-2">
                        <label for="orden-tipo">Tipo de orden *</label>
                        <label class="select-wrap">
                            <select id="orden-tipo" name="tipo">
                                <option value="laboratorio">Laboratorio</option>
                                <option value="imagen">Imagen</option>
                                <option value="otro">Otro</option>
                            </select>
                            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                        </label>
                    </div>
                </div>
                <div data-orden-detalle></div>
                <div class="row-actions" style="margin:8px 0 14px;">
                    <button class="btn btn-ghost btn-sm" type="button" data-add-orden>
                        <svg class="icon" aria-hidden="true"><use href="#i-plus"></use></svg>
                        Agregar estudio
                    </button>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">
                    <span class="btn-label">Emitir orden</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-clipboard"></use></svg></span>
                </button>
            </form>
        </div>
    </section>

    <aside class="dashboard-side">
        <section class="callout callout-info">
            <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
            <p>Los pedidos se generan desde una consulta ya registrada. Imprímelos o envíalos por WhatsApp desde aquí.</p>
        </section>
    </aside>
</div>

<section class="card" aria-labelledby="pedidos-sesion-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Sesión actual</span>
            <h2 id="pedidos-sesion-title">Pedidos emitidos en esta sesión</h2>
        </div>
        <span class="badge badge-muted" data-pedidos-sesion-count>0 pedidos en esta sesión</span>
    </div>
    <div data-pedidos-sesion>
        <div class="empty-state" style="padding:18px 12px;">
            <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-ticket"></use></svg></span>
            <h2>Sin pedidos en esta sesión</h2>
            <p>Los pedidos emitidos aparecerán aquí mientras no cierres sesión.</p>
        </div>
    </div>
    <div class="callout callout-warning" style="margin-top:14px;">
        <svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg>
        <p>El sistema no expone un listado histórico de pedidos. Para consultar una receta u orden anterior, abre la historia clínica del paciente.</p>
    </div>
</section>
