<?php
/** @var array<string, mixed> $user */
$especialidades = [
    ['nombre' => 'Medicina General', 'icono' => 'stethoscope', 'restriccion' => 'Sin restricciones', 'destacada' => true, 'medicos' => 3, 'estado' => 'activo'],
    ['nombre' => 'Cardiología', 'icono' => 'heart', 'restriccion' => 'Sin restricciones', 'destacada' => true, 'medicos' => 1, 'estado' => 'activo'],
    ['nombre' => 'Pediatría', 'icono' => 'users', 'restriccion' => 'Hasta 12 años', 'destacada' => true, 'medicos' => 1, 'estado' => 'activo'],
    ['nombre' => 'Ginecología', 'icono' => 'heart', 'restriccion' => 'Solo femenino', 'destacada' => false, 'medicos' => 1, 'estado' => 'activo'],
    ['nombre' => 'Dermatología', 'icono' => 'shield', 'restriccion' => 'Sin restricciones', 'destacada' => false, 'medicos' => 1, 'estado' => 'inactivo'],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Especialidades</h1>
        <p>Catálogo de especialidades médicas, restricciones de sexo/edad y destacadas en la landing.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button">
            Nueva especialidad
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="card" aria-labelledby="lista-especialidades-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Catálogo</span>
            <h2 id="lista-especialidades-title">Especialidades registradas</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Especialidad</th>
                    <th>Restricción</th>
                    <th>Médicos</th>
                    <th>Destacada</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($especialidades as $especialidad): ?>
                    <tr>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><svg class="icon" aria-hidden="true" style="width:16px;height:16px;"><use href="#i-<?= e($especialidad['icono']) ?>"></use></svg></span>
                                <strong><?= e($especialidad['nombre']) ?></strong>
                            </div>
                        </td>
                        <td><?= e($especialidad['restriccion']) ?></td>
                        <td><?= e((string) $especialidad['medicos']) ?></td>
                        <td>
                            <?php if ($especialidad['destacada']): ?>
                                <span class="badge badge-pine"><svg class="icon" aria-hidden="true"><use href="#i-spark"></use></svg> Destacada</span>
                            <?php else: ?>
                                <span class="badge badge-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($especialidad['estado'] === 'activo'): ?>
                                <span class="status status-activo">Activo</span>
                            <?php else: ?>
                                <span class="status status-inactivo">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Editar" aria-label="Editar">
                                    <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                </button>
                                <button class="row-action is-danger" type="button" title="Desactivar" aria-label="Desactivar">
                                    <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
