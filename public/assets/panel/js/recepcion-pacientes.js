import {
    $,
    apiFetch,
    apiErrorMessage,
    debounce,
    escapeHtml,
    formatDate,
    formatTimeValue,
    humanDate,
    initials,
    openModal,
    closeModal,
    renderFieldErrors,
    setLoading,
    sexLabel,
    toast,
    confirmAction,
} from './app.js';
import {
    IS_ADMIN,
    emptyStateHtml,
    errorStateHtml,
    patientName,
    renderLoadError,
    renderPagination,
    statusBadge,
} from './recepcion-shared.js';

export default function initPacientes() {
    const state = {
        page: 1,
        perPage: 12,
        q: '',
        estado: '',
        rows: [],
        total: 0,
        editing: null,
        historyPatient: null,
    };

    const table = $('[data-patients-table]');
    const pagination = $('[data-patient-pagination]');
    const count = $('[data-patient-count]');
    const search = $('[data-patient-search]');
    const clear = $('[data-patient-clear]');
    const estado = $('[data-patient-estado]');
    const form = $('[data-patient-form]');
    const modal = $('#modal-paciente');
    const historyModal = $('#modal-historial');
    const historyContent = $('[data-history-content]');
    const historyTitle = $('[data-history-title]');
    const historyEdit = $('[data-history-edit]');
    const historySchedule = $('[data-history-schedule]');

    const load = async () => {
        table.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 6 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

        try {
            const params = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
            });

            if (state.q) {
                params.set('q', state.q);
            }

            if (state.estado) {
                params.set('estado', state.estado);
            }

            const response = await apiFetch(`/api/pacientes?${params.toString()}`);
            state.rows = Array.isArray(response?.data) ? response.data : [];
            state.total = Number(response?.meta?.total || state.rows.length);

            renderRows();
            renderPaginationState(response?.meta);

            if (count) {
                count.textContent = `${state.total} paciente${state.total === 1 ? '' : 's'}`;
            }
        } catch (error) {
            renderLoadError(table, error, load);
            if (count) {
                count.textContent = 'Error de carga';
            }
        }
    };

    const renderPaginationState = (meta) => {
        renderPagination(pagination, meta || {
            page: state.page,
            per_page: state.perPage,
            total: state.total,
        }, (page) => {
            state.page = page;
            load();
        });
    };

    function renderRows() {
        if (state.rows.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'users',
                title: state.q || state.estado ? 'Sin coincidencias' : 'Directorio vacío',
                message: state.q || state.estado
                    ? 'Prueba con otro nombre, cédula o cambia el filtro de estado.'
                    : 'Registra al primer paciente para comenzar a agendar citas.',
                action: `<button class="btn btn-primary btn-sm" type="button" data-patient-new>Nuevo paciente</button>`,
            });
            return;
        }

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Cédula</th>
                        <th>Edad / Sexo</th>
                        <th>Contacto</th>
                        <th>Última cita</th>
                        <th>Estado</th>
                        <th><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${state.rows.map((patient) => {
                        const age = patient.edad ? `${patient.edad} años` : '—';
                        const toggle = IS_ADMIN
                            ? `
                                <button class="row-action ${patient.estado === 'activo' ? 'is-danger' : ''}" type="button"
                                        title="${patient.estado === 'activo' ? 'Desactivar' : 'Activar'}"
                                        aria-label="${patient.estado === 'activo' ? 'Desactivar paciente' : 'Activar paciente'}"
                                        data-patient-action="toggle" data-patient-id="${escapeHtml(patient.id)}">
                                    <svg class="icon" aria-hidden="true"><use href="#i-power"></use></svg>
                                </button>
                            `
                            : '';

                        return `
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar avatar-sm">${escapeHtml(initials(patientName(patient)))}</span>
                                        <div>
                                            <strong>${escapeHtml(patientName(patient))}</strong>
                                            <small>${escapeHtml(patient.codigo || '')}</small>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="cell-code">${escapeHtml(patient.ci || '—')}</span></td>
                                <td>
                                    <div class="cell-stack">
                                        <strong>${escapeHtml(age)}</strong>
                                        <small>${escapeHtml(sexLabel(patient.sexo))}</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-stack">
                                        <strong>${escapeHtml(patient.telefono || '—')}</strong>
                                        <small>${escapeHtml(patient.email || '')}</small>
                                    </div>
                                </td>
                                <td>${patient.ultima_cita ? escapeHtml(humanDate(patient.ultima_cita)) : '<span class="muted">Sin citas</span>'}</td>
                                <td>${statusBadge(patient.estado)}</td>
                                <td>
                                    <div class="row-actions">
                                        <button class="row-action" type="button" title="Ver historial"
                                                aria-label="Ver historial" data-patient-action="history" data-patient-id="${escapeHtml(patient.id)}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                                        </button>
                                        <a class="row-action" title="Agendar cita" aria-label="Agendar cita"
                                           href="/recepcion/citas?nuevo=1&paciente_id=${escapeHtml(patient.id)}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-calendar-plus"></use></svg>
                                        </a>
                                        <button class="row-action" type="button" title="Editar"
                                                aria-label="Editar paciente" data-patient-action="edit" data-patient-id="${escapeHtml(patient.id)}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                        </button>
                                        ${toggle}
                                    </div>
                                </td>
                            </tr>
                        `;
                    }).join('')}
                </tbody>
            </table>
        `;
    }

    const openCreate = () => {
        state.editing = null;
        form.reset();
        form.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
        form.querySelectorAll('.field-error').forEach((node) => node.remove());

        const kicker = modal.querySelector('[data-patient-modal-kicker]');
        const title = modal.querySelector('[data-patient-modal-title]');
        if (kicker) kicker.textContent = 'Nuevo registro';
        if (title) title.textContent = 'Registrar paciente';

        openModal(modal);
    };

    const openEdit = (patient) => {
        state.editing = patient;
        form.reset();

        Object.entries(patient).forEach(([key, value]) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field && value !== null && value !== undefined) {
                field.value = String(value);
            }
        });

        const kicker = modal.querySelector('[data-patient-modal-kicker]');
        const title = modal.querySelector('[data-patient-modal-title]');
        if (kicker) kicker.textContent = 'Edición';
        if (title) title.textContent = 'Editar paciente';

        openModal(modal);
    };

    const openHistory = async (patient) => {
        state.historyPatient = patient;
        historyTitle.textContent = patientName(patient);
        historyContent.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-block"></div>
                <div class="skeleton skeleton-row"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        `;
        historyEdit.hidden = true;
        historySchedule.hidden = true;
        openModal(historyModal);

        try {
            const [detailResponse, historyResponse] = await Promise.all([
                apiFetch(`/api/pacientes/${patient.id}`),
                apiFetch(`/api/pacientes/${patient.id}/citas`),
            ]);

            const detail = detailResponse?.data?.paciente || detailResponse?.data || patient;
            const history = historyResponse?.data || {};
            const citas = Array.isArray(history.citas) ? history.citas : [];

            renderHistory(detail, citas);
            historyEdit.hidden = false;
            historySchedule.hidden = false;
        } catch (error) {
            historyContent.innerHTML = errorStateHtml({ message: apiErrorMessage(error) });
        }
    };

    const renderHistory = (patient, citas) => {
        historyTitle.textContent = patientName(patient);

        historyEdit.onclick = () => {
            closeModal(historyModal);
            openEdit(patient);
        };

        historySchedule.href = `/recepcion/citas?nuevo=1&paciente_id=${encodeURIComponent(patient.id)}`;

        const clinical = [
            ['Alergias', patient.alergias],
            ['Enfermedades crónicas', patient.enfermedades_cronicas],
            ['Medicamentos actuales', patient.medicamentos],
            ['Antecedentes', patient.antecedentes],
        ];

        historyContent.innerHTML = `
            <div class="history-profile">
                <span class="avatar avatar-lg">${escapeHtml(initials(patientName(patient)))}</span>
                <div class="history-identity">
                    <h3>${escapeHtml(patientName(patient))}</h3>
                    <p>CI ${escapeHtml(patient.ci || '—')} · ${escapeHtml(patient.codigo || '')}</p>
                    <div class="history-badges">
                        ${statusBadge(patient.estado)}
                        <span class="badge badge-muted">${escapeHtml(sexLabel(patient.sexo))}</span>
                        ${patient.edad ? `<span class="badge badge-muted">${escapeHtml(patient.edad)} años</span>` : ''}
                    </div>
                </div>
                <div class="history-count">
                    <strong>${citas.length}</strong>
                    <span>cita${citas.length === 1 ? '' : 's'}</span>
                </div>
            </div>

            <div class="ficha-summary">
                <div class="ficha-summary-item">
                    <span>Teléfono</span>
                    <strong>${escapeHtml(patient.telefono || '—')}</strong>
                </div>
                <div class="ficha-summary-item">
                    <span>Correo</span>
                    <strong>${escapeHtml(patient.email || '—')}</strong>
                </div>
                <div class="ficha-summary-item">
                    <span>Tipo de sangre</span>
                    <strong>${escapeHtml(patient.tipo_sangre || 'Sin dato')}</strong>
                </div>
                <div class="ficha-summary-item">
                    <span>Última cita</span>
                    <strong>${escapeHtml(patient.ultima_cita ? humanDate(patient.ultima_cita) : 'Sin citas')}</strong>
                </div>
            </div>

            <div class="history-grid">
                <section class="history-block">
                    <h3>Información clínica</h3>
                    ${clinical.map(([label, value]) => `
                        <div class="history-row">
                            <span>${escapeHtml(label)}</span>
                            <p>${escapeHtml(value || 'Sin dato registrado.')}</p>
                        </div>
                    `).join('')}
                </section>

                <section class="history-block">
                    <h3>Contacto de emergencia</h3>
                    <div class="history-row">
                        <span>Nombre</span>
                        <p>${escapeHtml(patient.emergencia_nombre || 'Sin dato registrado.')}</p>
                    </div>
                    <div class="history-row">
                        <span>Parentesco</span>
                        <p>${escapeHtml(patient.emergencia_parentesco || 'Sin dato registrado.')}</p>
                    </div>
                    <div class="history-row">
                        <span>Teléfono</span>
                        <p>${escapeHtml(patient.emergencia_telefono || 'Sin dato registrado.')}</p>
                    </div>
                    <div class="history-row">
                        <span>Dirección</span>
                        <p>${escapeHtml(patient.direccion || 'Sin dato registrado.')}</p>
                    </div>
                </section>
            </div>

            <section class="history-block">
                <h3>Historial de citas</h3>
                ${renderHistoryCitas(citas)}
            </section>
        `;
    };

    const renderHistoryCitas = (citas) => {
        if (citas.length === 0) {
            return `
                <div class="empty-state" style="padding: 22px 14px;">
                    <span class="empty-icon" aria-hidden="true">
                        <svg class="icon"><use href="#i-calendar"></use></svg>
                    </span>
                    <h2>Sin citas registradas</h2>
                    <p>Este paciente todavía no tiene atenciones en la clínica.</p>
                </div>
            `;
        }

        return `
            <div class="history-timeline">
                ${citas.map((cita) => {
                    const parts = formatDate(cita.fecha, 'short').split(' ');

                    return `
                    <article class="history-cita">
                        <time class="history-cita-date">
                            <strong>${escapeHtml(parts[0] || '—')}</strong>
                            <span>${escapeHtml(parts[1] || '')}</span>
                        </time>
                        <div class="history-cita-body">
                            <div class="history-cita-top">
                                <span class="cell-time">${escapeHtml(formatTimeValue(cita.hora_inicio))}</span>
                                ${statusBadge(cita.estado)}
                                ${cita.origen === 'kiosco' ? '<span class="badge badge-muted">Kiosco</span>' : ''}
                            </div>
                            <strong>${escapeHtml(cita.medico?.nombre_completo || cita.medico_nombre || 'Médico')}</strong>
                            <p>${escapeHtml(cita.especialidad?.nombre || cita.especialidad_nombre || 'Consulta')}</p>
                            ${cita.motivo ? `<small>${escapeHtml(cita.motivo)}</small>` : ''}
                        </div>
                    </article>
                `;
                }).join('')}
            </div>
        `;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('button[type="submit"]');
        const payload = Object.fromEntries(new FormData(form).entries());

        setLoading(submit, true);

        try {
            const response = await apiFetch(
                state.editing ? `/api/pacientes/${state.editing.id}` : '/api/pacientes',
                {
                    method: state.editing ? 'PUT' : 'POST',
                    body: payload,
                }
            );

            toast('success', response?.message || (state.editing ? 'Paciente actualizado.' : 'Paciente registrado.'));
            closeModal(modal);
            state.page = 1;
            load();
        } catch (error) {
            if (error?.fields) {
                renderFieldErrors(form, error.fields);
            }
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submit, false);
        }
    });

    const togglePatient = async (patient) => {
        const next = patient.estado === 'activo' ? 'inactivo' : 'activo';
        const confirmed = await confirmAction({
            title: next === 'inactivo' ? 'Desactivar paciente' : 'Activar paciente',
            message: next === 'inactivo'
                ? 'No podrás agendar citas nuevas mientras esté inactivo. Su historial se conserva.'
                : 'El paciente volverá a estar disponible para agendar citas.',
            confirmLabel: next === 'inactivo' ? 'Desactivar' : 'Activar',
            danger: next === 'inactivo',
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiFetch(`/api/pacientes/${patient.id}/estado`, {
                method: 'PATCH',
                body: { estado: next },
            });
            toast('success', response?.message || 'Estado actualizado.');
            load();
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    };

    document.addEventListener('click', async (event) => {
        const newButton = event.target.closest('[data-patient-new]');
        if (newButton) {
            openCreate();
            return;
        }

        const action = event.target.closest('[data-patient-action]');
        if (!action) {
            return;
        }

        const patient = state.rows.find((item) => Number(item.id) === Number(action.dataset.patientId));

        if (!patient) {
            return;
        }

        if (action.dataset.patientAction === 'history') {
            openHistory(patient);
        } else if (action.dataset.patientAction === 'edit') {
            openEdit(patient);
        } else if (action.dataset.patientAction === 'toggle') {
            togglePatient(patient);
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

    estado?.addEventListener('change', () => {
        state.estado = estado.value;
        state.page = 1;
        load();
    });

    $('[data-patient-refresh]')?.addEventListener('click', load);

    const params = new URLSearchParams(window.location.search);
    if (params.get('q')) {
        state.q = params.get('q');
        if (search) {
            search.value = state.q;
            clear.hidden = false;
        }
    }

    load();

    if (params.get('nuevo') === '1') {
        openCreate();
    }
}
