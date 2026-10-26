<?php
/** @var array<string, mixed> $receta */
$fecha = (string) ($receta['fecha'] ?? '');
$fechaFormateada = $fecha !== '' ? (new DateTimeImmutable($fecha))->format('d/m/Y H:i') : '';
?>
<div class="hoja-head">
    <div class="hoja-marca">
        <svg viewBox="0 0 40 40" aria-hidden="true">
            <rect width="40" height="40" rx="14" fill="#0b3d35"/>
            <path d="M20 11.5v17M11.5 20h17" stroke="#F4F6F0" stroke-width="4.2" stroke-linecap="round"/>
            <circle cx="30.5" cy="9.5" r="4" fill="#D95E3C"/>
        </svg>
        <strong>Clínica Vida Salud</strong>
    </div>
    <div class="hoja-tipo">Receta médica<strong><?= e($receta['codigo'] ?? '') ?></strong></div>
</div>

<dl class="hoja-meta">
    <div><dt>Paciente</dt><dd><?= e($receta['paciente']['nombre_completo'] ?? '') ?></dd></div>
    <div><dt>CI</dt><dd><?= e($receta['paciente']['ci'] ?? '') ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($receta['medico']['nombre_completo'] ?? '') ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e($fechaFormateada) ?></dd></div>
</dl>

<?php if (($receta['diagnostico'] ?? '') !== ''): ?>
<div class="hoja-seccion">
    <h2>Diagnóstico</h2>
    <p><?= e($receta['diagnostico']) ?></p>
</div>
<?php endif; ?>

<div class="hoja-seccion">
    <h2>Medicamentos</h2>
    <table class="hoja-tabla">
        <thead>
            <tr>
                <th>Medicamento</th>
                <th>Dosis</th>
                <th>Vía</th>
                <th>Frecuencia</th>
                <th>Duración</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($receta['detalle'] ?? []) as $item): ?>
            <tr>
                <td>
                    <?= e($item['medicamento'] ?? '') ?>
                    <?php if (($item['instrucciones'] ?? '') !== ''): ?>
                        <br><small><?= e($item['instrucciones']) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= e($item['dosis'] ?? '') ?></td>
                <td><?= e($item['via'] ?? '') ?></td>
                <td><?= e($item['frecuencia'] ?? '') ?></td>
                <td><?= e($item['duracion'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="hoja-codigos">
    <span>Código: <strong><?= e($receta['codigo'] ?? '') ?></strong></span>
    <span>Código de acceso: <strong><?= e($receta['codigo_acceso'] ?? '') ?></strong></span>
</div>
