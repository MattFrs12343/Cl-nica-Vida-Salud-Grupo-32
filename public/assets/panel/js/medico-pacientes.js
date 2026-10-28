import {
    $,
    apiErrorMessage,
    apiFetch,
    debounce,
    escapeHtml,
    formatDateNumeric,
    initials,
    patientName,
    statusBadge,
    toast,
} from './app.js';
import { renderPagination } from './recepcion-shared.js';
import { patientHref } from './medico-shared.js';

const table = $('[data-pacientes-table]');
const search = $('[data-pacientes-search]');
const estado = $('[data-pacientes-estado]');
const count = $('[data-pacientes-count]');
const pagination = $('[data-pacientes-pagination]');
const refresh = $('[data-pacientes-refresh]');

let page = 1;
let query = '';
let currentEstado = '';
let lastMeta = { page: 1, per_page: 12, total: 0 };

function renderRows(pacientes) {
    if (!table) {
        return;
    }

    if (!Array.isArray(pacientes) || pacientes.length === 0) {
        table.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-users"></use></svg></span>
                <h2>Sin pacientes</h2>
                <p>No encontramos pacientes atendidos con ese criterio.</p>
            </div>
        `;
        return;
    }

    table.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Edad</th>
                    <th>Última atención</th>
                    <th>Última cita</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
                ${pacientes.map((paciente) => `
                    <tr>
                        <td>
                            <div class="cell-person">
                                <span class="avatar avatar-sm">${escapeHtml(initials(patientName(paciente)))}</span>
                                <div>
                                    <strong>${escapeHtml(patientName(paciente))}</strong>
                                    <small>CI ${escapeHtml(paciente.ci || '—')}</small>
                                </div>
                            </div>
                        </td>
                        <td>${paciente.edad ? `${escapeHtml(paciente.edad)} años` : '—'}</td>
                        <td>${escapeHtml(formatDateNumeric(paciente.ultima_atencion))}</td>
                        <td>${escapeHtml(formatDateNumeric(paciente.ultima_cita))}</td>
                        <td>${statusBadge(paciente.estado)}</td>
                        <td>
                            <div class="row-actions">
                                <a class="row-action" href="${patientHref(paciente.id)}" title="Ver historia clínica" aria-label="Ver historia clínica de ${escapeHtml(patientName(paciente))}">
                                    <svg class="icon" aria-hidden="true"><use href="#i-file"></use></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

async function load() {
    const params = new URLSearchParams({
        atendidos: '1',
        page: String(page),
        per_page: '12',
    });

    if (query.trim() !== '') {
        params.set('q', query.trim());
    }

    if (currentEstado !== '') {
        params.set('estado', currentEstado);
    }

    try {
        const response = await apiFetch(`/api/pacientes?${params.toString()}`);
        const pacientes = Array.isArray(response?.data) ? response.data : [];
        lastMeta = response?.meta || { page, per_page: 12, total: pacientes.length };

        renderRows(pacientes);

        if (count) {
            const total = Number(lastMeta.total || pacientes.length);
            count.textContent = `${total} paciente${total === 1 ? '' : 's'} atendido${total === 1 ? '' : 's'}`;
        }

        renderPagination(pagination, lastMeta, (nextPage) => {
            page = nextPage;
            load();
        });
    } catch (error) {
        const message = apiErrorMessage(error);
        if (table) {
            table.innerHTML = `
                <div class="error-state">
                    <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <h2>No pudimos cargar tus pacientes</h2>
                    <p>${escapeHtml(message)}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-pacientes-retry>
                        Reintentar
                        <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    </button>
                </div>
            `;
            table.querySelector('[data-pacientes-retry]')?.addEventListener('click', load);
        }
        toast('error', message);
    }
}

function reloadFromStart() {
    page = 1;
    load();
}

search?.addEventListener('input', debounce(() => {
    query = search.value;
    reloadFromStart();
}, 280));

estado?.addEventListener('change', () => {
    currentEstado = estado.value;
    reloadFromStart();
});

refresh?.addEventListener('click', load);

load();
