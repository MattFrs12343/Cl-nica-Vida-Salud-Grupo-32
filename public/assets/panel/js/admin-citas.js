import {
    $,
    addDays,
    apiFetch,
    apiErrorMessage,
    clearFieldErrors,
    closeModal,
    debounce,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    initials,
    openModal,
    renderFieldErrors,
    setLoading,
    statusBadge,
    toast,
    todayISO,
} from './app.js';
import {
    confirmAction,
    emptyStateHtml,
    fillSelect,
    loadMedicos,
    medicoOptionLabel,
    metaOf,
    normalizeData,
    renderLoadError,
    renderPagination,
} from './admin-shared.js';
import { loadSlots } from './recepcion-shared.js';

export default function initAdminCitas() {
    const table = $('[data-citas-table]');
    const pagination = $('[data-cita-pagination]');
    const search = $('[data-cita-search]');
    const clear = $('[data-cita-clear]');
    const dateInput = $('[data-cita-fecha]');
    const estadoFilter = $('[data-cita-estado]');
    const medicoFilter = $('[data-cita-medico]');
    const count = $('[data-cita-count]');
    const dayLabel = $('[data-cita-day-label]');

    const detailModal = $('#modal-cita-detalle');
    const detailBody = $('[data-cita-detalle-body]');

    const reprogModal = $('#modal-reprogramar');
    const reprogForm = $('[data-reprogramar-form]');
    const reprogInfo = $('[data-reprogramar-info]');
    const reprogDate = $('[data-reprogramar-fecha]');
    const reprogSlots = $('[data-reprogramar-slots]');
    const reprogHint = $('[data-reprogramar-slot-hint]');
    const reprogHora = $('[data-reprogramar-hora]');
    const reprogSubmit = $('[data-reprogramar-submit]');

    const cancelModal = $('#modal-cancelar');
    const cancelForm = $('[data-cancelar-form]');
    const cancelInfo = $('[data-cancelar-info]');
    const cancelSubmit = $('[data-cancelar-submit]');

    if (!table) {
        return;
    }

    const state = {
        page: 1,
        perPage: 15,
        q: '',
        fecha: '',
        estado: '',
        medico: '',
        citas: [],
        meta: {},
        detail: null,
        reprogram: null,
        cancel: null,
    };

    const params = new URLSearchParams(window.location.search);
    state.fecha = params.get('fecha') || dateInput?.value || todayISO();
    state.q = params.get('q') || '';

    if (dateInput) {
        dateInput.value = state.fecha;
    }

    if (search && state.q) {
        search.value = state.q;
        if (clear) clear.hidden = false;
    }

    const load = async () => {
        table.innerHTML = `<div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(6)}</div>`;

        try {
            const query = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
                sort: 'hora',
                direction: 'asc',
            });

            if (state.fecha) query.set('fecha', state.fecha);
            if (state.q) query.set('q', state.q);
            if (state.estado) query.set('estado', state.estado);
            if (state.medico) query.set('medico', state.medico);

            const response = await apiFetch(`/api/citas?${query.toString()}`);
            state.citas = normalizeData(response);
            state.meta = metaOf(response);

            renderRows();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.citas.length} cita(s)`;
            }

            if (dayLabel) {
                dayLabel.textContent = state.fecha ? formatDateNumeric(state.fecha) : 'Todas las fechas';
            }

            loadResumen();
        } catch (error) {
            renderLoadError(table, error, load);
        }
    };

    const loadResumen = async () => {
        try {
            const query = state.fecha
                ? `?desde=${encodeURIComponent(state.fecha)}&hasta=${encodeURIComponent(state.fecha)}`
                : '';
            const response = await apiFetch(`/api/reportes/asistencia${query}`);
            const data = response?.data || {};

            setText('[data-cita-kpi="total"]', data.total ?? 0);
            setText('[data-cita-kpi="programadas"]', data.programadas ?? 0);
            setText('[data-cita-kpi="confirmadas"]', data.confirmadas ?? 0);
            setText('[data-cita-kpi="atendidas"]', data.atendidas ?? 0);
            setText('[data-cita-kpi="inasistencias"]', data.inasistencias ?? 0);
        } catch (error) {
            // El listado principal ya comunica los errores de carga.
        }
    };

    const renderRows = () => {
        if (state.citas.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'calendar',
                title: 'Sin citas para mostrar',
                message: state.q || state.estado || state.medico
                    ? 'No hay citas que coincidan con los filtros.'
                    : 'No hay citas registradas para esta fecha.',
            });
            return;
        }

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Especialidad</th>
                        <th>Origen</th>
                        <th>Estado</th>
                        <th><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${state.citas.map((cita) => {
                        const actions = [actionButton('detail', cita.id, 'eye', 'Ver detalle', false)];

                        if (cita.estado === 'confirmada') {
                            actions.push(actionButton('atender', cita.id, 'check', 'Marcar atendida', false));
                        }

                        if (['programada', 'confirmada'].includes(cita.estado)) {
                            actions.push(actionButton('inasistencia', cita.id, 'x-circle', 'Registrar inasistencia', true));
                            actions.push(actionButton('reprogramar', cita.id, 'calendar', 'Reprogramar', false));

                            if (cita.fecha > todayISO()) {
                                actions.push(actionButton('cancelar', cita.id, 'close', 'Cancelar cita', true));
                            }
                        }

                        return `
                            <tr>
                                <td>
                                    <div class="cell-stack">
                                        <span class="cell-time">${escapeHtml(formatTimeValue(cita.hora_inicio))}</span>
                                        <small>${escapeHtml(cita.codigo || '')}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar avatar-sm">${escapeHtml(initials(cita.paciente?.nombre_completo || 'P'))}</span>
                                        <div>
                                            <strong>${escapeHtml(cita.paciente?.nombre_completo || 'Paciente')}</strong>
                                            <small>${escapeHtml(cita.paciente?.ci || '')}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-stack">
                                        <strong>${escapeHtml(cita.medico?.nombre_completo || 'Médico')}</strong>
                                        <small>${escapeHtml(cita.medico?.codigo || '')}</small>
                                    </div>
                                </td>
                                <td>${escapeHtml(cita.especialidad?.nombre || '—')}</td>
                                <td><span class="badge badge-muted">${escapeHtml(originLabel(cita.origen))}</span></td>
                                <td>
                                    ${statusBadge(cita.estado)}
                                    ${cita.es_reconsulta ? '<span class="badge-reconsulta">Reconsulta</span>' : ''}
                                </td>
                                <td><div class="row-actions">${actions.join('')}</div></td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    };

    const actionButton = (action, id, icon, title, danger) => `
        <button class="row-action ${danger ? 'is-danger' : ''}" type="button" title="${escapeHtml(title)}"
                aria-label="${escapeHtml(title)}" data-cita-action="${action}" data-cita-id="${escapeHtml(id)}">
            <svg class="icon" aria-hidden="true"><use href="#i-${icon}"></use></svg>
        </button>
    `;

    const openDetail = async (cita) => {
        if (!detailModal || !detailBody) {
            return;
        }

        state.detail = cita;
        detailBody.innerHTML = '<div class="table-loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>';
        openModal(detailModal);

        try {
            const [detalle, historial] = await Promise.all([
                apiFetch(`/api/citas/${cita.id}`),
                apiFetch(`/api/citas/${cita.id}/historial-estados`),
            ]);

            state.detail = detalle?.data || cita;
            renderDetail(state.detail, normalizeData(historial));
        } catch (error) {
            detailBody.innerHTML = `<p class="field-error">${escapeHtml(apiErrorMessage(error))}</p>`;
        }
    };

    const renderDetail = (cita, historial) => {
        const puedeReprogramar = ['programada', 'confirmada'].includes(cita.estado);
        const puedeCancelar = puedeReprogramar && cita.fecha > todayISO();

        detailBody.innerHTML = `
            <div class="appointment-summary">
                <div class="appointment-summary-top">
                    <strong>${escapeHtml(cita.paciente?.nombre_completo || 'Paciente')}</strong>
                    ${statusBadge(cita.estado)}
                </div>
                <p>${escapeHtml(cita.medico?.nombre_completo || 'Médico')} · ${escapeHtml(cita.especialidad?.nombre || 'Sin especialidad')}</p>
                <div class="appointment-summary-meta">
                    <span>${escapeHtml(formatDateNumeric(cita.fecha))}</span>
                    <span>${escapeHtml(formatTimeValue(cita.hora_inicio))} – ${escapeHtml(formatTimeValue(cita.hora_fin))}</span>
                    <span>${escapeHtml(cita.codigo || '')}</span>
                    <span>${escapeHtml(originLabel(cita.origen))}</span>
                </div>
            </div>

            <div class="history-block">
                <h3>Información</h3>
                <div class="history-row"><span>Paciente</span><p>${escapeHtml(cita.paciente?.nombre_completo || '—')} · CI ${escapeHtml(cita.paciente?.ci || '—')}</p></div>
                <div class="history-row"><span>Teléfono</span><p>${escapeHtml(cita.paciente?.telefono || '—')}</p></div>
                <div class="history-row"><span>Motivo</span><p>${escapeHtml(cita.motivo || 'Sin motivo registrado')}</p></div>
                <div class="history-row"><span>Prioritaria</span><p>${cita.es_prioritaria ? 'Sí' : 'No'}</p></div>
            </div>

            <div class="history-block">
                <h3>Historial de estados</h3>
                ${historial.length > 0 ? `
                    <div class="history-timeline">
                        ${historial.map((item) => `
                            <div class="history-cita">
                                <div class="history-cita-date">
                                    <strong>${escapeHtml(item.estado_nuevo || '—')}</strong>
                                    <span>${escapeHtml(formatDateNumeric(item.fecha))}</span>
                                </div>
                                <div class="history-cita-body">
                                    <div class="history-cita-top">
                                        ${statusBadge(item.estado_nuevo)}
                                        <small>${escapeHtml(item.usuario?.username || 'Sistema')}</small>
                                    </div>
                                    <p>${escapeHtml(item.observacion || 'Sin observación')}</p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="field-hint">La cita aún no tiene cambios de estado registrados.</p>'}
            </div>

            ${(puedeReprogramar || puedeCancelar) ? `
                <div class="admin-detail-actions">
                    ${puedeReprogramar ? '<button class="btn btn-soft btn-sm" type="button" data-cita-action="reprogramar" data-cita-id="' + cita.id + '">Reprogramar</button>' : ''}
                    ${puedeCancelar ? '<button class="btn btn-danger btn-sm" type="button" data-cita-action="cancelar" data-cita-id="' + cita.id + '">Cancelar cita</button>' : ''}
                </div>
            ` : ''}
        `;
    };

    const openReprogramar = (cita) => {
        state.reprogram = cita;
        clearFieldErrors(reprogForm);
        reprogForm.reset();

        const fecha = cita.fecha >= todayISO() ? cita.fecha : addDays(todayISO(), 1);

        if (reprogDate) {
            reprogDate.value = fecha;
        }

        if (reprogHora) {
            reprogHora.value = '';
        }

        if (reprogInfo) {
            reprogInfo.innerHTML = `
                <div class="appointment-summary-top">
                    <strong>${escapeHtml(cita.paciente?.nombre_completo || 'Paciente')}</strong>
                    ${statusBadge(cita.estado)}
                </div>
                <p>${escapeHtml(cita.medico?.nombre_completo || 'Médico')} · ${escapeHtml(cita.especialidad?.nombre || 'Sin especialidad')}</p>
                <div class="appointment-summary-meta">
                    <span>${escapeHtml(formatDateNumeric(cita.fecha))}</span>
                    <span>${escapeHtml(formatTimeValue(cita.hora_inicio))}</span>
                    <span>${escapeHtml(cita.codigo || '')}</span>
                </div>
            `;
        }

        const motivo = reprogForm.querySelector('[name="motivo"]');
        if (motivo) {
            motivo.value = cita.motivo || '';
        }

        openModal(reprogModal);
        refreshReprogSlots();
    };

    const refreshReprogSlots = () => {
        loadSlots({
            container: reprogSlots,
            hint: reprogHint,
            medicoId: state.reprogram?.medico_id,
            fecha: reprogDate?.value,
            horaInput: reprogHora,
        });
    };

    const submitReprogramar = async (event) => {
        event.preventDefault();
        clearFieldErrors(reprogForm);

        if (!state.reprogram) {
            return;
        }

        const fecha = reprogDate?.value || '';
        const hora = reprogHora?.value || '';
        const motivo = reprogForm.querySelector('[name="motivo"]')?.value?.trim() || null;

        if (!fecha) {
            toast('error', 'Selecciona la nueva fecha.');
            reprogDate?.focus();
            return;
        }

        if (!hora) {
            toast('error', 'Selecciona un horario disponible.');
            return;
        }

        setLoading(reprogSubmit, true);

        try {
            const response = await apiFetch(`/api/citas/${state.reprogram.id}/reprogramar`, {
                method: 'PUT',
                body: { fecha, hora, motivo },
            });

            toast('success', response?.message || 'Cita reprogramada.');
            closeModal(reprogModal);
            closeModal(detailModal);
            await load();
        } catch (error) {
            renderFieldErrors(reprogForm, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(reprogSubmit, false);
        }
    };

    const openCancelar = (cita) => {
        state.cancel = cita;
        clearFieldErrors(cancelForm);
        cancelForm.reset();

        if (cancelInfo) {
            cancelInfo.innerHTML = `
                <div class="appointment-summary-top">
                    <strong>${escapeHtml(cita.paciente?.nombre_completo || 'Paciente')}</strong>
                    ${statusBadge(cita.estado)}
                </div>
                <p>${escapeHtml(cita.medico?.nombre_completo || 'Médico')} · ${escapeHtml(cita.especialidad?.nombre || 'Sin especialidad')}</p>
                <div class="appointment-summary-meta">
                    <span>${escapeHtml(formatDateNumeric(cita.fecha))}</span>
                    <span>${escapeHtml(formatTimeValue(cita.hora_inicio))}</span>
                    <span>${escapeHtml(cita.codigo || '')}</span>
                </div>
            `;
        }

        openModal(cancelModal);
    };

    const submitCancelar = async (event) => {
        event.preventDefault();
        clearFieldErrors(cancelForm);

        if (!state.cancel) {
            return;
        }

        const motivo = cancelForm.querySelector('[name="motivo"]')?.value?.trim() || '';

        if (!motivo) {
            toast('error', 'Indica el motivo de la cancelación.');
            return;
        }

        setLoading(cancelSubmit, true);

        try {
            const response = await apiFetch(`/api/citas/${state.cancel.id}/cancelar`, {
                method: 'PATCH',
                body: { motivo },
            });

            toast('success', response?.message || 'Cita cancelada.');
            closeModal(cancelModal);
            closeModal(detailModal);
            await load();
        } catch (error) {
            renderFieldErrors(cancelForm, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(cancelSubmit, false);
        }
    };

    const changeAttendance = async (cita, action) => {
        const atendiendo = action === 'atender';
        const confirmed = await confirmAction({
            title: atendiendo ? 'Marcar como atendida' : 'Registrar inasistencia',
            message: atendiendo
                ? `¿Confirmas que ${cita.paciente?.nombre_completo || 'el paciente'} fue atendido(a)?`
                : `¿Confirmas que ${cita.paciente?.nombre_completo || 'el paciente'} no se presentó?`,
            confirmLabel: atendiendo ? 'Sí, atendida' : 'Sí, inasistencia',
            danger: !atendiendo,
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiFetch(`/api/citas/${cita.id}/${atendiendo ? 'atender' : 'inasistencia'}`, {
                method: 'PATCH',
            });
            toast('success', response?.message || 'Cita actualizada.');
            closeModal(detailModal);
            await load();
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    };

    search?.addEventListener('input', debounce(() => {
        state.q = search.value.trim();
        state.page = 1;
        if (clear) clear.hidden = state.q === '';
        load();
    }, 280));

    clear?.addEventListener('click', () => {
        search.value = '';
        state.q = '';
        state.page = 1;
        clear.hidden = true;
        load();
    });

    dateInput?.addEventListener('change', () => {
        state.fecha = dateInput.value;
        state.page = 1;
        load();
    });

    estadoFilter?.addEventListener('change', () => {
        state.estado = estadoFilter.value;
        state.page = 1;
        load();
    });

    medicoFilter?.addEventListener('change', () => {
        state.medico = medicoFilter.value;
        state.page = 1;
        load();
    });

    $('[data-cita-refresh]')?.addEventListener('click', load);

    reprogDate?.addEventListener('change', refreshReprogSlots);
    reprogForm?.addEventListener('submit', submitReprogramar);
    cancelForm?.addEventListener('submit', submitCancelar);

    document.addEventListener('click', (event) => {
        const action = event.target.closest('[data-cita-action]');

        if (!action) {
            return;
        }

        const id = Number(action.dataset.citaId);
        const cita = [state.detail, ...state.citas]
            .filter(Boolean)
            .find((item) => Number(item.id) === id);

        if (!cita) {
            return;
        }

        if (action.dataset.citaAction === 'detail') {
            openDetail(cita);
        } else if (action.dataset.citaAction === 'reprogramar') {
            openReprogramar(cita);
        } else if (action.dataset.citaAction === 'cancelar') {
            openCancelar(cita);
        } else if (action.dataset.citaAction === 'atender' || action.dataset.citaAction === 'inasistencia') {
            changeAttendance(cita, action.dataset.citaAction);
        }
    });

    const initFilters = async () => {
        try {
            const medicos = await loadMedicos();
            fillSelect(medicoFilter, medicos, {
                placeholder: 'Todos los médicos',
                label: medicoOptionLabel,
            });
        } catch (error) {
            // El filtro de médicos es opcional.
        }
    };

    initFilters();
    load();
}

function originLabel(origin) {
    return {
        web: 'Web',
        kiosco: 'Kiosco',
        recepcion: 'Recepción',
    }[origin] || origin || '—';
}

function setText(selector, value) {
    const node = document.querySelector(selector);
    if (node) {
        node.textContent = String(value ?? '—');
    }
}
