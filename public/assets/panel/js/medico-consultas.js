import {
    $,
    apiErrorMessage,
    apiFetch,
    clearFieldErrors,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    patientName,
    renderFieldErrors,
    setLoading,
    statusBadge,
    toast,
    todayISO,
} from './app.js';
import {
    consultationHref,
    loadDoctor,
    patientHref,
    pedidoHref,
    renderAgendaList,
    renderPatientProfile,
    requireMedicoId,
} from './medico-shared.js';

const citaId = Number(new URLSearchParams(window.location.search).get('cita_id') || 0);
const pending = $('[data-consulta-pending]');
const workspace = $('[data-consulta-workspace]');
const form = $('[data-consulta-form]');
const title = $('[data-consulta-title]');
const subtitle = $('[data-consulta-subtitle]');
const badge = $('[data-consulta-badge]');
const patientCard = $('[data-consulta-patient]');
const context = $('[data-consulta-context]');
const existing = $('[data-consulta-existing]');
const submit = $('[data-consulta-submit]');
const pedidosLink = $('[data-consulta-pedidos-link]');

function showPending() {
    if (workspace) {
        workspace.hidden = true;
    }
    if (pending) {
        pending.hidden = false;
    }
}

function showWorkspace() {
    if (pending) {
        pending.hidden = true;
    }
    if (workspace) {
        workspace.hidden = false;
    }
}

async function loadPending() {
    if (!pending) {
        return;
    }

    if (!requireMedicoId()) {
        pending.innerHTML = '<div class="agenda-empty"><p>No hay un médico asociado a tu cuenta.</p></div>';
        return;
    }

    pending.innerHTML = `
        <div class="table-loading">
            <div class="skeleton skeleton-row"></div>
            <div class="skeleton skeleton-row"></div>
            <div class="skeleton skeleton-row"></div>
        </div>
    `;

    try {
        const response = await apiFetch(`/api/medicos/${requireMedicoId()}/agenda?fecha=${encodeURIComponent(todayISO())}`);
        const citas = (response?.data?.citas || [])
            .filter((cita) => ['programada', 'confirmada'].includes(String(cita.estado)));

        renderAgendaList(pending, citas, {
            emptyMessage: 'No tienes citas pendientes para hoy. Puedes revisar tu agenda completa.',
        });
    } catch (error) {
        pending.innerHTML = `
            <div class="error-state">
                <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                <h2>No pudimos cargar tus citas pendientes</h2>
                <p>${escapeHtml(apiErrorMessage(error))}</p>
                <button class="btn btn-ghost btn-sm" type="button" data-consulta-retry>
                    Reintentar
                    <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                </button>
            </div>
        `;
        pending.querySelector('[data-consulta-retry]')?.addEventListener('click', loadPending);
    }
}

function fillForm(cita) {
    if (!form) {
        return;
    }

    const motivo = form.querySelector('[name="motivo"]');
    if (motivo && !motivo.value) {
        motivo.value = cita.motivo || '';
    }
}

function renderExisting(consulta) {
    if (!form) {
        return;
    }

    form.hidden = true;
    if (existing) {
        existing.hidden = false;
        existing.innerHTML = `
            <div class="callout callout-info">
                <svg class="icon" aria-hidden="true"><use href="#i-check-circle"></use></svg>
                <p>Esta cita ya tiene una consulta registrada. Puedes revisarla en la historia clínica o emitir recetas y órdenes.</p>
            </div>
            <div class="history-grid" style="margin-top:12px;">
                <div class="history-block">
                    <h3>Diagnóstico</h3>
                    <div class="history-row"><span>Diagnóstico</span><p>${escapeHtml(consulta.diagnostico || '—')}</p></div>
                    <div class="history-row"><span>Tratamiento</span><p>${escapeHtml(consulta.tratamiento || '—')}</p></div>
                    <div class="history-row"><span>Indicaciones</span><p>${escapeHtml(consulta.indicaciones || '—')}</p></div>
                </div>
                <div class="history-block">
                    <h3>Registro</h3>
                    <div class="history-row"><span>Fecha</span><p>${escapeHtml(formatDateNumeric(consulta.fecha))}</p></div>
                    <div class="history-row"><span>Motivo</span><p>${escapeHtml(consulta.motivo || '—')}</p></div>
                </div>
            </div>
        `;
    }

    if (pedidosLink) {
        pedidosLink.hidden = false;
        pedidosLink.href = pedidoHref(consulta.id);
    }
}

