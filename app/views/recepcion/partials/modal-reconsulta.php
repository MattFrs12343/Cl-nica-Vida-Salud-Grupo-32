<div class="modal" id="modal-reconsulta" data-modal aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-reconsulta-title">
        <header class="modal-head">
            <div>
                <span class="modal-kicker">Continuidad clínica</span>
                <h2 id="modal-reconsulta-title">Nueva reconsulta</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
            </button>
        </header>
        <form data-reconsulta-form novalidate>
            <div class="modal-body">
                <div class="appointment-summary" data-reconsulta-info></div>

                <div class="reconsulta-type" data-reconsulta-type-box>
                    <span class="reconsulta-type-icon" aria-hidden="true"><svg class="icon"><use href="#i-refresh"></use></svg></span>
                    <div>
                        <strong data-reconsulta-type-label>Reconsulta</strong>
                        <p data-reconsulta-type-help>Selecciona una cita de origen.</p>
                    </div>
                </div>
                <input type="hidden" name="tipo" data-reconsulta-tipo>

                <div class="field-grid">
                    <div class="field">
                        <label for="reconsulta-fecha">Fecha *</label>
                        <input id="reconsulta-fecha" name="fecha" type="date" min="<?= e(date('Y-m-d')) ?>" required data-reconsulta-fecha>
                    </div>
                </div>

                <div class="slot-field">
                    <div class="field-label-row">
                        <span class="field-label">Horarios disponibles</span>
                        <span class="field-hint" data-reconsulta-slot-hint>Selecciona una fecha</span>
                    </div>
                    <div class="slot-grid" data-reconsulta-slots>
                        <p class="slot-placeholder">Los horarios aparecerán aquí.</p>
                    </div>
                    <input type="hidden" name="hora" data-reconsulta-hora>
                </div>

                <div class="field">
                    <label for="reconsulta-motivo">Motivo <span class="label-hint">(opcional)</span></label>
                    <input id="reconsulta-motivo" name="motivo" type="text" maxlength="200"
                           placeholder="Ej. Control de resultados">
                </div>

                <div class="callout callout-info">
                    <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
                    <p>La reconsulta conserva el médico y la especialidad de la cita original. Si es por ausencia del médico, la cita original se cancela automáticamente.</p>
                </div>
            </div>
            <footer class="modal-foot">
                <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary btn-sm" type="submit">
                    <span class="btn-label">Crear reconsulta</span>
                    <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-refresh"></use></svg></span>
                </button>
            </footer>
        </form>
    </div>
</div>
