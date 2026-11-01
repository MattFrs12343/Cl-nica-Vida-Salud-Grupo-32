<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Pagos</h1>
        <p>Registra el cobro de tu atención y revisa tus movimientos.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-ghost btn-sm" type="button" data-mpago-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            Actualizar
        </button>
    </div>
</header>

<div class="pay-layout">
    <section class="card pay-form-card" aria-labelledby="mpago-form-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Nuevo movimiento</span>
                <h2 id="mpago-form-title">Registrar pago</h2>
            </div>
        </div>

        <form data-mpago-form novalidate>
            <div class="field">
                <label for="mpago-patient-search">Paciente *</label>
                <div class="input-wrap">
                    <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
                    <input id="mpago-patient-search" type="search" placeholder="Buscar por nombre o CI…" autocomplete="off" data-mpago-patient-search>
                </div>
                <div class="picker-results" data-mpago-patient-results hidden></div>
                <div class="picker-selected" data-mpago-patient-selected hidden></div>
                <input type="hidden" data-mpago-patient-id>
            </div>

            <div class="field">
                <label for="mpago-monto">Monto *</label>
                <div class="input-wrap input-money">
                    <span class="input-prefix">Bs</span>
                    <input id="mpago-monto" type="number" inputmode="decimal" min="0.01" step="0.01" placeholder="0.00" data-mpago-monto>
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
                <label for="mpago-referencia">Referencia <span class="label-hint">(opcional)</span></label>
                <input id="mpago-referencia" type="text" maxlength="150" placeholder="N.º de recibo, voucher o nota" data-mpago-referencia>
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
                    <span class="card-kicker">Rango seleccionado</span>
                    <h2>Resumen</h2>
                </div>
                <span class="badge badge-muted"><?= e(date('d/m/Y')) ?></span>
            </div>
            <div class="pay-total">
                <span>Total registrado</span>
                <strong data-mpago-stat="total">Bs 0,00</strong>
                <small><span data-mpago-stat="count">0</span> movimientos</small>
            </div>
            <div class="pay-breakdown">
                <div class="pay-breakdown-row">
                    <span class="origin-dot efectivo"></span>
                    <span>Efectivo</span>
                    <strong data-mpago-stat="efectivo">Bs 0,00</strong>
                </div>
                <div class="pay-breakdown-row">
                    <span class="origin-dot visa"></span>
                    <span>Visa</span>
                    <strong data-mpago-stat="visa">Bs 0,00</strong>
                </div>
                <div class="pay-breakdown-row">
                    <span class="origin-dot mastercard"></span>
                    <span>Mastercard</span>
                    <strong data-mpago-stat="mastercard">Bs 0,00</strong>
                </div>
            </div>
        </section>
    </aside>
</div>

<section class="card" aria-labelledby="mpagos-title">
    <div class="card-head card-head-wrap">
        <div>
            <span class="card-kicker">Movimientos</span>
            <h2 id="mpagos-title">Mis pagos</h2>
        </div>
        <div class="card-head-tools">
            <label class="input-inline" aria-label="Desde">
                <span class="input-inline-label">Desde</span>
                <input type="date" value="<?= e(date('Y-m-01')) ?>" data-mpago-desde>
            </label>
            <label class="input-inline" aria-label="Hasta">
                <span class="input-inline-label">Hasta</span>
                <input type="date" value="<?= e(date('Y-m-d')) ?>" data-mpago-hasta>
            </label>
        </div>
    </div>
    <div data-mpago-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="table-foot">
        <span data-mpago-count>0 movimientos</span>
        <strong>Total del rango: <span data-mpago-total>Bs 0,00</span></strong>
    </div>
</section>
