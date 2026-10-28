import {
    $,
    apiFetch,
    apiErrorMessage,
    doctorName,
    escapeHtml,
    formatTimeValue,
    patientName,
    statusBadge,
    toast,
    todayISO,
} from './app.js';

export default function initAdminDashboard() {
    if (!$('[data-admin-dashboard]')) {
        return;
    }

    loadDashboard();
}

async function loadDashboard() {
    const today = todayISO();

    try {
        const [dashboard, citas] = await Promise.all([
            apiFetch('/api/dashboard/admin'),
            apiFetch(`/api/citas?fecha=${today}&per_page=10&sort=hora&direction=asc`),
        ]);

        renderKpis(dashboard.data || {});
        renderOrigin(dashboard.data || {});
        renderAgenda(Array.isArray(citas.data) ? citas.data : []);
    } catch (error) {
        renderError(error);
    }
}

function renderKpis(data) {
    const values = {
        citas_hoy: data.citas_hoy ?? 0,
        programadas: data.programadas ?? 0,
        confirmadas: data.confirmadas ?? 0,
        atendidas: data.atendidas ?? 0,
        inasistencias: data.inasistencias ?? 0,
    };

    Object.entries(values).forEach(([key, value]) => {
        const node = document.querySelector(`[data-admin-kpi="${key}"]`);
        if (node) {
            node.textContent = String(value);
        }
    });

    const pacientes = document.querySelector('[data-admin-total="pacientes"]');
    const medicos = document.querySelector('[data-admin-total="medicos"]');

    if (pacientes) {
        pacientes.textContent = String(data.total_pacientes ?? '—');
    }

    if (medicos) {
        medicos.textContent = String(data.total_medicos ?? '—');
    }
}

function renderOrigin(data) {
    const counts = {
        web: Number(data.web ?? data.por_origen?.web ?? 0),
        kiosco: Number(data.kiosco ?? data.por_origen?.kiosco ?? 0),
        recepcion: Number(data.recepcion ?? data.por_origen?.recepcion ?? 0),
    };

    Object.entries(counts).forEach(([key, value]) => {
        const node = document.querySelector(`[data-origin="${key}"]`);
        if (node) {
            node.textContent = String(value);
        }
    });

    const total = counts.web + counts.kiosco + counts.recepcion;
    const donut = document.querySelector('[data-origin-donut]');

    if (!donut) {
        return;
    }

    if (total === 0) {
        donut.style.background = 'rgba(16, 34, 30, 0.08)';
        donut.innerHTML = '<span class="origin-donut-center">0<small>citas</small></span>';
        return;
    }

    let cursor = 0;
    const stops = [];

    [
        ['web', counts.web, 'var(--pine)'],
        ['kiosco', counts.kiosco, 'var(--accent)'],
        ['recepcion', counts.recepcion, 'var(--mint-strong)'],
    ].forEach(([, value, color]) => {
        if (!value) {
            return;
        }

        const end = cursor + (value / total) * 360;
        stops.push(`${color} ${cursor.toFixed(2)}deg ${end.toFixed(2)}deg`);
        cursor = end;
    });

    donut.style.background = `conic-gradient(${stops.join(', ')})`;
    donut.innerHTML = `<span class="origin-donut-center">${total}<small>citas</small></span>`;
}

function renderAgenda(citas) {
    const container = document.querySelector('[data-admin-agenda]');

    if (!container) {
        return;
    }

    if (citas.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true">
                    <svg class="icon"><use href="#i-calendar"></use></svg>
                </span>
                <h2>Sin citas para hoy</h2>
                <p>La agenda está libre. Las nuevas reservas aparecerán aquí automáticamente.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Médico</th>
                    <th>Especialidad</th>
                    <th>Origen</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                ${citas.map((cita) => `
                    <tr>
                        <td><span class="cell-time">${escapeHtml(formatTimeValue(cita.hora_inicio))}</span></td>
                        <td>
                            <div class="cell-person">
                                <div>
                                    <strong>${escapeHtml(patientName(cita.paciente))}</strong>
                                    <small>${escapeHtml(cita.paciente?.ci || cita.codigo || '')}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <strong>${escapeHtml(doctorName(cita.medico))}</strong>
                                <small>${escapeHtml(cita.medico?.codigo || '')}</small>
                            </div>
                        </td>
                        <td>${escapeHtml(cita.especialidad?.nombre || '—')}</td>
                        <td><span class="badge badge-muted">${escapeHtml(originLabel(cita.origen))}</span></td>
                        <td>${statusBadge(cita.estado)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function originLabel(origin) {
    return {
        web: 'Web',
        kiosco: 'Kiosco',
        recepcion: 'Recepción',
    }[origin] || origin || '—';
}

function renderError(error) {
    const message = apiErrorMessage(error);
    const agenda = document.querySelector('[data-admin-agenda]');

    if (agenda) {
        agenda.innerHTML = `
            <div class="error-state">
                <span class="error-icon" aria-hidden="true">
                    <svg class="icon"><use href="#i-alert"></use></svg>
                </span>
                <h2>No pudimos cargar los indicadores</h2>
                <p>${escapeHtml(message)}</p>
                <button class="btn btn-ghost btn-sm" type="button" data-admin-retry>
                    Reintentar
                    <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                </button>
            </div>
        `;
        agenda.querySelector('[data-admin-retry]')?.addEventListener('click', () => {
            window.location.reload();
        });
    }

    toast('error', message);
}
