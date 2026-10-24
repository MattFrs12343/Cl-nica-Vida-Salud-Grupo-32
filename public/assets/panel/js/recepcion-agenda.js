import {
    $,
    addDays,
    apiFetch,
    apiErrorMessage,
    escapeHtml,
    formatDate,
    formatDateNumeric,
    formatTimeValue,
    initials,
    toast,
    todayISO,
} from './app.js';
import {
    ReconsultaController,
    changeAttendance,
    doctorName,
    emptyStateHtml,
    fillSelect,
    getMedicos,
    medicalOptionLabel,
    originLabel,
    patientName,
    renderLoadError,
    statusBadge,
} from './recepcion-shared.js';

export default function initAgenda() {
    const state = {
        doctors: [],
        medico: '',
        fecha: '',
        citas: [],
        contadores: {},
    };

    const doctorSelect = $('[data-agenda-medico]');
    const dateInput = $('[data-agenda-fecha]');
    const list = $('[data-agenda-list]');
    const count = $('[data-agenda-count]');
    const dateLabel = $('[data-agenda-date-label]');
    const status = $('[data-agenda-status]');

    const params = new URLSearchParams(window.location.search);
    state.fecha = params.get('fecha') || (dateInput?.value || todayISO());

    if (dateInput) {
        dateInput.value = state.fecha;
    }

    const reconsulta = new ReconsultaController({
        onSuccess: () => load(),
    });

    const load = async () => {
        if (!state.medico) {
            list.innerHTML = emptyStateHtml({
                icon: 'stethoscope',
                title: 'Selecciona un médico',
                message: 'Elige un médico para ver su cronograma del día.',
            });
            if (count) {
                count.textContent = 'Sin médico seleccionado';
            }
            if (status) {
                status.textContent = 'Pendiente';
            }
            return;
        }

        list.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 5 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

        try {
            const response = await apiFetch(
                `/api/medicos/${state.medico}/agenda?fecha=${encodeURIComponent(state.fecha)}`
            );

            const data = response?.data || {};
            state.citas = Array.isArray(data.citas) ? data.citas : [];
            state.contadores = data.contadores || {};

            renderKpis(data.contadores || {});
            renderAgenda(state.citas, data.medico);

            if (count) {
                count.textContent = `${state.citas.length} cita${state.citas.length === 1 ? '' : 's'} · ${formatDateNumeric(state.fecha)}`;
            }

            if (dateLabel) {
                dateLabel.textContent = formatDate(state.fecha, 'long');
            }

            if (status) {
                status.textContent = formatDateNumeric(state.fecha);
            }
        } catch (error) {
            renderLoadError(list, error, load);
            if (status) {
                status.textContent = 'Error';
            }
            toast('error', apiErrorMessage(error));
        }
    };

    const renderKpis = (contadores) => {
        Object.entries({
            total: contadores.total ?? 0,
            programadas: contadores.programadas ?? 0,
            confirmadas: contadores.confirmadas ?? 0,
            atendidas: contadores.atendidas ?? 0,
            inasistencias: contadores.inasistencias ?? 0,
        }).forEach(([key, value]) => {
            const node = document.querySelector(`[data-agenda-kpi="${key}"]`);
            if (node) {
                node.textContent = String(value);
            }
        });

        const doctor = state.doctors.find((item) => Number(item.id) === Number(state.medico));
        document.querySelectorAll('[data-agenda-doctor]').forEach((node) => {
            node.textContent = doctor ? doctorName(doctor) : '—';
        });
    };

    const renderAgenda = (citas, medico) => {
        if (citas.length === 0) {
            list.innerHTML = emptyStateHtml({
                icon: 'calendar',
                title: 'Sin citas este día',
                message: `No hay citas registradas para ${formatDate(state.fecha, 'long')}.`,
                action: `<a class="btn btn-primary btn-sm" href="/recepcion/citas?nuevo=1">Agendar cita</a>`,
            });
            return;
        }

        list.innerHTML = `<div class="agenda-list">${citas.map((cita) => {
            const canAttend = cita.estado === 'confirmada';
            const canMiss = ['programada', 'confirmada'].includes(cita.estado);
            const canReconsult = ['atendida', 'programada', 'confirmada'].includes(cita.estado);

            const actions = [
                canAttend ? rowAction('atender', cita.id, 'check', 'Marcar atendida', false) : '',
                canMiss ? rowAction('inasistencia', cita.id, 'x-circle', 'Registrar inasistencia', true) : '',
                canReconsult ? rowAction('reconsulta', cita.id, 'refresh', 'Crear reconsulta', false) : '',
                `<a class="row-action" title="Gestionar en citas" aria-label="Gestionar en citas"
                    href="/recepcion/citas?fecha=${encodeURIComponent(cita.fecha)}&q=${encodeURIComponent(cita.codigo || cita.paciente?.ci || '')}">
                    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
                 </a>`,
            ].join('');

            return `
                <div class="agenda-item">
                    <div class="agenda-time">
                        ${escapeHtml(formatTimeValue(cita.hora_inicio))}
                        <small>${escapeHtml(formatTimeValue(cita.hora_fin))}</small>
                    </div>
                    <div class="agenda-body ${cita.es_reconsulta ? 'is-reconsulta' : ''}">
                        <span class="avatar avatar-sm">${escapeHtml(initials(patientName(cita.paciente)))}</span>
                        <div class="agenda-main">
                            <div class="agenda-main-top">
                                <strong>${escapeHtml(patientName(cita.paciente))}</strong>
                                ${statusBadge(cita.estado)}
                                ${cita.es_reconsulta ? '<span class="badge-reconsulta">Reconsulta</span>' : ''}
                            </div>
                            <p>
                                ${escapeHtml(cita.especialidad?.nombre || 'Consulta')}
                                · <span class="mono">${escapeHtml(cita.codigo || '')}</span>
                                · ${escapeHtml(originLabel(cita.origen))}
                                ${cita.motivo ? ` · ${escapeHtml(cita.motivo)}` : ''}
                            </p>
                        </div>
                        <div class="agenda-actions">${actions}</div>
                    </div>
                </div>
            `;
        }).join('')}</div>`;
    };

    const rowAction = (action, id, icon, title, danger) => `
        <button class="row-action ${danger ? 'is-danger' : ''}" type="button" title="${escapeHtml(title)}"
                aria-label="${escapeHtml(title)}" data-agenda-action="${action}" data-cita-id="${escapeHtml(id)}">
            <svg class="icon" aria-hidden="true"><use href="#i-${icon}"></use></svg>
        </button>
    `;

    list?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-agenda-action]');
        if (!button) {
            return;
        }

        const cita = state.citas.find((item) => Number(item.id) === Number(button.dataset.citaId));
        if (!cita) {
            return;
        }

        const action = button.dataset.agendaAction;

        if (action === 'reconsulta') {
            reconsulta.open(cita);
            return;
        }

        const ok = await changeAttendance(cita, action);
        if (ok) {
            load();
        }
    });

    doctorSelect?.addEventListener('change', () => {
        state.medico = doctorSelect.value;
        const url = new URL(window.location.href);
        if (state.medico) {
            url.searchParams.set('medico', state.medico);
        } else {
            url.searchParams.delete('medico');
        }
        window.history.replaceState({}, '', url);
        load();
    });

    dateInput?.addEventListener('change', () => {
        state.fecha = dateInput.value || todayISO();
        const url = new URL(window.location.href);
        url.searchParams.set('fecha', state.fecha);
        window.history.replaceState({}, '', url);
        load();
    });

    $('[data-agenda-prev]')?.addEventListener('click', () => {
        state.fecha = addDays(state.fecha, -1);
        dateInput.value = state.fecha;
        load();
    });

    $('[data-agenda-next]')?.addEventListener('click', () => {
        state.fecha = addDays(state.fecha, 1);
        dateInput.value = state.fecha;
        load();
    });

    $('[data-agenda-today]')?.addEventListener('click', () => {
        state.fecha = todayISO();
        dateInput.value = state.fecha;
        load();
    });

    const bootstrap = async () => {
        try {
            state.doctors = await getMedicos();
            fillSelect(doctorSelect, state.doctors, {
                placeholder: 'Selecciona un médico',
                label: medicalOptionLabel,
                selected: params.get('medico') || state.doctors[0]?.id || '',
            });

            state.medico = doctorSelect?.value || '';
            load();
        } catch (error) {
            renderLoadError(list, error, bootstrap);
        }
    };

    bootstrap();
}
