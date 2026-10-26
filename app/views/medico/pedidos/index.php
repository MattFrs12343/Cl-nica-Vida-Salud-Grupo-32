<?php
/** @var array<string, mixed> $user */
$recetas = [
    ['codigo' => 'RSV-000120', 'paciente' => 'Juan Pérez', 'fecha' => '24/10/2026', 'medicamentos' => 2, 'enviado' => true],
    ['codigo' => 'RSV-000098', 'paciente' => 'María Gómez', 'fecha' => '17/10/2026', 'medicamentos' => 1, 'enviado' => true],
    ['codigo' => 'RSV-000091', 'paciente' => 'Pedro Fernández', 'fecha' => '10/10/2026', 'medicamentos' => 3, 'enviado' => false],
    ['codigo' => 'RSV-000085', 'paciente' => 'Lucía Flores', 'fecha' => '02/10/2026', 'medicamentos' => 1, 'enviado' => true],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Recetas y órdenes</h1>
        <p>Recetas, documentos médicos y órdenes de estudio emitidos durante tus consultas.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button">
            Nueva receta
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="card">
    <div class="segmented" role="tablist" aria-label="Tipo de pedido">
        <button class="segmented-item is-active" type="button" role="tab" aria-selected="true">
            Recetas
            <span class="segmented-count">12</span>
        </button>
        <button class="segmented-item" type="button" role="tab" aria-selected="false">
            Documentos
            <span class="segmented-count">4</span>
        </button>
        <button class="segmented-item" type="button" role="tab" aria-selected="false">
            Órdenes
            <span class="segmented-count">7</span>
        </button>
    </div>

    <div class="table-wrap" style="margin-top:16px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Paciente</th>
                    <th>Fecha</th>
                    <th>Medicamentos</th>
                    <th>WhatsApp</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recetas as $receta): ?>
                    <tr>
                        <td><span class="cell-code"><?= e($receta['codigo']) ?></span></td>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr($receta['paciente'], 0, 1))) ?></span>
                                <strong><?= e($receta['paciente']) ?></strong>
                            </div>
                        </td>
                        <td><?= e($receta['fecha']) ?></td>
                        <td><?= e((string) $receta['medicamentos']) ?></td>
                        <td>
                            <?php if ($receta['enviado']): ?>
                                <span class="badge badge-pine">Enviado</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Sin enviar</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Imprimir" aria-label="Imprimir">
                                    <svg class="icon" aria-hidden="true"><use href="#i-print"></use></svg>
                                </button>
                                <button class="row-action" type="button" title="Enviar por WhatsApp" aria-label="Enviar por WhatsApp">
                                    <svg class="icon" aria-hidden="true"><use href="#i-mail"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