async function loadConsulta() {
    if (!citaId) {
        showPending();
        await loadPending();
        return;
    }

    showWorkspace();
    if (form) {
        form.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        `;
    }

    try {
        const [citaResponse, doctor] = await Promise.all([
            apiFetch(`/api/citas/${citaId}`),
            loadDoctor(),
        ]);
        const cita = citaResponse?.data || null;

        if (!cita) {
            throw new Error('La cita no existe.');
        }

        const [patientResponse, historiaResponse] = await Promise.all([
            apiFetch(`/api/pacientes/${cita.paciente_id}`).catch(() => null),
            apiFetch(`/api/pacientes/${cita.paciente_id}/historia`),
        ]);
        const paciente = historiaResponse?.data?.paciente || patientResponse?.data || cita.paciente || {};
        const consultas = Array.isArray(historiaResponse?.data?.consultas) ? historiaResponse.data.consultas : [];
        const consulta = consultas.find((item) => Number(item.cita_id) === Number(cita.id)) || null;

        if (title) {
            title.textContent = `Consulta — ${patientName(cita.paciente)}`;
        }

        if (subtitle) {
            subtitle.textContent = [
                `CI ${cita.paciente?.ci || '—'}`,
                paciente.edad ? `${paciente.edad} años` : null,
                cita.especialidad?.nombre || null,
                `${formatDateNumeric(cita.fecha)} · ${formatTimeValue(cita.hora_inicio)}`,
                cita.codigo || null,
            ].filter(Boolean).join(' · ');
        }

        if (badge) {
            badge.innerHTML = statusBadge(cita.estado);
        }

        renderPatientProfile(patientCard, paciente, {
            consultas: consultas.length,
            citas: 0,
        });

        if (context) {
            const recent = consultas.slice(0, 3);
            context.innerHTML = recent.length > 0
                ? recent.map((item) => `
                    <div class="history-row">
                        <span>${escapeHtml(formatDateNumeric(item.fecha))}</span>
                        <p>${escapeHtml(item.diagnostico || item.motivo || 'Consulta')}</p>
                    </div>
                `).join('')
                : '<p class="text-muted">Sin atenciones previas registradas.</p>';
        }

        if (consulta) {
            renderExisting(consulta);
            return;
        }

        if (!['programada', 'confirmada'].includes(String(cita.estado))) {
            if (form) {
                form.innerHTML = `
                    <div class="callout callout-warning">
                        <svg class="icon" aria-hidden="true"><use href="#i-alert"></use></svg>
                        <p>No puedes registrar una consulta para una cita en estado ${escapeHtml(cita.estado)}.</p>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="${patientHref(cita.paciente_id)}" style="margin-top:10px;">
                        Ver historia clínica
                    </a>
                `;
            }
            return;
        }

        renderForm(cita, paciente, doctor);
    } catch (error) {
        const message = apiErrorMessage(error);
        if (form) {
            form.innerHTML = `
                <div class="error-state">
                    <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <h2>No pudimos cargar la cita</h2>
                    <p>${escapeHtml(message)}</p>
                    <a class="btn btn-ghost btn-sm" href="/medico/agenda">Volver a mi agenda</a>
                </div>
            `;
        }
        toast('error', message);
    }
}

function renderForm(cita, paciente, doctor) {
    if (!form) {
        return;
    }

    const nombreDoctor = doctor ? `Dr. ${doctor.nombres || ''} ${doctor.apellidos || ''}`.trim() : '';

    form.hidden = false;
    form.innerHTML = `
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
                    <label for="con-motivo">Motivo de consulta *</label>
                    <input id="con-motivo" name="motivo" type="text" maxlength="200" required
                           value="${escapeHtml(cita.motivo || '')}" placeholder="Motivo principal de la atención">
                </div>
                <div class="field field-span-2">
                    <label for="con-anamnesis">Anamnesis</label>
                    <textarea id="con-anamnesis" name="anamnesis" rows="3" maxlength="4000"
                              placeholder="Relato del paciente, antecedentes y evolución"></textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <span class="form-step">02</span>
                <div>
                    <h3>Signos vitales</h3>
                    <p>Opcionales. Valores numéricos sin unidad; la presión arterial se escribe como 120/80.</p>
                </div>
            </div>
            <div class="field-grid field-grid-3">
                <div class="field">
                    <label for="con-pa">Presión arterial</label>
                    <input id="con-pa" name="pa" type="text" maxlength="20" placeholder="120/80">
                </div>
                <div class="field">
                    <label for="con-fc">Frecuencia cardíaca</label>
                    <input id="con-fc" name="fc" type="number" step="1" min="0" placeholder="78">
                </div>
                <div class="field">
                    <label for="con-temp">Temperatura</label>
                    <input id="con-temp" name="temp" type="number" step="0.1" min="0" placeholder="36.6">
                </div>
                <div class="field">
                    <label for="con-peso">Peso</label>
                    <input id="con-peso" name="peso" type="number" step="0.1" min="0" placeholder="82.5">
                </div>
                <div class="field">
                    <label for="con-spo2">Saturación O₂</label>
                    <input id="con-spo2" name="spo2" type="number" step="1" min="0" placeholder="98">
                </div>
                <div class="field">
                    <label for="con-talla">Talla</label>
                    <input id="con-talla" name="talla" type="number" step="0.01" min="0" placeholder="1.75">
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
                    <textarea id="con-examen" name="examen_fisico" rows="2" maxlength="4000"></textarea>
                </div>
                <div class="field field-span-2">
                    <label for="con-dx">Diagnóstico *</label>
                    <input id="con-dx" name="diagnostico" type="text" maxlength="4000" required>
                </div>
                <div class="field field-span-2">
                    <label for="con-tx">Tratamiento</label>
                    <textarea id="con-tx" name="tratamiento" rows="2" maxlength="4000"></textarea>
                </div>
                <div class="field field-span-2">
                    <label for="con-indicaciones">Indicaciones</label>
                    <textarea id="con-indicaciones" name="indicaciones" rows="2" maxlength="4000"></textarea>
                </div>
            </div>
        </div>

        <div class="callout callout-info">
            <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
            <p>${nombreDoctor ? `${escapeHtml(nombreDoctor)} · ` : ''}Al guardar, la cita pasa a atendida y la consulta queda en la historia del paciente.</p>
        </div>

        <footer class="modal-foot" style="padding:0;border-top:1px solid var(--panel-line);margin-top:4px;padding-top:16px;">
            <a class="btn btn-ghost btn-sm" href="/medico/agenda">Volver a la agenda</a>
            <button class="btn btn-primary btn-sm" type="submit" data-consulta-submit>
                <span class="btn-label">Guardar consulta</span>
                <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-check"></use></svg></span>
            </button>
        </footer>
    `;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await saveConsulta(cita);
    });
}

function numericValue(value) {
    const raw = String(value ?? '').trim();
    if (raw === '') {
        return null;
    }
    const number = Number(raw);
    return Number.isFinite(number) ? number : null;
}

async function saveConsulta(cita) {
    if (!form) {
        return;
    }

    clearFieldErrors(form);
    const data = new FormData(form);
    const motivo = String(data.get('motivo') || '').trim();
    const diagnostico = String(data.get('diagnostico') || '').trim();

    if (motivo === '') {
        renderFieldErrors(form, { motivo: 'Este campo es obligatorio.' });
        return;
    }

    if (diagnostico === '') {
        renderFieldErrors(form, { diagnostico: 'Este campo es obligatorio.' });
        return;
    }

    const signs = {};
    const pa = String(data.get('pa') || '').trim();
    if (pa !== '') {
        signs.pa = pa;
    }

    for (const key of ['fc', 'temp', 'peso', 'spo2', 'talla']) {
        const raw = String(data.get(key) || '').trim();
        if (raw === '') {
            continue;
        }
        const value = numericValue(raw);
        if (value === null) {
            renderFieldErrors(form, { [key]: 'Ingresa un valor numérico.' });
            return;
        }
        signs[key] = value;
    }

    const body = {
        motivo,
        anamnesis: String(data.get('anamnesis') || '').trim() || null,
        examen_fisico: String(data.get('examen_fisico') || '').trim() || null,
        diagnostico,
        tratamiento: String(data.get('tratamiento') || '').trim() || null,
        indicaciones: String(data.get('indicaciones') || '').trim() || null,
        signos_vitales: Object.keys(signs).length > 0 ? signs : null,
    };

    const button = form.querySelector('[data-consulta-submit]') || submit;
    setLoading(button, true);

    try {
        const response = await apiFetch(`/api/citas/${cita.id}/consulta`, {
            method: 'POST',
            body,
        });
        toast('success', response?.message || 'Consulta registrada.');
        window.location.assign(consultationHref(cita.id));
    } catch (error) {
        renderFieldErrors(form, error?.fields || {});
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(button, false);
    }
}

loadConsulta();
