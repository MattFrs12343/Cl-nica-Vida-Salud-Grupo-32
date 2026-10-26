<?php
/** @var array<string, mixed> $user */
$pacientes = [
    ['nombre' => 'Juan Pérez', 'ci' => '10000001', 'edad' => 41, 'ultima' => '24/10/2026', 'visitas' => 6, 'estado' => 'activo'],
    ['nombre' => 'María Gómez', 'ci' => '10000002', 'edad' => 34, 'ultima' => '17/10/2026', 'visitas' => 3, 'estado' => 'activo'],
    ['nombre' => 'Lucía Flores', 'ci' => '10000006', 'edad' => 28, 'ultima' => '10/10/2026', 'visitas' => 2, 'estado' => 'activo'],
    ['nombre' => 'Pedro Fernández', 'ci' => '10000005', 'edad' => 56, 'ultima' => '24/10/2026', 'visitas' => 9, 'estado' => 'activo'],
    ['nombre' => 'Rosa Villarroel', 'ci' => '10000004', 'edad' => 76, 'ultima' => '02/09/2026', 'visitas' => 4, 'estado' => 'inactivo'],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico</span>
        <h1>Pacientes</h1>
        <p>Pacientes que has atendido alguna vez. Busca por nombre o CI para abrir su historia.</p>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Buscar por nombre o CI…" autocomplete="off" aria-label="Buscar pacientes">
    </div>
    <div class="toolbar-filters">
        <label class="select-wrap">
            <span class="sr-only">Estado</span>
            <select>
                <option>Todos</option>
                <option>Activos</option>
                <option>Inactivos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
    </div>
    <p class="toolbar-count">5 pacientes atendidos</p>
</section>

<section class="card" aria-labelledby="pacientes-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Tu consulta</span>
            <h2 id="pacientes-title">Pacientes atendidos</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Edad</th>
                    <th>Última atención</th>
                    <th>Visitas</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pacientes as $paciente): ?>
                    <tr>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm"><?= e(mb_strtoupper(mb_substr($paciente['nombre'], 0, 1))) ?></span>
                                <div>
                                    <strong><?= e($paciente['nombre']) ?></strong>
                                    <small><?= e($paciente['ci']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?= e((string) $paciente['edad']) ?> años</td>
                        <td><?= e($paciente['ultima']) ?></td>
                        <td><?= e((string) $paciente['visitas']) ?></td>
                        <td>
                            <?php if ($paciente['estado'] === 'activo'): ?>
                                <span class="status status-activo">Activo</span>
                            <?php else: ?>
                                <span class="status status-inactivo">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Ver historia clínica" aria-label="Ver historia clínica">
                                    <svg class="icon" aria-hidden="true"><use href="#i-file"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
