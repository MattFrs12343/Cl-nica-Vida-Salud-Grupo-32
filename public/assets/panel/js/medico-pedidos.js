import {
    $,
    $$,
    apiErrorMessage,
    apiFetch,
    clearFieldErrors,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    patientName,
    renderFieldErrors,
    setLoading,
    toast,
} from './app.js';
import { PatientPicker } from './recepcion-shared.js';
import {
    MEDICO_ID,
    pedidoHref,
    requireMedicoId,
} from './medico-shared.js';

const SESSION_KEY = `medico:pedidos:${MEDICO_ID}`;
const params = new URLSearchParams(window.location.search);
const initialConsultaId = Number(params.get('consulta_id') || 0);
const initialPatientId = Number(params.get('paciente_id') || 0);
const initialCitaId = Number(params.get('cita_id') || 0);

const search = $('[data-pedido-search]');
const results = $('[data-pedido-results]');
const selected = $('[data-pedido-selected]');
const hidden = $('[data-pedido-patient-id]');
const consultasContainer = $('[data-pedido-consultas]');
const workspace = $('[data-pedido-workspace]');
const patientNode = $('[data-pedido-patient]');
const metaNode = $('[data-pedido-meta]');
const resultNode = $('[data-pedido-result]');
const sessionNode = $('[data-pedidos-sesion]');
const sessionCount = $('[data-pedidos-sesion-count]');
const recetaDetalle = $('[data-receta-detalle]');
const ordenDetalle = $('[data-orden-detalle]');

let currentPatient = null;
let currentConsulta = null;
let sessionItems = readSession();

const picker = new PatientPicker({
    search,
    results,
    selected,
    hidden,
    onSelect: (paciente) => {
        currentPatient = paciente || null;
        currentConsulta = null;
        renderWorkspace();

        if (paciente) {
            loadPatientConsultations(Number(paciente.id));
        } else {
            renderConsultas([]);
        }
    },
});

