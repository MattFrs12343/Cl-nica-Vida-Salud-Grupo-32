import {
    $,
    apiFetch,
    apiErrorMessage,
    clearFieldErrors,
    closeModal,
    escapeHtml,
    openModal,
    renderFieldErrors,
    setLoading,
    toast,
    todayISO,
} from './app.js';
import {
    DIAS,
    confirmAction,
    emptyStateHtml,
    fillSelect,
    formatDateNumeric,
    loadMedicos,
    medicoNombre,
    medicoOptionLabel,
    normalizeData,
    renderLoadError,
} from './admin-shared.js';

export default function initAdminHorarios() {
    const medicoSelect = $('[data-horario-medico]');
    const franjasTable = $('[data-franjas-table]');
    const bloqueosTable = $('[data-bloqueos-table]');
    const doctorLabel = $('[data-horario-doctor]');
    const franjaModal = $('#modal-franja');
    const franjaForm = $('[data-franja-form]');
    const franjaTitle = $('[data-franja-modal-title]');
    const franjaSubmit = $('[data-franja-submit]');
    const bloqueoModal = $('#modal-bloqueo');
    const bloqueoForm = $('[data-bloqueo-form]');
    const bloqueoSubmit = $('[data-bloqueo-submit]');

    if (!franjasTable || !bloqueosTable) {
        return;
    }

    const state = {
        medicoId: 0,
        medicos: [],
        franjas: [],
        bloqueos: [],
        editingFranja: null,
    };

    const loadFranjas = async () => {
        if (!state.medicoId) {
            franjasTable.innerHTML = emptyStateHtml({
                icon: 'stethoscope',
                title: 'Selecciona un médico',
                message: 'Elige un médico para revisar sus franjas semanales.',
            });
            return;
        }

        franjasTable.innerHTML = `<div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(4)}</div>`;

        try {
            const response = await apiFetch(`/api/medicos/${state.medicoId}/horarios`);
            state.franjas = normalizeData(response);
            renderFranjas();
        } catch (error) {
            renderLoadError(franjasTable, error, loadFranjas);
        }
    };

    const loadBloqueos = async () => {
        if (!state.medicoId) {
            bloqueosTable.innerHTML = emptyStateHtml({
                icon: 'calendar',
                title: 'Selecciona un médico',
                message: 'Elige un médico para revisar sus bloqueos de agenda.',
            });
            return;
        }

        bloqueosTable.innerHTML = `<div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(3)}</div>`;

        try {
            const response = await apiFetch(`/api/medicos/${state.medicoId}/bloqueos`);
            state.bloqueos = normalizeData(response);
            renderBloqueos();
        } catch (error) {
            renderLoadError(bloqueosTable, error, loadBloqueos);
        }
    };

    const renderFranjas = () => {
        if (state.franjas.length === 0) {
            franjasTable.innerHTML = emptyStateHtml({
                icon: 'calendar',
                title: 'Sin franjas semanales',
                message: 'Agrega la primera franja para que los pacientes puedan reservar.',
                action: '<button class="btn btn-primary btn-sm" type="button" data-franja-new-inline>Nueva franja</button>',
            });
            return;
        }

        franjasTable.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Horario</th>
                        <th>Cupos web</th>
                        <th>Cupos kiosco</th>
                        <th>Estado</th>
                        <th><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${state.franjas.map((franja) => `
                        <tr>
                            <td><strong>${escapeHtml(DIAS[franja.dia_semana] || '—')}</strong></td>
                            <td>
                                <div class="cell-stack">
                                    <span class="cell-time">${escapeHtml(franja.hora_inicio)} – ${escapeHtml(franja.hora_fin)}</span>
                                    <small>${escapeHtml(estadoFranja(franja.activo))}</small>
                                </div>
                            </td>
                            <td><span class="badge badge-info">${escapeHtml(franja.cupos_web)} cupos</span></td>
                            <td><span class="badge badge-accent">${escapeHtml(franja.cupos_kiosco)} cupos</span></td>
                            <td>${franja.activo
                                ? '<span class="status status-activo">Activo</span>'
                                : '<span class="status status-inactivo">Inactivo</span>'}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="row-action" type="button" title="Editar" aria-label="Editar"
                                            data-franja-action="edit" data-franja-id="${franja.id}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                    </button>
                                    <button class="row-action is-danger" type="button" title="Eliminar" aria-label="Eliminar"
                                            data-franja-action="delete" data-franja-id="${franja.id}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-trash"></use></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    };

    const estadoFranja = (activo) => activo ? 'Acepta reservas' : 'No acepta reservas';

    const renderBloqueos = () => {
        if (state.bloqueos.length === 0) {
            bloqueosTable.innerHTML = emptyStateHtml({
                icon: 'calendar',
                title: 'Sin bloqueos programados',
                message: 'La agenda de este médico no tiene bloqueos.',
                action: '<button class="btn btn-primary btn-sm" type="button" data-bloqueo-new-inline>Nuevo bloqueo</button>',
            });
            return;
        }

        bloqueosTable.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Motivo</th>
                        <th>Alcance</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.bloqueos.map((bloqueo) => `
                        <tr>
                            <td>${escapeHtml(formatDateNumeric(bloqueo.fecha))}</td>
                            <td>${escapeHtml(bloqueo.motivo || 'Sin motivo indicado')}</td>
                            <td>
                                <span class="badge badge-warning">
                                    ${bloqueo.hora_inicio
                                        ? `${escapeHtml(bloqueo.hora_inicio)} – ${escapeHtml(bloqueo.hora_fin)}`
                                        : 'Todo el día'}
                                </span>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    };

    const openFranja = (franja = null) => {
        state.editingFranja = franja;
        clearFieldErrors(franjaForm);
        franjaForm.reset();

        franjaForm.querySelector('[name="id"]').value = franja ? String(franja.id) : '';
        franjaForm.querySelector('[name="dia_semana"]').value = String(franja?.dia_semana ?? 1);
        franjaForm.querySelector('[name="hora_inicio"]').value = franja?.hora_inicio || '08:00';
        franjaForm.querySelector('[name="hora_fin"]').value = franja?.hora_fin || '12:00';
        franjaForm.querySelector('[name="cupos_web"]').value = String(franja?.cupos_web ?? 0);
        franjaForm.querySelector('[name="cupos_kiosco"]').value = String(franja?.cupos_kiosco ?? 0);
        franjaForm.querySelector('[name="activo"]').checked = franja ? Boolean(franja.activo) : true;

        if (franjaTitle) {
            franjaTitle.textContent = franja ? 'Editar franja' : 'Nueva franja';
        }

        if (franjaSubmit) {
            franjaSubmit.textContent = franja ? 'Guardar cambios' : 'Crear franja';
        }

        openModal(franjaModal);
    };

    const saveFranja = async (event) => {
        event.preventDefault();
        clearFieldErrors(franjaForm);

        const data = new FormData(franjaForm);
        const id = String(data.get('id') || '');
        const body = {
            dia_semana: Number(data.get('dia_semana')),
            hora_inicio: String(data.get('hora_inicio') || ''),
            hora_fin: String(data.get('hora_fin') || ''),
            cupos_web: Number(data.get('cupos_web') || 0),
            cupos_kiosco: Number(data.get('cupos_kiosco') || 0),
            activo: data.get('activo') === 'on',
        };

        setLoading(franjaSubmit, true);

        try {
            const response = await apiFetch(
                id ? `/api/horarios/${id}` : `/api/medicos/${state.medicoId}/horarios`,
                { method: id ? 'PUT' : 'POST', body }
            );

            toast('success', response?.message || 'Franja guardada.');
            closeModal(franjaModal);
            await loadFranjas();
        } catch (error) {
            renderFieldErrors(franjaForm, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(franjaSubmit, false);
        }
    };

    const deleteFranja = async (franja) => {
        const confirmed = await confirmAction({
            title: 'Eliminar franja',
            message: `¿Eliminar la franja del ${DIAS[franja.dia_semana]} de ${franja.hora_inicio} a ${franja.hora_fin}?`,
            confirmLabel: 'Sí, eliminar',
        });

        if (!confirmed) {
            return;
        }

        try {
            await apiFetch(`/api/horarios/${franja.id}`, { method: 'DELETE' });
            toast('success', 'Franja eliminada.');
            await loadFranjas();
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    };

    const openBloqueo = () => {
        clearFieldErrors(bloqueoForm);
        bloqueoForm.reset();
        bloqueoForm.querySelector('[name="fecha"]').value = todayISO();
        bloqueoForm.querySelector('[name="todo_el_dia"]').checked = true;
        syncBloqueoHours();
        openModal(bloqueoModal);
    };

    const syncBloqueoHours = () => {
        const todo = bloqueoForm.querySelector('[name="todo_el_dia"]');
        const horas = bloqueoForm.querySelector('[data-bloqueo-horas]');
        const enabled = !(todo?.checked);

        if (horas) {
            horas.hidden = !enabled;
        }

        horas?.querySelectorAll('input').forEach((input) => {
            input.disabled = !enabled;
            if (!enabled) {
                input.value = '';
            }
        });
    };

    const saveBloqueo = async (event) => {
        event.preventDefault();
        clearFieldErrors(bloqueoForm);

        const data = new FormData(bloqueoForm);
        const todo = data.get('todo_el_dia') === 'on';
        const body = {
            fecha: String(data.get('fecha') || ''),
            motivo: String(data.get('motivo') || '').trim() || null,
            hora_inicio: todo ? null : (String(data.get('hora_inicio') || '') || null),
            hora_fin: todo ? null : (String(data.get('hora_fin') || '') || null),
        };

        setLoading(bloqueoSubmit, true);

        try {
            const response = await apiFetch(`/api/medicos/${state.medicoId}/bloqueos`, {
                method: 'POST',
                body,
            });

            toast('success', response?.message || 'Bloqueo creado.');
            closeModal(bloqueoModal);
            await loadBloqueos();
        } catch (error) {
            renderFieldErrors(bloqueoForm, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(bloqueoSubmit, false);
        }
    };

    const setMedico = async (id) => {
        state.medicoId = Number(id) || 0;
        const medico = state.medicos.find((item) => Number(item.id) === state.medicoId);

        if (doctorLabel) {
            doctorLabel.textContent = medico
                ? `${medicoNombre(medico)}${medico.codigo ? ` · ${medico.codigo}` : ''}`
                : 'Selecciona un médico';
        }

        const url = new URL(window.location.href);
        if (state.medicoId) {
            url.searchParams.set('medico', String(state.medicoId));
        } else {
            url.searchParams.delete('medico');
        }
        window.history.replaceState({}, '', url);

        await Promise.all([loadFranjas(), loadBloqueos()]);
    };

    const init = async () => {
        try {
            state.medicos = await loadMedicos();
        } catch (error) {
            toast('error', apiErrorMessage(error));
            return;
        }

        fillSelect(medicoSelect, state.medicos, {
            placeholder: state.medicos.length === 0 ? 'No hay médicos registrados' : 'Selecciona un médico',
            label: medicoOptionLabel,
            disabled: state.medicos.length === 0,
        });

        const params = new URLSearchParams(window.location.search);
        const requested = Number(params.get('medico') || 0);
        const initial = state.medicos.find((item) => Number(item.id) === requested) || state.medicos[0];

        if (initial) {
            if (medicoSelect) {
                medicoSelect.value = String(initial.id);
            }
            await setMedico(initial.id);
        } else {
            await setMedico(0);
        }
    };

    medicoSelect?.addEventListener('change', () => setMedico(medicoSelect.value));

    $('[data-horario-refresh]')?.addEventListener('click', () => {
        loadFranjas();
        loadBloqueos();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-franja-new]') || event.target.closest('[data-franja-new-inline]')) {
            if (!state.medicoId) {
                toast('error', 'Selecciona un médico primero.');
                return;
            }
            openFranja();
            return;
        }

        if (event.target.closest('[data-bloqueo-new]') || event.target.closest('[data-bloqueo-new-inline]')) {
            if (!state.medicoId) {
                toast('error', 'Selecciona un médico primero.');
                return;
            }
            openBloqueo();
            return;
        }

        const action = event.target.closest('[data-franja-action]');

        if (!action) {
            return;
        }

        const franja = state.franjas.find((item) => Number(item.id) === Number(action.dataset.franjaId));

        if (!franja) {
            return;
        }

        if (action.dataset.franjaAction === 'edit') {
            openFranja(franja);
        } else if (action.dataset.franjaAction === 'delete') {
            deleteFranja(franja);
        }
    });

    franjaForm?.addEventListener('submit', saveFranja);
    bloqueoForm?.addEventListener('submit', saveBloqueo);
    bloqueoForm?.querySelector('[name="todo_el_dia"]')?.addEventListener('change', syncBloqueoHours);

    init();
}
