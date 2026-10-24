<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Caja y cobros</span>
        <h1>Pagos</h1>
        <p>Registra el cobro de la consulta y revisa los movimientos del día.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/recepcion/fichas')) ?>">
            <svg class="icon" aria-hidden="true"><use href="#i-ticket"></use></svg>
            Buscar ficha
        </a>
    </div>
</header>

<div class="pay-layout">
    <section class="card pay-form-card" aria-labelledby="pago-form-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Nuevo movimiento</span>
                <h2 id="pago-form-title">Registrar pago</h2>
            </div>
        </div>

        <form data-pago-form novalidate>
            <div class="field">
                <label>Paciente *</label>
                <div class="patient-picker">
                    <div class="input-wrap">
                        <svg class="icon input-icon" aria-hidden="true"><use href="#i-search"></use></svg>
                        <input type="search" placeholder="Buscar por nombre, apellido o CI…"
                               autocomplete="off" data-pago-patient-search aria-label="Buscar paciente">
                    </div>
                    <div class="picker-results" data-pago-patient-results hidden></div>
                    <div class="picker-selected" data-pago-patient-selected hidden></div>
                    <input type="hidden" name="paciente_id" data-pago-patient-id>
                </div>
            </div>

            <div class="field-grid field-grid-2">
                <div class="field">
                    <label for="pago-medico">Médico *</label>
                    <div class="select-wrap">
                        <select id="pago-medico" name="medico_id" required data-pago-medico>
                            <option value="">Cargando médicos…</option>
                        </select>
                        <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                    </div>
                </div>
                <div class="field">
                    <label for="pago-monto">Monto *</label>
                    <div class="input-wrap input-money">
                        <span class="input-prefix">Bs</span>
                        <input id="pago-monto" name="monto" type="number" inputmode="decimal"
                               min="0.01" step="0.01" placeholder="0,00" required data-pago-monto>
                    </div>
                </div>
            </div>

            <div class="field">
                <label>Método de pago *</label>
                <div class="method-grid" role="radiogroup" aria-label="Método de pago">
                    <label class="method-card">
                        <input type="radio" name="metodo" value="efectivo" checked>
                        <span class="method-card-body">
                            <span class="method-icon" aria-hidden="true"><svg class="icon"><use href="#i-money"></use></svg></span>
                            <span><strong>Efectivo</strong><small>Billetes y monedas</small></span>
                            <span class="method-check" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                        </span>
                    </label>
                    <label class="method-card">
                        <input type="radio" name="metodo" value="visa">
                        <span class="method-card-body">
                            <span class="method-icon" aria-hidden="true"><svg class="icon"><use href="#i-card"></use></svg></span>
                            <span><strong>Visa</strong><small>Débito o crédito</small></span>
                            <span class="method-check" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                        </span>
                    </label>
                    <label class="method-card">
                        <input type="radio" name="metodo" value="mastercard">
                        <span class="method-card-body">
                            <span class="method-icon" aria-hidden="true"><svg class="icon"><use href="#i-card"></use></svg></span>
                            <span><strong>Mastercard</strong><small>Débito o crédito</small></span>
                            <span class="method-check" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="field">
                <label for="pago-referencia">Referencia <span class="label-hint">(opcional)</span></label>
                <input id="pago-referencia" name="referencia_paciente" type="text" maxlength="150"
                       placeholder="N.º de recibo, voucher o nota" data-pago-referencia>
                <p class="field-hint">Se guarda como referencia visible en el listado.</p>
            </div>

            <div class="callout callout-info">
                <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
                <p>El pago queda asociado al paciente y al médico. Verifica el monto antes de confirmar.</p>
            </div>

            <button class="btn btn-primary btn-block" type="submit">
                <span class="btn-label">Registrar pago</span>
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
            </button>
        </form>
    </section>

    <aside class="dashboard-side">
        <section class="card pay-summary-card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Hoy</span>
                    <h2>Resumen de caja</h2>
                </div>
                <span class="badge badge-muted" data-pago-summary-date><?= e(date('d/m/Y')) ?></span>
            </div>
            <div class="pay-total">
                <span>Total registrado</span>
                <strong data-pago-total>Bs 0,00</strong>
                <small data-pago-count>Sin movimientos todavía</small>
            </div>
            <div class="pay-breakdown">
                <div class="pay-breakdown-row">
                    <span class="origin-dot efectivo"></span>
                    <span>Efectivo</span>
                    <strong data-pago-metodo="efectivo">Bs 0,00</strong>
                </div>
                <div class="pay-breakdown-row">
                    <span class="origin-dot visa"></span>
                    <span>Visa</span>
                    <strong data-pago-metodo="visa">Bs 0,00</strong>
                </div>
                <div class="pay-breakdown-row">
                    <span class="origin-dot mastercard"></span>
                    <span>Mastercard</span>
                    <strong data-pago-metodo="mastercard">Bs 0,00</strong>
                </div>
            </div>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Cierre de caja</span>
            <h2>Todo cuadrado</h2>
            <p>Compara el total del sistema con el efectivo y los vouchers del día antes de cerrar el turno.</p>
            <span class="badge badge-pine"><svg class="icon" aria-hidden="true"><use href="#i-shield"></use></svg> Registro auditable</span>
        </section>
    </aside>
</div>

<section class="card" aria-labelledby="lista-pagos-title">
    <div class="card-head card-head-wrap">
        <div>
            <span class="card-kicker">Movimientos</span>
            <h2 id="lista-pagos-title">Pagos registrados</h2>
        </div>
        <div class="card-head-tools">
            <label class="input-inline" aria-label="Desde">
                <span class="input-inline-label">Desde</span>
                <input type="date" data-pago-desde value="<?= e(date('Y-m-d')) ?>">
            </label>
            <label class="input-inline" aria-label="Hasta">
                <span class="input-inline-label">Hasta</span>
                <input type="date" data-pago-hasta value="<?= e(date('Y-m-d')) ?>">
            </label>
            <label class="select-wrap">
                <span class="sr-only">Filtrar por médico</span>
                <select data-pago-filtro-medico>
                    <option value="">Todos los médicos</option>
                </select>
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
            </label>
            <button class="icon-btn" type="button" data-pago-refresh aria-label="Actualizar pagos">
                <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            </button>
        </div>
    </div>
    <div class="table-wrap" data-pagos-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="table-foot" data-pago-table-foot hidden>
        <span data-pago-table-count>—</span>
        <strong>Total del rango: <span data-pago-table-total>Bs 0,00</span></strong>
    </div>
</section>
