<?php
/** @var array<string, mixed> $user */
$registros = [
    ['fecha' => '24/10/2026 13:42', 'usuario' => 'recepcion', 'accion' => 'UPDATE', 'tabla' => 'citas', 'registro' => '#204', 'detalle' => 'Reprogramó la cita de Juan Pérez'],
    ['fecha' => '24/10/2026 12:18', 'usuario' => 'medico', 'accion' => 'INSERT', 'tabla' => 'consultas', 'registro' => '#58', 'detalle' => 'Registró consulta de María Gómez'],
    ['fecha' => '24/10/2026 11:05', 'usuario' => 'admin', 'accion' => 'UPDATE', 'tabla' => 'usuarios', 'registro' => '#9', 'detalle' => 'Desbloqueó la cuenta de Rosa Villarroel'],
    ['fecha' => '24/10/2026 09:50', 'usuario' => 'recepcion.medico', 'accion' => 'INSERT', 'tabla' => 'pagos', 'registro' => '#301', 'detalle' => 'Registró un pago en efectivo'],
    ['fecha' => '23/10/2026 17:33', 'usuario' => 'admin', 'accion' => 'UPDATE', 'tabla' => 'especialidades', 'registro' => '#4', 'detalle' => 'Marcó Cardiología como destacada'],
    ['fecha' => '23/10/2026 16:02', 'usuario' => 'kiosco', 'accion' => 'INSERT', 'tabla' => 'fichas', 'registro' => '#512', 'detalle' => 'Emitió ficha FIC-0512'],
    ['fecha' => '23/10/2026 10:15', 'usuario' => 'admin', 'accion' => 'DELETE', 'tabla' => 'horarios', 'registro' => '#17', 'detalle' => 'Eliminó una franja horaria'],
];
$coloresAccion = ['INSERT' => 'badge-pine', 'UPDATE' => 'badge-info', 'DELETE' => 'badge-danger'];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Auditoría</h1>
        <p>Bitácora de cambios sensibles: quién, qué tabla, qué registro y cuándo.</p>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por usuario o tabla…" autocomplete="off" aria-label="Buscar en la bitácora">
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Acción</span>
            <select>
                <option>Todas las acciones</option>
                <option>INSERT</option>
                <option>UPDATE</option>
                <option>DELETE</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <input type="date" value="<?= e(date('Y-m-d')) ?>" aria-label="Fecha">
    </div>
    <p class="toolbar-count">312 registros este mes</p>
</section>

<section class="card" aria-labelledby="bitacora-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Últimos cambios</span>
            <h2 id="bitacora-title">Bitácora</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>Detalle</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $registro): ?>
                    <tr>
                        <td><span class="cell-time"><?= e($registro['fecha']) ?></span></td>
                        <td><?= e($registro['usuario']) ?></td>
                        <td><span class="badge <?= e($coloresAccion[$registro['accion']] ?? 'badge-muted') ?>"><?= e($registro['accion']) ?></span></td>
                        <td>
                            <div class="cell-stack">
                                <strong><?= e($registro['tabla']) ?></strong>
                                <small><?= e($registro['registro']) ?></small>
                            </div>
                        </td>
                        <td><?= e($registro['detalle']) ?></td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Ver diferencias" aria-label="Ver diferencias">
                                    <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination">
        <span class="pagination-info">Mostrando 1–7 de 312</span>
        <div class="pagination-actions">
            <button class="pagination-btn" type="button" disabled>
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
                Anterior
            </button>
            <span class="pagination-page">1 / 45</span>
            <button class="pagination-btn" type="button">
                Siguiente
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
        </div>
    </div>
</section>
