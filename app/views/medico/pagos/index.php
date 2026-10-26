<?php
/** @var array<string, mixed> $user */
$pagos = [
    ['paciente' => 'Juan Pérez', 'fecha' => '24/10/2026', 'metodo' => 'efectivo', 'monto' => '150.00', 'referencia' => 'Consulta control'],
    ['paciente' => 'María Gómez', 'fecha' => '17/10/2026', 'metodo' => 'visa', 'monto' => '180.00', 'referencia' => 'Voucher #4821'],
    ['paciente' => 'Pedro Fernández', 'fecha' => '10/10/2026', 'metodo' => 'efectivo', 'monto' => '150.00', 'referencia' => ''],
    ['paciente' => 'Lucía Flores', 'fecha' => '02/10/2026', 'metodo' => 'mastercard', 'monto' => '200.00', 'referencia' => 'Voucher #4799'],
];
$metodoLabel = ['efectivo' => 'Efectivo', 'visa' => 'Visa', 'mastercard' => 'Mastercard'];
$metodoBadge = ['efectivo' => 'badge-pine', 'visa' => 'badge-info', 'mastercard' => 'badge-accent'];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Pagos</h1>
        <p>Registra el cobro de tu atención y revisa tus movimientos.</p>
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

        <form novalidate>
            <div class="field">
                <label>Paciente *</label>
                <div class="picker-selected">
                    <span class="avatar avatar-sm">J</span>
                    <div class="picker-selected-copy">
                        <strong>Juan Pérez</strong>
                        <small>CI 10000001</small>
                    </div>
                    <button class="picker-selected-clear" type="button" aria-label="Quitar selección">
                        <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
                    </button>
                </div>
            </div>

            <div class="field">
                <label for="pago-monto">Monto *</label>
                <div class="input-wrap input-money">
                    <span class="input-prefix">Bs</span>
                    <input id="pago-monto" type="number" inputmode="decimal" min="0.01" step="0.01" value="150.00">
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
                <input id="pago-referencia" type="text" maxlength="150" placeholder="N.º de recibo, voucher o nota">
            </div>

            <button class="btn btn-primary btn-block" type="button">
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
                    <h2>Resumen</h2>
                </div>
                <span class="badge badge-muted"><?= e(date('d/m/Y')) ?></span>
            </div>
            <div class="pay-total">
                <span>Total registrado</span>
                <strong>Bs 680,00</strong>
                <small>4 movimientos</small>
            </div>
            <div class="pay-breakdown">
                <div class="pay-breakdown-row">
                    <span class="origin-dot efectivo"></span>
                    <span>Efectivo</span>
                    <strong>Bs 300,00</strong>
                </div>
                <div class="pay-breakdown-row">
                    <span class="origin-dot visa"></span>
                    <span>Visa</span>
                    <strong>Bs 180,00</strong>
                </div>
                <div class="pay-breakdown-row">
                    <span class="origin-dot mastercard"></span>
                    <span>Mastercard</span>
                    <strong>Bs 200,00</strong>
                </div>
            </div>
        </section>
    </aside>
</div>

<section class="card" aria-labelledby="lista-pagos-title">
    <div class="card-head card-head-wrap">
        <div>
            <span class="card-kicker">Movimientos</span>
            <h2 id="lista-pagos-title">Mis pagos</h2>
        </div>
        <div class="card-head-tools">
            <label class="input-inline" aria-label="Desde">
                <span class="input-inline-label">Desde</span>
                <input type="date" value="2026-10-01">
            </label>
            <label class="input-inline" aria-label="Hasta">
                <span class="input-inline-label">Hasta</span>
                <input type="date" value="<?= e(date('Y-m-d')) ?>">
            </label>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Fecha</th>
                    <th>Método</th>
                    <th>Monto</th>
                    <th>Referencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr($pago['paciente'], 0, 1))) ?></span>
                                <strong><?= e($pago['paciente']) ?></strong>
                            </div>
                        </td>
                        <td><?= e($pago['fecha']) ?></td>
                        <td><span class="badge <?= e($metodoBadge[$pago['metodo']]) ?>"><?= e($metodoLabel[$pago['metodo']]) ?></span></td>
                        <td><strong>Bs <?= e($pago['monto']) ?></strong></td>
                        <td><?= e($pago['referencia'] !== '' ? $pago['referencia'] : '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-foot">
        <span>4 movimientos</span>
        <strong>Total del rango: Bs 680,00</strong>
    </div>
</section>
