import {
    $,
    apiErrorMessage,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    humanDate,
    initials,
    patientName,
    toast,
} from './app.js';
import {
    PANEL,
    ROLE,
    changeAttendance,
    doctorName,
    emptyStateHtml,
    errorStateHtml,
    getMedicos,
    originLabel,
    patientName as sharedPatientName,
    renderLoadError,
    statusBadge,
    summarizeCitas,
    todayISO,
} from './recepcion-shared.js';

export default function initDashboard() {
    if (document.querySelector('[data-doctor-kpis]')) {
        initDoctorDashboard();
        return;
    }

    if (document.querySelector('[data-today-kpis]')) {
        initReceptionDashboard();
    }
}

/* Recepción --------------------------------------------------------------- */
function initReceptionDashboard() {
    const state = { citas: [], patients: [] };
    const agenda = $('[data-today-agenda]');
    const recent = $('[data-recent-patients]');
    const summary = $('[data-today-summary]');

    const load = async () => {
        const [citasResult, patientsResult] = await Promise.allSettled([
            apiFetchCitas(),
            apiFetchPatients(),
        ]);

        if (citasResult.status === 'fulfilled') {
            state.citas = citasResult.value;
            renderReceptionKpis(state.citas);
            renderReceptionAgenda(state.citas);
            renderSummary(state.citas, summary);
        } else {
            renderLoadError(agenda, citasResult.reason, load);
            if (summary) {
                summary.textContent = 'No pudimos cargar la agenda de hoy.';
            }
        }

        if (patientsResult.status === 'fulfilled') {
            state.patients = patientsResult.value;
            renderRecentPatients(state.patients);
        } else if (recent) {
            renderLoadError(recent, patientsResult.reason, load);
        }
    };

    agenda?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-cita-action]');
        if (!button) {
            return;
        }

        const cita = state.citas.find((item) => Number(item.id) === Number(button.dataset.citaId));
        if (!cita) {
            return;
        }

        const ok = await changeAttendance(cita, button.dataset.citaAction);
        if (ok) {
            load();
        }
    });

    load();
}

async function apiFetchCitas() {
    const { apiFetch } = await import('./app.js');
    const response = await apiFetch(
        `/api/citas?fecha=${todayISO()}&per_page=60&sort=hora&direction=asc`
    );

    return Array.isArray(response?.data) ? response.data : [];
}

async function apiFetchPatients() {
    const { apiFetch } = await import('./app.js');
    const response = await apiFetch('/api/pacientes?per_page=5&page=1');
    return Array.isArray(response?.data) ? response.data : [];
}

function renderReceptionKpis(citas) {
    const counts = summarizeCitas(citas);
    const values = {
        total: counts.total,
        programadas: counts.programadas,
        confirmadas: counts.confirmadas,
        atendidas: counts.atendidas,
        inasistencias: counts.inasistencias,
    };

    Object.entries(values).forEach(([key, value]) => {
        const node = document.querySelector(`[data-today-kpi="${key}"]`);
        if (node) {
            node.textContent = String(value);
        }
    });
}

function renderSummary(citas, node) {
    if (!node) {
        return;
    }

    if (citas.length === 0) {
        node.textContent = 'No hay citas registradas para hoy. Agenda la primera cuando llegue un paciente.';
        return;
    }

    const counts = summarizeCitas(citas);
    const parts = [];

    if (counts.confirmadas > 0) {
        parts.push(`${counts.confirmadas} en espera`);
    }
    if (counts.atendidas > 0) {
        parts.push(`${counts.atendidas} atendida${counts.atendidas === 1 ? '' : 's'}`);
    }
    if (counts.inasistencias > 0) {
        parts.push(`${counts.inasistencias} inasistencia${counts.inasistencias === 1 ? '' : 's'}`);
    }

    const detail = parts.length > 0 ? ` · ${parts.join(', ')}` : '';
    node.textContent = `Tienes ${counts.total} cita${counts.total === 1 ? '' : 's'} hoy${detail}.`;
}

