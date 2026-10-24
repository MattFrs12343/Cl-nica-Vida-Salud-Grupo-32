<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Agenda de recepción</span>
        <h1>Citas</h1>
        <p>Registra una cita, mueve un horario o resuelve una cancelación sin salir de aquí.</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary btn-sm" type="button" data-cita-new>
            Nueva cita
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar-plus"></use></svg></span>
        </button>
    </div>
</header>

<section class="toolbar card">
    <div class="toolbar-search">
        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" placeholder="Paciente, CI, código o médico…" aria-label="Buscar citas"
               autocomplete="off" data-cita-search>
        <button class="toolbar-clear" type="button" data-cita-clear aria-label="Limpiar búsqueda" hidden>
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    </div>
    <div class="toolbar-filters">
        <label class="input-inline" aria-label="Fecha de la cita">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            <input type="date" data-cita-fecha value="<?= e(date('Y-m-d')) ?>">
        </label>
        <label class="select-wrap">
            <span class="sr-only">Filtrar por estado</span>
            <select data-cita-estado>
                <option value="">Todos los estados</option>
                <option value="programada">Programadas</option>
                <option value="confirmada">Confirmadas</option>
                <option value="atendida">Atendidas</option>
                <option value="cancelada">Canceladas</option>
                <option value="inasistencia">Inasistencias</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <label class="select-wrap">
            <span class="sr-only">Filtrar por médico</span>
            <select data-cita-medico>
                <option value="">Todos los médicos</option>
            </select>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
        </label>
        <button class="icon-btn" type="button" data-cita-refresh aria-label="Actualizar listado">
            <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
        </button>
    </div>
    <p class="toolbar-count" data-cita-count>—</p>
</section>

<section class="card" aria-labelledby="lista-citas-title">
    <div class="card-head">
        <div>
            <span class="card-kicker">Resultados</span>
            <h2 id="lista-citas-title">Citas registradas</h2>
        </div>
        <span class="badge badge-muted" data-cita-day-label><?= e(date('d/m/Y')) ?></span>
    </div>
    <div class="table-wrap" data-citas-table>
        <div class="table-loading">
            <?php for ($i = 0; $i < 7; $i++): ?>
                <div class="skeleton skeleton-row"></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="pagination" data-cita-pagination hidden></div>
</section>

