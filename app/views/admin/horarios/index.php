<?php
/** @var array<string, mixed> $user */
$horarios = [
    ['dia' => 'Lunes', 'inicio' => '08:00', 'fin' => '12:00', 'web' => 50, 'kiosco' => 50, 'duracion' => 30],
    ['dia' => 'Martes', 'inicio' => '08:00', 'fin' => '12:00', 'web' => 50, 'kiosco' => 50, 'duracion' => 30],
    ['dia' => 'Miércoles', 'inicio' => '14:00', 'fin' => '18:00', 'web' => 40, 'kiosco' => 40, 'duracion' => 30],
    ['dia' => 'Jueves', 'inicio' => '08:00', 'fin' => '12:00', 'web' => 50, 'kiosco' => 50, 'duracion' => 30],
    ['dia' => 'Viernes', 'inicio' => '08:00', 'fin' => '13:00', 'web' => 60, 'kiosco' => 60, 'duracion' => 30],
];
$bloqueos = [
    ['fecha' => '2026-11-10', 'motivo' => 'Congreso médico', 'alcance' => 'Todo el día'],
    ['fecha' => '2026-12-25', 'motivo' => 'Feriado', 'alcance' => 'Todo el día'],
];
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Administración</span>
        <h1>Horarios</h1>
        <p>Franjas semanales, cupos por canal (web/kiosco) y bloqueos de agenda por médico.</p>
    </div>
    <div class="page-actions">
        <label class="select-wrap">
            <span class="sr-only">Médico</span>
            <select>
                <option>Dr. Carlos Méndez</option>
                <option>Dra. Ana Pérez</option>
                <option>Dr. Jorge Salinas</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="btn btn-primary btn-sm" type="button">
            Nueva franja
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="card" aria-labelledby="horarios-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Dr. Carlos Méndez · Medicina General</span>
            <h2 id="horarios-title">Franjas semanales</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Horario</th>
                    <th>Duración de cita</th>
                    <th>Cupos web</th>
                    <th>Cupos kiosco</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $horario): ?>
                    <tr>
                        <td><strong><?= e($horario['dia']) ?></strong></td>
                        <td>
                            <div class="cell-stack">
                                <span class="cell-time"><?= e($horario['inicio']) ?> – <?= e($horario['fin']) ?></span>
                            </div>
                        </td>
                        <td><?= e((string) $horario['duracion']) ?> min</td>
                        <td><span class="badge badge-info"><?= e((string) $horario['web']) ?> cupos</span></td>
                        <td><span class="badge badge-accent"><?= e((string) $horario['kiosco']) ?> cupos</span></td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action" type="button" title="Editar" aria-label="Editar">
                                    <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                </button>
                                <button class="row-action is-danger" type="button" title="Eliminar" aria-label="Eliminar">
                                    <svg class="icon" aria-hidden="true"><use href="#i-trash"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card" aria-labelledby="bloqueos-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Agenda</span>
            <h2 id="bloqueos-title">Bloqueos programados</h2>
        </div>
        <button class="btn btn-ghost btn-sm" type="button">
            Nuevo bloqueo
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Motivo</th>
                    <th>Alcance</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bloqueos as $bloqueo): ?>
                    <tr>
                        <td><?= e((new DateTimeImmutable($bloqueo['fecha']))->format('d/m/Y')) ?></td>
                        <td><?= e($bloqueo['motivo']) ?></td>
                        <td><span class="badge badge-warning"><?= e($bloqueo['alcance']) ?></span></td>
                        <td>
                            <div class="row-actions">
                                <button class="row-action is-danger" type="button" title="Quitar bloqueo" aria-label="Quitar bloqueo">
                                    <svg class="icon" aria-hidden="true"><use href="#i-trash"></use></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