function renderReceptionAgenda(citas) {
    const container = $('[data-today-agenda]');

    if (!container) {
        return;
    }

    if (citas.length === 0) {
        container.innerHTML = emptyStateHtml({
            icon: 'calendar',
            title: 'Agenda libre por hoy',
            message: 'No hay citas registradas. Puedes agendar una nueva en cualquier momento.',
            action: `<a class="btn btn-primary btn-sm" href="/recepcion/citas?nuevo=1">Nueva cita</a>`,
        });
        return;
    }

    const rows = citas.slice(0, 8).map((cita) => {
        const actions = [];

        if (cita.estado === 'confirmada') {
            actions.push(`
                <button class="row-action" type="button" title="Marcar atendida"
                        aria-label="Marcar atendida" data-cita-action="atender" data-cita-id="${escapeHtml(cita.id)}">
                    <svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg>
                </button>
            `);
        }

        if (['programada', 'confirmada'].includes(cita.estado)) {
            actions.push(`
                <button class="row-action is-danger" type="button" title="Registrar inasistencia"
                        aria-label="Registrar inasistencia" data-cita-action="inasistencia" data-cita-id="${escapeHtml(cita.id)}">
                    <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                </button>
            `);
        }

        return `
            <tr>
                <td><span class="cell-time">${escapeHtml(formatTimeValue(cita.hora_inicio))}</span></td>
                <td>
                    <div class="cell-person">
                        <span class="avatar avatar-sm">${escapeHtml(initials(patientName(cita.paciente)))}</span>
                        <div>
                            <strong>${escapeHtml(patientName(cita.paciente))}</strong>
                            <small>${escapeHtml(cita.paciente?.ci || cita.codigo || '')}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="cell-stack">
                        <strong>${escapeHtml(doctorName(cita.medico))}</strong>
                        <small>${escapeHtml(cita.especialidad?.nombre || '')}</small>
                    </div>
                </td>
                <td>${statusBadge(cita.estado)}</td>
                <td>
                    <div class="row-actions">${actions.join('')}</div>
                </td>
            </tr>
        `;
    }).join('');

    container.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Estado</th>
                    <th><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

function renderRecentPatients(patients) {
    const container = $('[data-recent-patients]');

    if (!container) {
        return;
    }

    if (patients.length === 0) {
        container.innerHTML = `
            <div class="empty-state" style="padding: 22px 14px;">
                <span class="empty-icon" aria-hidden="true">
                    <svg class="icon"><use href="#i-users"></use></svg>
                </span>
                <h2>Sin pacientes aún</h2>
                <p>Registra el primero para empezar.</p>
                <a class="btn btn-primary btn-sm" href="/recepcion/pacientes?nuevo=1">Nuevo paciente</a>
            </div>
        `;
        return;
    }

    container.innerHTML = patients.map((patient) => `
        <a class="recent-patient" href="/recepcion/pacientes?q=${encodeURIComponent(patient.ci || patientName(patient))}">
            <span class="avatar avatar-sm">${escapeHtml(initials(patientName(patient)))}</span>
            <span class="recent-patient-copy">
                <strong>${escapeHtml(patientName(patient))}</strong>
                <small>CI ${escapeHtml(patient.ci || '—')}${patient.ultima_cita ? ` · ${escapeHtml(humanDate(patient.ultima_cita))}` : ''}</small>
            </span>
            <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
        </a>
    `).join('');
}

/* Médico ------------------------------------------------------------------ */
function initDoctorDashboard() {
    const root = $('[data-doctor-kpis]');
    const agenda = $('[data-doctor-agenda]');
    const medicoId = Number(PANEL.medicoId || 0);

    const load = async () => {
        if (!agenda) {
            return;
        }

        if (!medicoId) {
            agenda.innerHTML = errorStateHtml({ message: 'Tu usuario no tiene un médico asociado.' });
            return;
        }

        try {
            const { apiFetch } = await import('./app.js');
            const response = await apiFetch(`/api/medicos/${medicoId}/agenda?fecha=${todayISO()}`);
            const data = response?.data || {};
            const citas = Array.isArray(data.citas) ? data.citas : [];
            const contadores = data.contadores || summarizeCitas(citas);

            renderDoctorKpis(contadores, data.medico);
            renderDoctorAgenda(citas);
        } catch (error) {
            renderLoadError(agenda, error, load);
            toast('error', apiErrorMessage(error));
        }
    };

    agenda?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-doctor-action]');
        if (!button) {
            return;
        }

        const response = await fetchCurrentAgenda(medicoId);
        const cita = response.find((item) => Number(item.id) === Number(button.dataset.citaId));
        if (!cita) {
            return;
        }

        const ok = await changeAttendance(cita, button.dataset.doctorAction);
        if (ok) {
            load();
        }
    });

    load();
}