function readSession() {
    try {
        const raw = window.sessionStorage.getItem(SESSION_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
}

function writeSession() {
    try {
        window.sessionStorage.setItem(SESSION_KEY, JSON.stringify(sessionItems));
    } catch (error) {
        // El almacenamiento puede estar deshabilitado; la vista sigue funcionando.
    }
}

function labelTipo(tipo) {
    return { receta: 'Receta', documento: 'Documento', orden: 'Orden' }[tipo] || tipo;
}

function printUrl(tipo, id) {
    if (tipo === 'receta') {
        return `/api/recetas/${id}/imprimir`;
    }
    if (tipo === 'documento') {
        return `/api/documentos/${id}/imprimir`;
    }
    return `/api/ordenes/${id}/imprimir`;
}

function renderConsultas(consultas) {
    if (!consultasContainer) {
        return;
    }

    if (!Array.isArray(consultas) || consultas.length === 0) {
        consultasContainer.innerHTML = `
            <div class="empty-state" style="padding:18px 12px;">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
                <h2>Sin consultas</h2>
                <p>Selecciona otro paciente o registra una consulta primero.</p>
            </div>
        `;
        return;
    }

    consultasContainer.innerHTML = consultas.map((consulta) => `
        <button class="stack-link" type="button" data-pedido-consulta="${escapeHtml(consulta.id)}">
            <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-clipboard"></use></svg></span>
            <span>
                <strong>${escapeHtml(formatDateNumeric(consulta.fecha))} · ${escapeHtml(consulta.motivo || 'Consulta')}</strong>
                <small>${escapeHtml(consulta.diagnostico || 'Sin diagnóstico registrado')}</small>
            </span>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
        </button>
    `).join('');

    consultasContainer.querySelectorAll('[data-pedido-consulta]').forEach((button) => {
        button.addEventListener('click', () => {
            const consulta = consultas.find((item) => Number(item.id) === Number(button.dataset.pedidoConsulta));
            if (consulta) {
                selectConsulta(consulta);
            }
        });
    });
}

function renderWorkspace() {
    if (!workspace) {
        return;
    }

    if (!currentConsulta) {
        workspace.hidden = true;
        return;
    }

    workspace.hidden = false;

    if (patientNode) {
        patientNode.textContent = currentPatient ? patientName(currentPatient) : 'Paciente';
    }

    if (metaNode) {
        metaNode.textContent = [
            formatDateNumeric(currentConsulta.fecha),
            currentConsulta.cita?.hora_inicio ? formatTimeValue(currentConsulta.cita.hora_inicio) : null,
            currentConsulta.diagnostico || null,
        ].filter(Boolean).join(' · ');
    }
}

function selectConsulta(consulta) {
    currentConsulta = consulta;
    renderWorkspace();

    const url = new URL(window.location.href);
    url.searchParams.set('consulta_id', String(consulta.id));
    url.searchParams.delete('cita_id');
    url.searchParams.delete('paciente_id');
    window.history.replaceState({}, '', url);

    if (resultNode) {
        resultNode.hidden = true;
        resultNode.innerHTML = '';
    }
}

async function loadPatientConsultations(pacienteId) {
    if (!pacienteId) {
        renderConsultas([]);
        return;
    }

    if (consultasContainer) {
        consultasContainer.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        `;
    }

    try {
        const response = await apiFetch(`/api/pacientes/${pacienteId}/historia`);
        const paciente = response?.data?.paciente || currentPatient;
        const consultas = Array.isArray(response?.data?.consultas) ? response.data.consultas : [];

        currentPatient = paciente || currentPatient;
        renderConsultas(consultas);

        if (initialCitaId > 0) {
            const porCita = consultas.find((item) => Number(item.cita_id) === initialCitaId);
            if (porCita) {
                selectConsulta(porCita);
            }
        }
    } catch (error) {
        if (consultasContainer) {
            consultasContainer.innerHTML = `<p class="picker-empty">${escapeHtml(apiErrorMessage(error))}</p>`;
        }
        toast('error', apiErrorMessage(error));
    }
}

function addRecetaRow(values = {}) {
    if (!recetaDetalle) {
        return;
    }

    const row = document.createElement('div');
    row.className = 'history-block';
    row.dataset.recetaRow = '';
    row.style.marginBottom = '8px';
    row.innerHTML = `
        <div class="field-grid field-grid-2">
            <div class="field field-span-2">
                <label>Medicamento *</label>
                <input type="text" maxlength="150" data-receta-field="medicamento" placeholder="Ej. Losartán 50 mg" value="${escapeHtml(values.medicamento || '')}">
            </div>
            <div class="field">
                <label>Dosis</label>
                <input type="text" maxlength="80" data-receta-field="dosis" placeholder="1 tableta" value="${escapeHtml(values.dosis || '')}">
            </div>
            <div class="field">
                <label>Vía</label>
                <input type="text" maxlength="50" data-receta-field="via" placeholder="Oral" value="${escapeHtml(values.via || '')}">
            </div>
            <div class="field">
                <label>Frecuencia</label>
                <input type="text" maxlength="80" data-receta-field="frecuencia" placeholder="Cada 12 horas" value="${escapeHtml(values.frecuencia || '')}">
            </div>
            <div class="field">
                <label>Duración</label>
                <input type="text" maxlength="50" data-receta-field="duracion" placeholder="30 días" value="${escapeHtml(values.duracion || '')}">
            </div>
            <div class="field field-span-2">
                <label>Instrucciones</label>
                <input type="text" maxlength="200" data-receta-field="instrucciones" placeholder="Tomar después de las comidas" value="${escapeHtml(values.instrucciones || '')}">
            </div>
        </div>
        <div class="row-actions" style="margin-top:4px;">
            <button class="btn btn-ghost btn-sm" type="button" data-remove-receta>
                <svg class="icon" aria-hidden="true"><use href="#i-trash"></use></svg>
                Quitar
            </button>
        </div>
    `;

    row.querySelector('[data-remove-receta]')?.addEventListener('click', () => {
        if (recetaDetalle.querySelectorAll('[data-receta-row]').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
            });
        }
    });

    recetaDetalle.appendChild(row);
}

function addOrdenRow(values = {}) {
    if (!ordenDetalle) {
        return;
    }

    const row = document.createElement('div');
    row.className = 'history-block';
    row.dataset.ordenRow = '';
    row.style.marginBottom = '8px';
    row.innerHTML = `
        <div class="field-grid">
            <div class="field field-span-2">
                <label>Estudio *</label>
                <input type="text" maxlength="150" data-orden-field="estudio" placeholder="Ej. Hemograma completo" value="${escapeHtml(values.estudio || '')}">
            </div>
            <div class="field field-span-2">
                <label>Observaciones</label>
                <input type="text" maxlength="200" data-orden-field="observaciones" placeholder="Indicaciones para el laboratorio" value="${escapeHtml(values.observaciones || '')}">
            </div>
        </div>
        <div class="row-actions" style="margin-top:4px;">
            <button class="btn btn-ghost btn-sm" type="button" data-remove-orden>
                <svg class="icon" aria-hidden="true"><use href="#i-trash"></use></svg>
                Quitar
            </button>
        </div>
    `;

    row.querySelector('[data-remove-orden]')?.addEventListener('click', () => {
        if (ordenDetalle.querySelectorAll('[data-orden-row]').length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => {
                input.value = '';
            });
        }
    });

    ordenDetalle.appendChild(row);
}

function renderResult(tipo, item) {
    if (!resultNode) {
        return;
    }

    const codigo = item.codigo || item.codigo_acceso || `#${item.id}`;
    const detail = tipo === 'receta'
        ? `${(item.detalle || []).length} medicamento${(item.detalle || []).length === 1 ? '' : 's'}`
        : (tipo === 'orden'
            ? `${(item.detalle || []).length} estudio${(item.detalle || []).length === 1 ? '' : 's'}`
            : (item.tipo || 'Documento'));

    resultNode.hidden = false;
    resultNode.innerHTML = `
        <div class="callout callout-success">
            <svg class="icon" aria-hidden="true"><use href="#i-check-circle"></use></svg>
            <p>${escapeHtml(labelTipo(tipo))} emitida: <strong>${escapeHtml(codigo)}</strong> · ${escapeHtml(detail)}</p>
        </div>
        <div class="row-actions" style="margin-top:10px;">
            <a class="btn btn-ghost btn-sm" href="${printUrl(tipo, item.id)}" target="_blank" rel="noopener">
                <svg class="icon" aria-hidden="true"><use href="#i-print"></use></svg>
                Imprimir
            </a>
            <button class="btn btn-primary btn-sm" type="button" data-enviar-pedido data-tipo="${escapeHtml(tipo)}" data-id="${escapeHtml(item.id)}">
                <svg class="icon" aria-hidden="true"><use href="#i-mail"></use></svg>
                Enviar por WhatsApp
            </button>
        </div>
    `;

    resultNode.querySelector('[data-enviar-pedido]')?.addEventListener('click', (event) => {
        sendPedido(event.currentTarget.dataset.tipo, Number(event.currentTarget.dataset.id), event.currentTarget);
    });
}

function addToSession(tipo, item) {
    const entry = {
        tipo,
        id: Number(item.id),
        codigo: item.codigo || '',
        paciente: currentPatient ? patientName(currentPatient) : 'Paciente',
        fecha: new Date().toISOString(),
        enviado: Boolean(item.enviado_whatsapp),
    };

    sessionItems = [entry, ...sessionItems.filter((item) => !(item.tipo === entry.tipo && item.id === entry.id))].slice(0, 12);
    writeSession();
    renderSession();
}

function renderSession() {
    if (!sessionNode) {
        return;
    }

    if (sessionCount) {
        sessionCount.textContent = `${sessionItems.length} pedido${sessionItems.length === 1 ? '' : 's'} en esta sesión`;
    }

    if (sessionItems.length === 0) {
        sessionNode.innerHTML = `
            <div class="empty-state" style="padding:18px 12px;">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-ticket"></use></svg></span>
                <h2>Sin pedidos en esta sesión</h2>
                <p>Los pedidos emitidos aparecerán aquí mientras no cierres sesión.</p>
            </div>
        `;
        return;
    }

    sessionNode.innerHTML = `
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>Tipo</th><th>Código</th><th>Paciente</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr>
                </thead>
                <tbody>
                    ${sessionItems.map((item) => `
                        <tr>
                            <td><span class="badge badge-muted">${escapeHtml(labelTipo(item.tipo))}</span></td>
                            <td><span class="cell-code">${escapeHtml(item.codigo || `#${item.id}`)}</span></td>
                            <td>${escapeHtml(item.paciente)}</td>
                            <td>${item.enviado ? '<span class="badge badge-pine">Enviado</span>' : '<span class="badge badge-muted">Sin enviar</span>'}</td>
                            <td>
                                <div class="row-actions">
                                    <a class="row-action" href="${printUrl(item.tipo, item.id)}" target="_blank" rel="noopener" title="Imprimir" aria-label="Imprimir">
                                        <svg class="icon" aria-hidden="true"><use href="#i-print"></use></svg>
                                    </a>
                                    <button class="row-action" type="button" title="Enviar por WhatsApp" aria-label="Enviar por WhatsApp"
                                            data-enviar-pedido data-tipo="${escapeHtml(item.tipo)}" data-id="${escapeHtml(item.id)}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-mail"></use></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;

    sessionNode.querySelectorAll('[data-enviar-pedido]').forEach((button) => {
        button.addEventListener('click', (event) => {
            sendPedido(event.currentTarget.dataset.tipo, Number(event.currentTarget.dataset.id), event.currentTarget);
        });
    });
}

async function sendPedido(tipo, id, button) {
    setLoading(button, true);

    try {
        const response = await apiFetch(`/api/pedidos/${encodeURIComponent(tipo)}/${id}/enviar`, {
            method: 'POST',
        });
        sessionItems = sessionItems.map((item) => (
            item.tipo === tipo && Number(item.id) === Number(id)
                ? { ...item, enviado: true }
                : item
        ));
        writeSession();
        renderSession();
        toast('success', response?.message || 'Pedido enviado por WhatsApp.');
    } catch (error) {
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(button, false);
    }
}

function switchTab(tipo) {
    $$('[data-pedido-tab]').forEach((button) => {
        const active = button.dataset.pedidoTab === tipo;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    $$('[data-pedido-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.pedidoPanel !== tipo;
    });
}

$$('[data-pedido-tab]').forEach((button) => {
    button.addEventListener('click', () => switchTab(button.dataset.pedidoTab));
});

$('[data-add-receta]')?.addEventListener('click', () => addRecetaRow());
$('[data-add-orden]')?.addEventListener('click', () => addOrdenRow());

$('[data-receta-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!currentConsulta) {
        toast('error', 'Selecciona una consulta primero.');
        return;
    }

    const form = event.currentTarget;
    clearFieldErrors(form);
    const rows = $$('[data-receta-row]', recetaDetalle);
    const detalle = rows.map((row) => {
        const value = (key) => row.querySelector(`[data-receta-field="${key}"]`)?.value?.trim() || '';
        return {
            medicamento: value('medicamento'),
            dosis: value('dosis') || null,
            via: value('via') || null,
            frecuencia: value('frecuencia') || null,
            duracion: value('duracion') || null,
            instrucciones: value('instrucciones') || null,
        };
    }).filter((item) => item.medicamento !== '');

    if (detalle.length === 0) {
        toast('error', 'Agrega al menos un medicamento.');
        recetaDetalle?.querySelector('input')?.focus();
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    setLoading(button, true);

    try {
        const response = await apiFetch(`/api/consultas/${currentConsulta.id}/recetas`, {
            method: 'POST',
            body: { detalle },
        });
        const receta = response?.data;
        renderResult('receta', receta);
        addToSession('receta', receta);
        toast('success', response?.message || 'Receta registrada.');
    } catch (error) {
        renderFieldErrors(form, error?.fields || {});
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(button, false);
    }
});

$('[data-documento-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!currentConsulta) {
        toast('error', 'Selecciona una consulta primero.');
        return;
    }

    const form = event.currentTarget;
    clearFieldErrors(form);
    const data = new FormData(form);
    const tipo = String(data.get('tipo') || '');
    const contenido = String(data.get('contenido') || '').trim();

    if (contenido === '') {
        renderFieldErrors(form, { contenido: 'Escribe el contenido del documento.' });
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    setLoading(button, true);

    try {
        const response = await apiFetch(`/api/consultas/${currentConsulta.id}/documentos`, {
            method: 'POST',
            body: { tipo, contenido },
        });
        const documento = response?.data;
        renderResult('documento', documento);
        addToSession('documento', documento);
        toast('success', response?.message || 'Documento registrado.');
    } catch (error) {
        renderFieldErrors(form, error?.fields || {});
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(button, false);
    }
});

$('[data-orden-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!currentConsulta) {
        toast('error', 'Selecciona una consulta primero.');
        return;
    }

    const form = event.currentTarget;
    clearFieldErrors(form);
    const data = new FormData(form);
    const tipo = String(data.get('tipo') || '');
    const rows = $$('[data-orden-row]', ordenDetalle);
    const detalle = rows.map((row) => {
        const value = (key) => row.querySelector(`[data-orden-field="${key}"]`)?.value?.trim() || '';
        return {
            estudio: value('estudio'),
            observaciones: value('observaciones') || null,
        };
    }).filter((item) => item.estudio !== '');

    if (detalle.length === 0) {
        toast('error', 'Agrega al menos un estudio.');
        ordenDetalle?.querySelector('input')?.focus();
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    setLoading(button, true);

    try {
        const response = await apiFetch(`/api/consultas/${currentConsulta.id}/ordenes`, {
            method: 'POST',
            body: { tipo, detalle },
        });
        const orden = response?.data;
        renderResult('orden', orden);
        addToSession('orden', orden);
        toast('success', response?.message || 'Orden registrada.');
    } catch (error) {
        renderFieldErrors(form, error?.fields || {});
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(button, false);
    }
});

async function init() {
    if (!requireMedicoId()) {
        return;
    }

    addRecetaRow();
    addOrdenRow();
    renderSession();
    switchTab('receta');

    if (initialConsultaId > 0) {
        try {
            const response = await apiFetch(`/api/consultas/${initialConsultaId}`);
            const consulta = response?.data;
            const paciente = await picker.selectById(Number(consulta.paciente_id));
            if (!paciente) {
                await loadPatientConsultations(Number(consulta.paciente_id));
            }
            selectConsulta(consulta);
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
        return;
    }

    if (initialPatientId > 0) {
        await picker.selectById(initialPatientId);
        return;
    }

    if (initialCitaId > 0) {
        try {
            const response = await apiFetch(`/api/citas/${initialCitaId}`);
            const cita = response?.data;
            await picker.selectById(Number(cita.paciente_id));
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    }
}

init();
