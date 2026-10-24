import {
    $,
    apiFetch,
    apiErrorMessage,
    closeModal,
    debounce,
    escapeHtml,
    formatDateNumeric,
    formatDate,
    formatTimeValue,
    initials,
    openModal,
    setLoading,
    statusBadge as badge,
    toast,
    todayISO,
} from './app.js';
import {
    PatientPicker,
    ReconsultaController,
    changeAttendance,
    doctorName,
    emptyStateHtml,
    fillSelect,
    getEspecialidades,
    getMedicos,
    loadSlots,
    medicalOptionLabel,
    originLabel,
    patientName,
    renderLoadError,
    renderPagination,
    statusBadge,
} from './recepcion-shared.js';

export default function initCitas() {
    const state = {
        page: 1,
        perPage: 15,
        q: '',
        fecha: '',
        estado: '',
        medico: '',
        citas: [],
        meta: {},
        doctors: [],
        specialties: [],
        editingReprog: null,
        editingCancel: null,
    };

    const search = $('[data-cita-search]');
    const clear = $('[data-cita-clear]');
    const dateInput = $('[data-cita-fecha]');
    const estadoSelect = $('[data-cita-estado]');
    const medicoFilter = $('[data-cita-medico]');
    const table = $('[data-citas-table]');
    const pagination = $('[data-cita-pagination]');
    const count = $('[data-cita-count]');
    const dayLabel = $('[data-cita-day-label]');

    const newModal = $('#modal-cita');
    const newForm = $('[data-cita-form]');
    const newButton = $('[data-cita-new]');
    const specialtySelect = $('[data-cita-especialidad]');
    const doctorSelect = $('[data-cita-medico-select]');
    const newDate = $('[data-cita-fecha-form]');
    const newSlots = $('[data-cita-slots]');
    const newSlotHint = $('[data-cita-slot-hint]');
    const newHora = $('[data-cita-hora]');

    const reprogModal = $('#modal-reprogramar');
    const reprogForm = $('[data-reprog-form]');
    const reprogInfo = $('[data-reprog-info]');
    const reprogDate = $('[data-reprog-fecha]');
    const reprogSlots = $('[data-reprog-slots]');
    const reprogHint = $('[data-reprog-slot-hint]');
    const reprogHora = $('[data-reprog-hora]');

    const cancelModal = $('#modal-cancelar');
    const cancelForm = $('[data-cancel-form]');
    const cancelInfo = $('[data-cancel-info]');

    const picker = new PatientPicker({
        search: $('[data-cita-patient-search]'),
        results: $('[data-cita-patient-results]'),
        selected: $('[data-cita-patient-selected]'),
        hidden: $('[data-cita-patient-id]'),
        onSelect: () => {},
    });

    const reconsulta = new ReconsultaController({
        onSuccess: () => load(),
    });

    const params = new URLSearchParams(window.location.search);
    state.fecha = params.get('fecha') || (dateInput?.value || todayISO());
    state.q = params.get('q') || '';

    if (dateInput) {
        dateInput.value = state.fecha;
    }

    if (search && state.q) {
        search.value = state.q;
        clear.hidden = false;
    }

    const load = async () => {
        table.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 6 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

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
            state.citas = Array.isArray(response?.data) ? response.data : [];
            state.meta = response?.meta || {};

            renderRows();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.citas.length} cita${Number(state.meta.total ?? state.citas.length) === 1 ? '' : 's'}`;
            }

            if (dayLabel) {
                dayLabel.textContent = state.fecha ? formatDateNumeric(state.fecha) : 'Todas las fechas';
            }
        } catch (error) {
            renderLoadError(table, error, load);
        }
    };

    const renderRows = () => {
        if (state.citas.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'calendar',
                title: 'Sin citas para mostrar',
                message: state.q || state.estado || state.medico
                    ? 'No hay citas que coincidan con los filtros. Prueba con otro criterio.'
                    : 'No hay citas registradas para esta fecha. Agenda la primera.',
                action: `<button class="btn btn-primary btn-sm" type="button" data-cita-new-inline>Nueva cita</button>`,
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
                        const actions = [];

                        if (cita.estado === 'confirmada') {
                            actions.push(actionButton('atender', cita.id, 'check', 'Marcar atendida', false));
                        }

                        if (['programada', 'confirmada'].includes(cita.estado)) {
                            actions.push(actionButton('inasistencia', cita.id, 'x-circle', 'Registrar inasistencia', true));
                            actions.push(actionButton('reprogramar', cita.id, 'calendar', 'Reprogramar', false));
                            if (cita.fecha > todayISO()) {
                                actions.push(actionButton('cancelar', cita.id, 'close', 'Cancelar cita', true));
                            }
                            actions.push(actionButton('reconsulta', cita.id, 'refresh', 'Crear reconsulta', false));
                        } else if (cita.estado === 'atendida') {
                            actions.push(actionButton('reconsulta', cita.id, 'refresh', 'Reconsulta de seguimiento', false));
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
                                        <span class="avatar avatar-sm">${escapeHtml(initials(patientName(cita.paciente)))}</span>
                                        <div>
                                            <strong>${escapeHtml(patientName(cita.paciente))}</strong>
                                            <small>${escapeHtml(cita.paciente?.ci || '')}</small>
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
                                <td>
                                    ${statusBadge(cita.estado)}
                                    ${cita.es_reconsulta || cita.reconsulta_de_id ? '<span class="badge-reconsulta">Reconsulta</span>' : ''}
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

    const setupSpecialtyDoctors = (selectedSpecialty = '') => {
        const filtered = state.doctors.filter((doctor) => {
            if (!selectedSpecialty) {
                return false;
            }

            return (doctor.especialidades || []).some((item) => Number(item.id) === Number(selectedSpecialty));
        });

        if (!selectedSpecialty) {
            fillSelect(doctorSelect, [], { placeholder: 'Primero elige una especialidad', disabled: true });
            return;
        }

        fillSelect(doctorSelect, filtered, {
            placeholder: filtered.length === 0 ? 'Sin médicos disponibles' : 'Selecciona un médico',
            label: medicalOptionLabel,
            disabled: filtered.length === 0,
        });

        if (filtered.length === 1) {
            doctorSelect.value = String(filtered[0].id);
        }
    };

    const resetNewForm = () => {
        newForm.reset();
        newForm.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
        newForm.querySelectorAll('.field-error').forEach((node) => node.remove());
        picker.reset();
        fillSelect(doctorSelect, [], { placeholder: 'Primero elige una especialidad', disabled: true });
        if (newDate) {
            newDate.value = todayISO();
        }
        if (newHora) {
            newHora.value = '';
        }
        loadSlots({
            container: newSlots,
            hint: newSlotHint,
            medicoId: '',
            fecha: newDate?.value,
            horaInput: newHora,
        });
    };

    const openNew = () => {
        resetNewForm();
        openModal(newModal);
    };

    const refreshNewSlots = () => {
        loadSlots({
            container: newSlots,
            hint: newSlotHint,
            medicoId: doctorSelect?.value,
            fecha: newDate?.value,
            horaInput: newHora,
        });
    };

    const openReprog = (cita) => {
        state.editingReprog = cita;
        reprogForm.reset();
        reprogInfo.innerHTML = appointmentSummary(cita);
        reprogDate.value = cita.fecha >= todayISO() ? cita.fecha : todayISO();
        reprogHora.value = '';
        loadSlots({
            container: reprogSlots,
            hint: reprogHint,
            medicoId: cita.medico_id,
            fecha: reprogDate.value,
            horaInput: reprogHora,
        });
        openModal(reprogModal);
    };

    const openCancel = (cita) => {
        state.editingCancel = cita;
        cancelForm.reset();
        cancelInfo.innerHTML = appointmentSummary(cita);
        openModal(cancelModal);
    };

    const appointmentSummary = (cita) => `
        <div class="appointment-summary-top">
            <strong>${escapeHtml(patientName(cita.paciente))}</strong>
            ${statusBadge(cita.estado)}
        </div>
        <p>${escapeHtml(doctorName(cita.medico))} · ${escapeHtml(cita.especialidad?.nombre || 'Sin especialidad')}</p>
        <div class="appointment-summary-meta">
            <span>${escapeHtml(formatDateNumeric(cita.fecha))}</span>
            <span>${escapeHtml(formatTimeValue(cita.hora_inicio))}</span>
            <span>${escapeHtml(cita.codigo || '')}</span>
        </div>
    `;

    const submitNew = async (event) => {
        event.preventDefault();
        const submit = newForm.querySelector('button[type="submit"]');
        const formData = new FormData(newForm);
        const payload = Object.fromEntries(formData.entries());

        if (!payload.paciente_id) {
            toast('error', 'Selecciona un paciente antes de agendar.');
            $('[data-cita-patient-search]')?.focus();
            return;
        }

        if (!payload.hora) {
            toast('error', 'Selecciona un horario disponible.');
            newSlots?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        payload.paciente_id = Number(payload.paciente_id);
        payload.medico_id = Number(payload.medico_id);
        payload.especialidad_id = Number(payload.especialidad_id);
        payload.es_prioritaria = newForm.querySelector('[name="es_prioritaria"]')?.checked || false;
        payload.motivo = String(payload.motivo || '').trim() || null;

        setLoading(submit, true);

        try {
            const response = await apiFetch('/api/citas', { method: 'POST', body: payload });
            toast('success', response?.message || 'Cita registrada.');
            closeModal(newModal);
            state.page = 1;
            load();
        } catch (error) {
            if (error?.code === 'SLOT_TAKEN') {
                toast('error', 'Ese horario acaba de ocuparse. Elige otro.');
                refreshNewSlots();
            } else {
                toast('error', apiErrorMessage(error));
            }
        } finally {
            setLoading(submit, false);
        }
    };

    const submitReprog = async (event) => {
        event.preventDefault();

        if (!state.editingReprog) {
            return;
        }

        if (!reprogHora.value) {
            toast('error', 'Selecciona el nuevo horario.');
            return;
        }

        const submit = reprogForm.querySelector('button[type="submit"]');
        setLoading(submit, true);

        try {
            const response = await apiFetch(`/api/citas/${state.editingReprog.id}/reprogramar`, {
                method: 'PUT',
                body: {
                    fecha: reprogDate.value,
                    hora: reprogHora.value,
                    motivo: String(reprogForm.querySelector('[name="motivo"]')?.value || '').trim() || null,
                },
            });

            toast('success', response?.message || 'Cita reprogramada.');
            closeModal(reprogModal);
            load();
        } catch (error) {
            if (error?.code === 'SLOT_TAKEN') {
                toast('error', 'Ese horario acaba de ocuparse. Elige otro.');
                loadSlots({
                    container: reprogSlots,
                    hint: reprogHint,
                    medicoId: state.editingReprog.medico_id,
                    fecha: reprogDate.value,
                    horaInput: reprogHora,
                });
            } else {
                toast('error', apiErrorMessage(error));
            }
        } finally {
            setLoading(submit, false);
        }
    };

    const submitCancel = async (event) => {
        event.preventDefault();

        if (!state.editingCancel) {
            return;
        }

        const motivo = String(cancelForm.querySelector('[name="motivo"]')?.value || '').trim();

        if (!motivo) {
            toast('error', 'Escribe el motivo de la cancelación.');
            return;
        }

        const submit = cancelForm.querySelector('button[type="submit"]');
        setLoading(submit, true);

        try {
            const response = await apiFetch(`/api/citas/${state.editingCancel.id}/cancelar`, {
                method: 'PATCH',
                body: { motivo },
            });

            toast('success', response?.message || 'Cita cancelada.');
            closeModal(cancelModal);
            load();
        } catch (error) {
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submit, false);
        }
    };

    /* Eventos */
    newButton?.addEventListener('click', openNew);

    document.addEventListener('click', async (event) => {
        if (event.target.closest('[data-cita-new-inline]')) {
            openNew();
            return;
        }

        const button = event.target.closest('[data-cita-action]');
        if (!button) {
            return;
        }

        const cita = state.citas.find((item) => Number(item.id) === Number(button.dataset.citaId));
        if (!cita) {
            return;
        }

        const action = button.dataset.citaAction;

        if (action === 'atender' || action === 'inasistencia') {
            const ok = await changeAttendance(cita, action);
            if (ok) {
                load();
            }
            return;
        }

        if (action === 'reprogramar') {
            openReprog(cita);
            return;
        }

        if (action === 'cancelar') {
            openCancel(cita);
            return;
        }

        if (action === 'reconsulta') {
            reconsulta.open(cita);
        }
    });

    search?.addEventListener('input', debounce(() => {
        state.q = search.value.trim();
        state.page = 1;
        clear.hidden = state.q === '';
        load();
    }, 300));

    clear?.addEventListener('click', () => {
        search.value = '';
        state.q = '';
        state.page = 1;
        clear.hidden = true;
        search.focus();
        load();
    });

    dateInput?.addEventListener('change', () => {
        state.fecha = dateInput.value;
        state.page = 1;
        load();
    });

    estadoSelect?.addEventListener('change', () => {
        state.estado = estadoSelect.value;
        state.page = 1;
        load();
    });

    medicoFilter?.addEventListener('change', () => {
        state.medico = medicoFilter.value;
        state.page = 1;
        load();
    });

    $('[data-cita-refresh]')?.addEventListener('click', load);

    specialtySelect?.addEventListener('change', () => {
        setupSpecialtyDoctors(specialtySelect.value);
        refreshNewSlots();
    });

    doctorSelect?.addEventListener('change', refreshNewSlots);
    newDate?.addEventListener('change', refreshNewSlots);
    newForm?.addEventListener('submit', submitNew);
    reprogForm?.addEventListener('submit', submitReprog);
    cancelForm?.addEventListener('submit', submitCancel);

    reprogDate?.addEventListener('change', () => {
        if (!state.editingReprog) {
            return;
        }

        loadSlots({
            container: reprogSlots,
            hint: reprogHint,
            medicoId: state.editingReprog.medico_id,
            fecha: reprogDate.value,
            horaInput: reprogHora,
        });
    });

    const bootstrap = async () => {
        try {
            const [doctors, specialties] = await Promise.all([getMedicos(), getEspecialidades()]);
            state.doctors = doctors;
            state.specialties = specialties;

            fillSelect(medicoFilter, doctors, {
                placeholder: 'Todos los médicos',
                label: medicalOptionLabel,
            });

            fillSelect(specialtySelect, specialties, {
                placeholder: 'Selecciona una especialidad',
            });

            load();
        } catch (error) {
            renderLoadError(table, error, bootstrap);
        }

        if (params.get('nuevo') === '1') {
            openNew();

            const patientId = params.get('paciente_id');
            if (patientId) {
                await picker.selectById(patientId);
            }
        }
    };

    bootstrap();
}