async function fetchCurrentAgenda(medicoId) {
    try {
        const { apiFetch } = await import('./app.js');
        const response = await apiFetch(`/api/medicos/${medicoId}/agenda?fecha=${todayISO()}`);
        return Array.isArray(response?.data?.citas) ? response.data.citas : [];
    } catch (error) {
        return [];
    }
}

function renderDoctorKpis(contadores, medico) {
    Object.entries({
        total: contadores.total ?? 0,
        confirmadas: contadores.confirmadas ?? 0,
        atendidas: contadores.atendidas ?? 0,
        inasistencias: contadores.inasistencias ?? 0,
    }).forEach(([key, value]) => {
        const node = document.querySelector(`[data-doctor-kpi="${key}"]`);
        if (node) {
            node.textContent = String(value);
        }
    });

    if (medico) {
        document.querySelectorAll('[data-doctor-name]').forEach((node) => {
            node.textContent = doctorName(medico);
        });
    }
}

function renderDoctorAgenda(citas) {
    const container = $('[data-doctor-agenda]');

    if (!container) {
        return;
    }

    if (citas.length === 0) {
        container.innerHTML = emptyStateHtml({
            icon: 'calendar',
            title: 'Sin pacientes por hoy',
            message: 'No tienes citas registradas para la fecha de hoy.',
        });
        return;
    }

    const items = citas.slice(0, 10).map((cita) => {
        const canAttend = cita.estado === 'confirmada';
        const canMiss = ['programada', 'confirmada'].includes(cita.estado);
        const actions = [
            canAttend ? `
                <button class="row-action" type="button" data-doctor-action="atender" data-cita-id="${escapeHtml(cita.id)}"
                        title="Marcar atendida" aria-label="Marcar atendida">
                    <svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg>
                </button>
            ` : '',
            canMiss ? `
                <button class="row-action is-danger" type="button" data-doctor-action="inasistencia" data-cita-id="${escapeHtml(cita.id)}"
                        title="Registrar inasistencia" aria-label="Registrar inasistencia">
                    <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                </button>
            ` : '',
        ].join('');

        return `
            <div class="agenda-item">
                <div class="agenda-time">
                    ${escapeHtml(formatTimeValue(cita.hora_inicio))}
                    <small>${escapeHtml(formatTimeValue(cita.hora_fin))}</small>
                </div>
                <div class="agenda-body">
                    <span class="avatar avatar-sm">${escapeHtml(initials(patientName(cita.paciente)))}</span>
                    <div class="agenda-main">
                        <div class="agenda-main-top">
                            <strong>${escapeHtml(patientName(cita.paciente))}</strong>
                            ${statusBadge(cita.estado)}
                        </div>
                        <p>
                            ${escapeHtml(cita.especialidad?.nombre || 'Consulta')}
                            · <span class="mono">${escapeHtml(cita.codigo || '')}</span>
                            ${cita.origen ? ` · ${escapeHtml(originLabel(cita.origen))}` : ''}
                        </p>
                    </div>
                    <div class="agenda-actions">${actions}</div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = `<div class="agenda-list">${items}</div>`;
}
