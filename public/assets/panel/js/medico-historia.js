import {
    $,
    apiErrorMessage,
    apiFetch,
    escapeHtml,
    patientName,
    toast,
} from './app.js';
import { PatientPicker } from './recepcion-shared.js';
import {
    renderConsultasTimeline,
    renderPatientProfile,
} from './medico-shared.js';

const search = $('[data-historia-search]');
const results = $('[data-historia-results]');
const selected = $('[data-historia-selected]');
const hidden = $('[data-historia-id]');
const profile = $('[data-historia-profile]');
const timeline = $('[data-historia-timeline]');
const count = $('[data-historia-count]');

let currentId = Number(new URLSearchParams(window.location.search).get('paciente_id') || 0);

const picker = new PatientPicker({
    search,
    results,
    selected,
    hidden,
    onSelect: (paciente) => {
        if (paciente) {
            currentId = Number(paciente.id);
            const url = new URL(window.location.href);
            url.searchParams.set('paciente_id', String(currentId));
            window.history.replaceState({}, '', url);
            loadHistory(currentId);
            return;
        }

        currentId = 0;
        const url = new URL(window.location.href);
        url.searchParams.delete('paciente_id');
        window.history.replaceState({}, '', url);
        clearHistory();
    },
});

function clearHistory() {
    if (profile) {
        profile.innerHTML = '';
    }
    if (timeline) {
        timeline.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
                <h2>Selecciona un paciente</h2>
                <p>Busca por nombre o CI para consultar su evolución clínica.</p>
            </div>
        `;
    }
    if (count) {
        count.textContent = 'Sin paciente seleccionado';
    }
}

async function loadHistory(pacienteId) {
    if (!pacienteId) {
        clearHistory();
        return;
    }

    if (timeline) {
        timeline.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        `;
    }

    try {
        const [historiaResponse, citasResponse] = await Promise.all([
            apiFetch(`/api/pacientes/${pacienteId}/historia`),
            apiFetch(`/api/pacientes/${pacienteId}/historial`),
        ]);

        const historia = historiaResponse?.data || {};
        const paciente = historia.paciente || picker.patient || null;
        const consultas = Array.isArray(historia.consultas) ? historia.consultas : [];
        const citas = Array.isArray(citasResponse?.data?.citas) ? citasResponse.data.citas : [];

        renderPatientProfile(profile, paciente, {
            consultas: consultas.length,
            citas: citas.length,
        });
        renderConsultasTimeline(timeline, consultas, {
            emptyMessage: 'Este paciente todavía no tiene atenciones registradas.',
        });

        if (count) {
            count.textContent = `${consultas.length} atención${consultas.length === 1 ? '' : 'es'} registrada${consultas.length === 1 ? '' : 's'}`;
        }
    } catch (error) {
        const message = apiErrorMessage(error);
        if (timeline) {
            timeline.innerHTML = `
                <div class="error-state">
                    <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <h2>No pudimos cargar la historia</h2>
                    <p>${escapeHtml(message)}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-historia-retry>
                        Reintentar
                        <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    </button>
                </div>
            `;
            timeline.querySelector('[data-historia-retry]')?.addEventListener('click', () => loadHistory(pacienteId));
        }
        toast('error', message);
    }
}

if (currentId > 0) {
    picker.selectById(currentId).then((paciente) => {
        if (!paciente) {
            clearHistory();
        }
    });
} else {
    clearHistory();
}