<div class="modal" id="modal-cita" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-lg" role="dialog" aria-modal="true" aria-labelledby="modal-cita-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Recepción</span>
                <h2 id="modal-cita-title">Nueva cita</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>

        <form data-cita-form novalidate>
            <div class="modal-body">
                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">01</span>
                        <div>
                            <h3>Paciente</h3>
                            <p>Busca por nombre, apellido o cédula.</p>
                        </div>
                    </div>
                    <div class="patient-picker">
                        <div class="input-wrap">
                            <svg class="icon input-icon" aria-hidden="true"><use href="#i-search"></use></svg>
                            <input type="search" placeholder="Ej. María Gómez o 87654321"
                                   autocomplete="off" data-cita-patient-search aria-label="Buscar paciente">
                        </div>
                        <div class="picker-results" data-cita-patient-results hidden></div>
                        <div class="picker-selected" data-cita-patient-selected hidden></div>
                        <input type="hidden" name="paciente_id" data-cita-patient-id>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <span class="form-step">02</span>
                        <div>
                            <h3>Atención</h3>
                            <p>Elige especialidad, médico y una fecha con cupo.</p>
                        </div>
                    </div>
                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="cita-especialidad">Especialidad *</label>
                            <div class="select-wrap">
                                <select id="cita-especialidad" name="especialidad_id" required data-cita-especialidad>
                                    <option value="">Selecciona una especialidad</option>
                                </select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                        <div class="field">
                            <label for="cita-medico">Médico *</label>
                            <div class="select-wrap">
                                <select id="cita-medico" name="medico_id" required data-cita-medico-select disabled>
                                    <option value="">Primero elige una especialidad</option>
                                </select>
                                <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                            </div>
                        </div>
                        <div class="field field-span-2">
                            <label for="cita-fecha">Fecha *</label>
                            <input id="cita-fecha" name="fecha" type="date" min="<?= e(date('Y-m-d')) ?>"
                                   value="<?= e(date('Y-m-d')) ?>" required data-cita-fecha-form>
                        </div>
                    </div>

                    <div class="slot-field">
                        <div class="field-label-row">
                            <span class="field-label">Horarios disponibles</span>
                            <span class="field-hint" data-cita-slot-hint>Elige médico y fecha</span>
                        </div>
                        <div class="slot-grid" data-cita-slots>
                            <p class="slot-placeholder">Los horarios aparecerán aquí.</p>
                        </div>
                        <input type="hidden" name="hora" data-cita-hora>
                    </div>

                    <div class="field-grid field-grid-2">
                        <div class="field">
                            <label for="cita-motivo">Motivo de consulta</label>
                            <input id="cita-motivo" name="motivo" type="text" maxlength="200" placeholder="Ej. Control general">
                        </div>
                        <label class="check-card">
                            <input type="checkbox" name="es_prioritaria" value="1">
                            <span class="check-card-box" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                            <span><strong>Marcar como prioritaria</strong><small>Solo si requiere atención preferente.</small></span>
                        </label>
                    </div>
                </div>
            </div>

            <footer class="modal-foot">
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit">
                    <span class="btn-label">Registrar cita</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="modal-reprogramar" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-reprogramar-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Reprogramación</span>
                <h2 id="modal-reprogramar-title">Mover una cita</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>
        <form data-reprog-form novalidate>
            <div class="modal-body">
                <div class="appointment-summary" data-reprog-info></div>
                <div class="field-grid">
                    <div class="field">
                        <label for="reprog-fecha">Nueva fecha *</label>
                        <input id="reprog-fecha" name="fecha" type="date" min="<?= e(date('Y-m-d')) ?>" required data-reprog-fecha>
                    </div>
                </div>
                <div class="slot-field">
                    <div class="field-label-row">
                        <span class="field-label">Horarios disponibles</span>
                        <span class="field-hint" data-reprog-slot-hint>Selecciona una fecha</span>
                    </div>
                    <div class="slot-grid" data-reprog-slots>
                        <p class="slot-placeholder">Los horarios aparecerán aquí.</p>
                    </div>
                    <input type="hidden" name="hora" data-reprog-hora>
                </div>
                <div class="field">
                    <label for="reprog-motivo">Motivo del cambio <span class="label-hint">(opcional)</span></label>
                    <input id="reprog-motivo" name="motivo" type="text" maxlength="200" placeholder="Ej. Solicitud del paciente">
                </div>
            </div>
            <footer class="modal-foot">
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit">
                    <span class="btn-label">Reprogramar</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="modal" id="modal-cancelar" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel modal-panel-sm" role="dialog" aria-modal="true" aria-labelledby="modal-cancelar-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Cancelación</span>
                <h2 id="modal-cancelar-title">Cancelar cita</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>
        <form data-cancel-form novalidate>
            <div class="modal-body">
                <div class="appointment-summary" data-cancel-info></div>
                <div class="callout callout-warning">
                    <svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg>
                    <p>Solo se puede cancelar hasta el día anterior. La cita quedará registrada como cancelada.</p>
                </div>
                <div class="field">
                    <label for="cancel-motivo">Motivo *</label>
                    <textarea id="cancel-motivo" name="motivo" rows="3" maxlength="200" placeholder="Ej. El paciente no podrá asistir" required></textarea>
                </div>
            </div>
            <footer class="modal-foot">
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Volver</button>
                <button class="btn btn-danger btn-sm" type="submit">
                    <span class="btn-label">Cancelar cita</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-close"></use></svg></span>
                </button>
            </footer>
        </form>
    </div>
</div>

<?php require app_path('views/recepcion/partials/modal-reconsulta.php'); ?>
