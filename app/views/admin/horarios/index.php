<?php
/** @var array<string, mixed> $user */
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
            <select data-horario-medico></select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="btn btn-primary btn-sm" type="button" data-franja-new>
            Nueva franja
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="card" aria-labelledby="horarios-title">
    <div class="card-head">
        <div>
            <span class="card-kicker" data-horario-doctor>Selecciona un médico</span>
            <h2 id="horarios-title">Franjas semanales</h2>
        </div>
        <button class="icon-btn" type="button" aria-label="Actualizar horarios" data-horario-refresh>
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <div class="table-wrap" data-franjas-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section class="card" aria-labelledby="bloqueos-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Agenda</span>
            <h2 id="bloqueos-title">Bloqueos programados</h2>
        </div>
        <button class="btn btn-ghost btn-sm" type="button" data-bloqueo-new>
            Nuevo bloqueo
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-plus"></use></svg></span>
        </button>
    </div>
    <div class="table-wrap" data-bloqueos-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<div class="modal" id="modal-franja" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-franja-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Agenda semanal</span>
                <h2 id="modal-franja-title" data-franja-modal-title>Nueva franja</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-franja-form novalidate>
            <input type="hidden" name="id">
            <div class="modal-body">
                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label for="franja-dia">Día *</label>
                        <div class="select-wrap">
                            <select id="franja-dia" name="dia_semana" required>
                                <option value="1">Lunes</option>
                                <option value="2">Martes</option>
                                <option value="3">Miércoles</option>
                                <option value="4">Jueves</option>
                                <option value="5">Viernes</option>
                                <option value="6">Sábado</option>
                                <option value="0">Domingo</option>
                            </select>
                            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label-row"><span>Estado</span></label>
                        <label class="check-card">
                            <input type="checkbox" name="activo" checked>
                            <span class="check-card-box" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                            <span>
                                <strong>Acepta reservas</strong>
                                <small>Los pacientes pueden reservar en esta franja.</small>
                            </span>
                        </label>
                    </div>
                    <div class="field">
                        <label for="franja-inicio">Hora de inicio *</label>
                        <input id="franja-inicio" name="hora_inicio" type="time" required>
                    </div>
                    <div class="field">
                        <label for="franja-fin">Hora de fin *</label>
                        <input id="franja-fin" name="hora_fin" type="time" required>
                    </div>
                    <div class="field">
                        <label for="franja-web">Cupos web</label>
                        <input id="franja-web" name="cupos_web" type="number" min="0" max="32767" value="0">
                        <p class="field-hint">Reservas desde la web pública.</p>
                    </div>
                    <div class="field">
                        <label for="franja-kiosco">Cupos kiosco</label>
                        <input id="franja-kiosco" name="cupos_kiosco" type="number" min="0" max="32767" value="0">
                        <p class="field-hint">Fichas emitidas en el kiosco.</p>
                    </div>
                </div>

                <div class="callout callout-info">
                    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
                    <p>Los cupos web y kiosco no pueden superar la cantidad de espacios que permite la franja según la duración de cita del médico.</p>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Los cambios se registran en auditoría.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-franja-submit>Crear franja</button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="modal-bloqueo" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-bloqueo-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Agenda</span>
                <h2 id="modal-bloqueo-title">Nuevo bloqueo</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-bloqueo-form novalidate>
            <div class="modal-body">
                <div class="field-grid field-grid-2">
                    <div class="field">
                        <label for="bloqueo-fecha">Fecha *</label>
                        <input id="bloqueo-fecha" name="fecha" type="date" required>
                    </div>
                    <div class="field">
                        <label for="bloqueo-motivo">Motivo</label>
                        <input id="bloqueo-motivo" name="motivo" type="text" maxlength="120" placeholder="Ej. Congreso médico">
                    </div>
                </div>

                <label class="check-card">
                    <input type="checkbox" name="todo_el_dia" checked>
                    <span class="check-card-box" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                    <span>
                        <strong>Todo el día</strong>
                        <small>Bloquea la agenda completa de esa fecha.</small>
                    </span>
                </label>

                <div class="field-grid field-grid-2" data-bloqueo-horas hidden>
                    <div class="field">
                        <label for="bloqueo-inicio">Hora de inicio</label>
                        <input id="bloqueo-inicio" name="hora_inicio" type="time">
                    </div>
                    <div class="field">
                        <label for="bloqueo-fin">Hora de fin</label>
                        <input id="bloqueo-fin" name="hora_fin" type="time">
                    </div>
                </div>

                <div class="callout callout-warning">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg>
                    <p>Si indicas un bloqueo parcial, completa ambas horas. El bloqueo no elimina citas ya agendadas.</p>
                </div>
            </div>

            <footer class="modal-foot">
                <span class="modal-foot-note">Los bloqueos se conservan en la agenda.</span>
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit" data-bloqueo-submit>Crear bloqueo</button>
            </footer>
        </form>
    </div>
</div>
