<?php
/** @var array<string, mixed> $user */
$consultas = [
    [
        'fecha' => '24/10/2026', 'motivo' => 'Control de presión arterial',
        'diagnostico' => 'Hipertensión arterial leve controlada',
        'tratamiento' => 'Continuar losartán 50 mg cada 12 horas.',
        'pedidos' => ['Receta RSV-000120'],
    ],
    [
        'fecha' => '18/09/2026', 'motivo' => 'Dolor de cabeza frecuente',
        'diagnostico' => 'Cefalea tensional',
        'tratamiento' => 'Paracetamol 500 mg cada 8 horas por 3 días.',
        'pedidos' => ['Receta RSV-000098', 'Orden ORD-000041'],
    ],
    [
        'fecha' => '02/08/2026', 'motivo' => 'Chequeo anual',
        'diagnostico' => 'Paciente sano, sin hallazgos relevantes',
        'tratamiento' => 'Sin tratamiento farmacológico.',
        'pedidos' => ['Orden ORD-000033'],
    ],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Historia clínica</h1>
        <p>Busca un paciente para ver su evolución cronológica y antecedentes.</p>
    </div>
</header>

<section class="card">
    <div class="toolbar" style="border:none;padding:0;">
        <div class="toolbar-search">
            <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
            <input type="search" placeholder="Buscar paciente por nombre o CI…" autocomplete="off" aria-label="Buscar paciente" value="Juan Pérez">
        </div>
    </div>
    <div class="picker-selected" style="margin-top:14px;">
        <span class="avatar avatar-sm">J</span>
        <div class="picker-selected-copy">
            <strong>Juan Pérez</strong>
            <small>CI 10000001 · 41 años · O+</small>
        </div>
        <button class="picker-selected-clear" type="button" aria-label="Quitar selección">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
</section>

<section class="card" aria-labelledby="historia-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">3 atenciones registradas</span>
            <h2 id="historia-title">Evolución cronológica</h2>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:14px;">
        <?php foreach ($consultas as $consulta): ?>
            <article style="border:1px solid var(--panel-line);border-radius:12px;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                    <div>
                        <span class="cell-time"><?= e($consulta['fecha']) ?></span>
                        <strong style="display:block;font-size:14px;margin-top:4px;"><?= e($consulta['motivo']) ?></strong>
                    </div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php foreach ($consulta['pedidos'] as $pedido): ?>
                            <span class="badge badge-muted"><?= e($pedido) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p style="margin:10px 0 2px;font-size:13px;"><strong>Diagnóstico:</strong> <?= e($consulta['diagnostico']) ?></p>
                <p style="margin:0;font-size:13px;color:var(--panel-muted);"><strong>Tratamiento:</strong> <?= e($consulta['tratamiento']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
