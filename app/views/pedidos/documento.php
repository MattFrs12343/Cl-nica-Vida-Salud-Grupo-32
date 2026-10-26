<?php
/** @var array<string, mixed> $documento */
$etiquetas = [
    'certificado' => 'Certificado médico',
    'informe' => 'Informe médico',
    'constancia' => 'Constancia médica',
    'interconsulta' => 'Interconsulta',
];
$tipo = (string) ($documento['tipo'] ?? '');
$fecha = (string) ($documento['fecha'] ?? '');
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
    <div class="hoja-tipo"><?= e($etiquetas[$tipo] ?? 'Documento médico') ?><strong><?= e($documento['codigo'] ?? '') ?></strong></div>
</div>

<dl class="hoja-meta">
    <div><dt>Paciente</dt><dd><?= e($documento['paciente']['nombre_completo'] ?? '') ?></dd></div>
    <div><dt>CI</dt><dd><?= e($documento['paciente']['ci'] ?? '') ?></dd></div>
    <div><dt>Médico</dt><dd><?= e($documento['medico']['nombre_completo'] ?? '') ?></dd></div>
    <div><dt>Fecha</dt><dd><?= e($fechaFormateada) ?></dd></div>
</dl>

<div class="hoja-seccion">
    <h2>Contenido</h2>
    <p style="white-space: pre-wrap;"><?= e($documento['contenido'] ?? 'Sin contenido.') ?></p>
</div>

<div class="hoja-codigos">
    <span>Código: <strong><?= e($documento['codigo'] ?? '') ?></strong></span>
    <span>Código de acceso: <strong><?= e($documento['codigo_acceso'] ?? '') ?></strong></span>
</div>
