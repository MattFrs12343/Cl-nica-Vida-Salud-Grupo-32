<?php
/** @var array<string, mixed> $orden */
$etiquetas = [
    'laboratorio' => 'Orden de laboratorio',
    'imagen' => 'Orden de imágenes',
    'otro' => 'Orden de estudios',
];
$tipo = (string) ($orden['tipo'] ?? '');
$fecha = (string) ($orden['fecha'] ?? '');
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
    <div class="hoja-tipo"><?= e($etiquetas[$tipo] ?? 'Orden de estudios') ?><strong><?= e($orden['codigo'] ?? '') ?></strong></div>
</div>

<dl class="hoja-meta">
    <div><dt>Paciente</dt><dd><?= e($orden['paciente']['nombre_completo'] ?? '') ?></dd></div>
    <div><dt>CI</dt><dd><?= e($orden['paciente']['ci'] ?? '') ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($orden['medico']['nombre_completo'] ?? '') ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e($fechaFormateada) ?></dd></div>
</dl>

<div class="hoja-seccion">
    <h2>Estudios solicitados</h2>
    <table class="hoja-tabla">
        <thead>
            <tr>
                <th>Estudio</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (($orden['detalle'] ?? []) as $item): ?>
            <tr>
                <td><?= e($item['estudio'] ?? '') ?></td>
                <td><?= e($item['observaciones'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (($orden['resultados'] ?? []) !== []): ?>
<div class="hoja-seccion">
    <h2>Resultados</h2>
    <table class="hoja-tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Detalle</th>
                <th>Revisado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orden['resultados'] as $resultado): ?>
            <tr>
                <td><?= e((new DateTimeImmutable((string) $resultado['fecha']))->format('d/m/Y H:i')) ?></td>
                <td>
                    <?php if (($resultado['texto'] ?? null) !== null): ?>
                        <?= e($resultado['texto']) ?>
                    <?php elseif (($resultado['archivo'] ?? null) !== null): ?>
                        Archivo adjunto
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><?= $resultado['revisado'] ? 'Sí' : 'No' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="hoja-codigos">
    <span>Código: <strong><?= e($orden['codigo'] ?? '') ?></strong></span>
    <span>Código de acceso: <strong><?= e($orden['codigo_acceso'] ?? '') ?></strong></span>
</div>
