<?php
/** @var array<string, mixed> $user */
?>
<header class="page-head">
    <div class="page-head-copy">
        <span class="page-kicker"><span class="status-dot"></span> Médico · Cita CM-0041</span>
        <h1>Consulta — Juan Pérez</h1>
        <p>CI 10000001 · 41 años · Medicina General · 08:00</p>
    </div>
    <div class="page-actions">
        <span class="badge badge-info">Confirmada</span>
    </div>
</header>

<div class="dashboard-grid">
    <section class="card card-span-2" aria-labelledby="consulta-title">
        <div class="card-head">
            <div>
                <span class="card-kicker">Registro clínico</span>
                <h2 id="consulta-title">Detalle de la atención</h2>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="form-step">01</span>
                <div>
                    <h3>Motivo y anamnesis</h3>
                    <p>Por qué viene el paciente y qué refiere.</p>
                </div>
            </div>
            <div class="field-grid">
                <div class="field field-span-2">
                    <label for="con-motivo">Motivo de consulta</label>
                    <input id="con-motivo" type="text" value="Control de presión arterial">
                </div>
                <div class="field field-span-2">
                    <label for="con-anamnesis">Anamnesis</label>
                    <textarea id="con-anamnesis" rows="3">Paciente refiere cefalea ocasional desde hace una semana. Sin otros síntomas asociados. Adherente a tratamiento con losartán.</textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="form-step">02</span>
                <div>
                    <h3>Signos vitales</h3>
                    <p>Valores numéricos; rangos orientativos, no bloquean el guardado.</p>
                </div>
            </div>
            <div class="field-grid field-grid-3">
                <div class="field">
                    <label for="con-pa">Presión arterial</label>
                    <input id="con-pa" type="text" value="130/85">
                </div>
                <div class="field">
                    <label for="con-fc">Frecuencia cardíaca</label>
                    <input id="con-fc" type="text" value="78 lpm">
                </div>
                <div class="field">
                    <label for="con-temp">Temperatura</label>
                    <input id="con-temp" type="text" value="36.6 °C">
                </div>
                <div class="field">
                    <label for="con-peso">Peso</label>
                    <input id="con-peso" type="text" value="82.5 kg">
                </div>
                <div class="field">
                    <label for="con-spo2">Saturación O₂</label>
                    <input id="con-spo2" type="text" value="98%">
                </div>
                <div class="field">
                    <label for="con-talla">Talla</label>
                    <input id="con-talla" type="text" value="1.75 m">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="form-step">03</span>
                <div>
                    <h3>Examen, diagnóstico y tratamiento</h3>
                    <p>El cierre clínico de la consulta.</p>
                </div>
            </div>
            <div class="field-grid">
                <div class="field field-span-2">
                    <label for="con-examen">Examen físico</label>
                    <textarea id="con-examen" rows="2">Consciente, orientado, afebril. Sin signos de alarma.</textarea>
                </div>
                <div class="field field-span-2">
                    <label for="con-dx">Diagnóstico</label>
                    <input id="con-dx" type="text" value="Hipertensión arterial leve controlada">
                </div>
                <div class="field field-span-2">
                    <label for="con-tx">Tratamiento</label>
                    <textarea id="con-tx" rows="2">Continuar losartán 50 mg cada 12 horas.</textarea>
                </div>
                <div class="field field-span-2">
                    <label for="con-indicaciones">Indicaciones</label>
                    <textarea id="con-indicaciones" rows="2">Control en 30 días. Dieta baja en sodio.</textarea>
                </div>
            </div>
        </div>

        <footer class="modal-foot" style="padding:0;border-top:1px solid var(--panel-line);margin-top:4px;padding-top:16px;">
            <button class="btn btn-ghost btn-sm" type="button">Cancelar</button>
            <button class="btn btn-primary btn-sm" type="button">
                <span class="btn-label">Guardar consulta</span>
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
            </button>
        </footer>
    </section>

    <aside class="dashboard-side">
        <section class="card">
            <div class="card-head">
                <div>
                    <span class="card-kicker">Ficha</span>
                    <h2>Datos del paciente</h2>
                </div>
            </div>
            <div class="mini-stats">
                <div class="mini-stat">
                    <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-heart"></use></svg></span>
                    <div><strong>O+</strong><span>Tipo de sangre</span></div>
                </div>
                <div class="mini-stat">
                    <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <div><strong>Penicilina</strong><span>Alergia</span></div>
                </div>
            </div>
            <p style="font-size:12.5px;color:var(--panel-muted);margin-top:10px;">Hipertensión arterial · Losartán 50 mg</p>
        </section>

        <section class="card card-accent">
            <span class="card-kicker">Siguiente paso</span>
            <h2>Emitir recetas y órdenes</h2>
            <p>Disponibles al guardar la consulta.</p>
            <div class="quick-actions" style="display:flex;flex-direction:column;gap:8px;margin-top:10px;">
                <button class="quick-action" type="button">
                    <svg class="icon" aria-hidden="true"><use href="#i-ticket"></use></svg>
                    Nueva receta
                </button>
                <button class="quick-action" type="button">
                    <svg class="icon" aria-hidden="true"><use href="#i-file"></use></svg>
                    Nuevo documento
                </button>
                <button class="quick-action" type="button">
                    <svg class="icon" aria-hidden="true"><use href="#i-clipboard"></use></svg>
                    Nueva orden
                </button>
            </div>
        </section>
    </aside>
</div>
