import {
    $,
    apiFetch,
    apiErrorMessage,
    clearFieldErrors,
    closeModal,
    debounce,
    escapeHtml,
    initials,
    openModal,
    renderFieldErrors,
    setLoading,
    toast,
} from './app.js';
import {
    checkedValues,
    confirmAction,
    emptyStateHtml,
    estadoBadge,
    loadEspecialidades,
    loadRecepcionistas,
    medicoNombre,
    metaOf,
    normalizeData,
    personaNombre,
    renderLoadError,
    renderPagination,
    renderSpecialtyCheckboxes,
} from './admin-shared.js';

export default function initAdminMedicos() {
    const grid = $('[data-medicos-grid]');
    const pagination = $('[data-medico-pagination]');
    const search = $('[data-medico-search]');
    const clear = $('[data-medico-clear]');
    const specialtyFilter = $('[data-medico-especialidad]');
    const estadoFilter = $('[data-medico-estado]');
    const count = $('[data-medico-count]');

    const modal = $('#modal-medico');
    const form = $('[data-medico-form]');
    const title = $('[data-medico-modal-title]');
    const submit = $('[data-medico-submit]');
    const specialtyBox = $('[data-medico-especialidades]');
    const account = $('[data-medico-account]');

    const detailModal = $('#modal-medico-detalle');
    const detailBody = $('[data-medico-detalle-body]');

    if (!grid || !form) {
        return;
    }

    const state = {
        page: 1,
        perPage: 9,
        q: '',
        especialidad: '',
        estado: '',
        medicos: [],
        meta: {},
        especialidades: [],
        recepcionistas: [],
        editing: null,
        detail: null,
    };

    const load = async () => {
        grid.innerHTML = `<div class="quick-grid quick-grid-3">${'<div class="card"><div class="skeleton skeleton-block"></div></div>'.repeat(3)}</div>`;

        try {
            const query = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
            });

            if (state.q) query.set('q', state.q);
            if (state.especialidad) query.set('especialidad', state.especialidad);
            if (state.estado) query.set('estado', state.estado);

            const response = await apiFetch(`/api/medicos?${query.toString()}`);
            state.medicos = normalizeData(response);
            state.meta = metaOf(response);

            renderCards();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.medicos.length} médico(s)`;
            }
        } catch (error) {
            renderLoadError(grid, error, load);
        }
    };

    const renderCards = () => {
        if (state.medicos.length === 0) {
            grid.innerHTML = emptyStateHtml({
                icon: 'stethoscope',
                title: 'Sin médicos para mostrar',
                message: state.q || state.especialidad || state.estado
                    ? 'No hay perfiles que coincidan con los filtros.'
                    : 'Registra el primer médico para empezar a agendar.',
                action: '<button class="btn btn-primary btn-sm" type="button" data-medico-new-inline>Nuevo médico</button>',
            });
            return;
        }

        grid.innerHTML = `
            <div class="quick-grid quick-grid-3">
                ${state.medicos.map((medico) => `
                    <article class="card">
                        <div class="card-head">
                            <div class="cell-person">
                                <span class="avatar">${escapeHtml(initials(medicoNombre(medico)))}</span>
                                <div>
                                    <strong>${escapeHtml(medicoNombre(medico))}</strong>
                                    <small>${escapeHtml(medico.codigo || '—')} · ${escapeHtml(medico.registro_profesional || '—')}</small>
                                </div>
                            </div>
                            ${estadoBadge(medico.estado === 'activo')}
                        </div>
                        <div class="admin-chip-row">
                            ${(medico.especialidades || []).length > 0
                                ? medico.especialidades.map((item) => `<span class="badge badge-accent">${escapeHtml(item.nombre)}</span>`).join('')
                                : '<span class="badge badge-muted">Sin especialidades</span>'}
                        </div>
                        <p class="admin-card-line">
                            <svg class="icon" aria-hidden="true"><use href="#i-clock"></use></svg>
                            ${escapeHtml(medico.duracion_cita_min || 30)} min por cita
                        </p>
                        <p class="admin-card-line">
                            <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
                            ${medico.qr_url ? 'QR cargado' : 'Sin QR subido'}
                        </p>
                        <div class="row-actions">
                            <button class="row-action" type="button" title="Ver detalle" aria-label="Ver detalle"
                                    data-medico-action="detail" data-medico-id="${medico.id}">
                                <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                            </button>
                            <button class="row-action" type="button" title="Editar" aria-label="Editar"
                                    data-medico-action="edit" data-medico-id="${medico.id}">
                                <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                            </button>
                            <a class="row-action" title="Horarios" aria-label="Horarios"
                               href="/admin/horarios?medico=${medico.id}">
                                <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
                            </a>
                            <button class="row-action ${medico.estado === 'activo' ? 'is-danger' : ''}" type="button"
                                    title="${medico.estado === 'activo' ? 'Desactivar' : 'Activar'}"
                                    aria-label="${medico.estado === 'activo' ? 'Desactivar' : 'Activar'}"
                                    data-medico-action="toggle" data-medico-id="${medico.id}">
                                <svg class="icon" aria-hidden="true"><use href="#i-${medico.estado === 'activo' ? 'x-circle' : 'check-circle'}"></use></svg>
                            </button>
                        </div>
                    </article>
                `).join('')}
            </div>
        `;
    };

    const loadAux = async () => {
        try {
            const [especialidades, recepcionistas] = await Promise.all([
                loadEspecialidades(),
                loadRecepcionistas(),
            ]);

            state.especialidades = especialidades;
            state.recepcionistas = recepcionistas;

            if (specialtyFilter) {
                specialtyFilter.innerHTML = '<option value="">Todas las especialidades</option>'
                    + especialidades
                        .filter((item) => item.estado === 'activo')
                        .map((item) => `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`)
                        .join('');
            }
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    };

    const openForm = (medico = null) => {
        state.editing = medico;
        clearFieldErrors(form);
        form.reset();

        form.querySelector('[name="id"]').value = medico ? String(medico.id) : '';
        form.querySelector('[name="registro_profesional"]').value = medico?.registro_profesional || '';
        form.querySelector('[name="nombres"]').value = medico?.nombres || '';
        form.querySelector('[name="apellidos"]').value = medico?.apellidos || '';
        form.querySelector('[name="telefono"]').value = medico?.telefono || '';
        form.querySelector('[name="email"]').value = medico?.email || '';
        form.querySelector('[name="duracion_cita_min"]').value = String(medico?.duracion_cita_min || 30);

        renderSpecialtyCheckboxes(
            specialtyBox,
            state.especialidades,
            (medico?.especialidades || []).map((item) => item.id)
        );

        if (account) {
            account.hidden = Boolean(medico);
        }

        const crearUsuario = form.querySelector('[name="crear_usuario"]');
        if (crearUsuario) {
            crearUsuario.checked = true;
        }

        if (title) {
            title.textContent = medico ? `Editar ${medicoNombre(medico)}` : 'Nuevo médico';
        }

        if (submit) {
            submit.textContent = medico ? 'Guardar cambios' : 'Crear médico';
        }

        openModal(modal);
    };

    const save = async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const data = new FormData(form);
        const id = String(data.get('id') || '');
        const crearUsuario = data.get('crear_usuario') === 'on';
        const body = {
            registro_profesional: String(data.get('registro_profesional') || '').trim(),
            nombres: String(data.get('nombres') || '').trim(),
            apellidos: String(data.get('apellidos') || '').trim(),
            telefono: String(data.get('telefono') || '').trim() || null,
            email: String(data.get('email') || '').trim() || null,
            duracion_cita_min: Number(data.get('duracion_cita_min') || 30),
            especialidades: checkedValues(specialtyBox, 'especialidades[]'),
        };

        if (!id) {
            body.crear_usuario = crearUsuario;

            if (crearUsuario) {
                body.username = String(data.get('username') || '').trim();
                body.password = String(data.get('password') || '');
                body.password_confirmation = String(data.get('password_confirmation') || '');
            }
        }

        setLoading(submit, true);

        try {
            const response = await apiFetch(id ? `/api/medicos/${id}` : '/api/medicos', {
                method: id ? 'PUT' : 'POST',
                body,
            });

            toast('success', response?.message || 'Médico guardado.');
            closeModal(modal);
            await load();
        } catch (error) {
            renderFieldErrors(form, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submit, false);
        }
    };

    const openDetail = async (medico) => {
        state.detail = medico;

        if (!detailModal || !detailBody) {
            return;
        }

        detailBody.innerHTML = '<div class="table-loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>';
        openModal(detailModal);

        try {
            const response = await apiFetch(`/api/medicos/${medico.id}`);
            const data = response?.data || medico;
            state.detail = data;
            renderDetail(data);
        } catch (error) {
            detailBody.innerHTML = `<p class="field-error">${escapeHtml(apiErrorMessage(error))}</p>`;
        }
    };

    const renderDetail = (medico) => {
        const recepcionistas = Array.isArray(medico.recepcionistas) ? medico.recepcionistas : [];
        const asignados = new Set(recepcionistas.map((item) => Number(item.id)));
        const disponibles = state.recepcionistas.filter((item) => !asignados.has(Number(item.id)) && item.activo);

        detailBody.innerHTML = `
            <div class="ficha-summary">
                <div class="ficha-summary-item"><span>Registro</span><strong>${escapeHtml(medico.registro_profesional || '—')}</strong></div>
                <div class="ficha-summary-item"><span>Duración</span><strong>${escapeHtml(medico.duracion_cita_min || 30)} min</strong></div>
                <div class="ficha-summary-item"><span>Estado</span><strong>${escapeHtml(medico.estado === 'activo' ? 'Activo' : 'Inactivo')}</strong></div>
                <div class="ficha-summary-item"><span>Contacto</span><strong>${escapeHtml(medico.telefono || '—')}</strong></div>
            </div>

            <div class="history-block">
                <h3>Especialidades</h3>
                <div class="admin-chip-row">
                    ${(medico.especialidades || []).length > 0
                        ? medico.especialidades.map((item) => `<span class="badge badge-accent">${escapeHtml(item.nombre)}</span>`).join('')
                        : '<span class="badge badge-muted">Sin especialidades</span>'}
                </div>
            </div>

            <div class="history-block">
                <h3>Recepcionistas asignados</h3>
                ${recepcionistas.length > 0 ? `
                    <div class="stack-links">
                        ${recepcionistas.map((item) => `
                            <div class="stack-link">
                                <span class="stack-icon" aria-hidden="true"><svg class="icon"><use href="#i-user"></use></svg></span>
                                <span>
                                    <strong>${escapeHtml(item.username || personaNombre(item))}</strong>
                                    <small>${escapeHtml(item.email || '—')}</small>
                                </span>
                                <span class="badge ${item.activo ? 'badge-success' : 'badge-muted'}">${item.activo ? 'Activo' : 'Inactivo'}</span>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="field-hint">Este médico aún no tiene recepcionistas asignados.</p>'}

                <form class="admin-inline-form" data-medico-asignar-form>
                    <div class="field">
                        <label for="medico-recepcionista">Asignar recepcionista</label>
                        <div class="select-wrap">
                            <select id="medico-recepcionista" name="usuario_id" data-medico-recepcionista-select ${disponibles.length === 0 ? 'disabled' : ''}>
                                <option value="">${disponibles.length === 0 ? 'No hay recepcionistas disponibles' : 'Selecciona una cuenta'}</option>
                                ${disponibles.map((item) => `<option value="${item.id}">${escapeHtml(item.username)} · ${escapeHtml(item.email || '')}</option>`).join('')}
                            </select>
                            <svg class="icon" aria-hidden="true"><use href="#i-chevron-down"></use></svg>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit" ${disponibles.length === 0 ? 'disabled' : ''}>
                        Asignar
                    </button>
                </form>
                <p class="field-hint">Para crear una cuenta de recepción médica, ve a Usuarios y elige el rol correspondiente.</p>
            </div>

            <div class="history-block">
                <h3>QR de acceso</h3>
                ${medico.qr_url
                    ? `<img src="${escapeHtml(medico.qr_url)}" alt="Código QR de ${escapeHtml(medicoNombre(medico))}" class="admin-qr-preview">`
                    : '<p class="field-hint">El médico aún no subió su código QR desde su panel.</p>'}
            </div>
        `;

        detailBody.querySelector('[data-medico-asignar-form]')?.addEventListener('submit', (event) => {
            event.preventDefault();
            assign(medico.id);
        });
    };

    const assign = async (medicoId) => {
        const select = detailBody.querySelector('[data-medico-recepcionista-select]');
        const usuarioId = Number(select?.value || 0);

        if (!usuarioId) {
            toast('error', 'Selecciona una cuenta de recepción.');
            return;
        }

        try {
            const response = await apiFetch(`/api/medicos/${medicoId}/recepcionistas`, {
                method: 'POST',
                body: { usuario_id: usuarioId },
            });
            toast('success', response?.message || 'Recepcionista asignado.');
            const refreshed = await apiFetch(`/api/medicos/${medicoId}`);
            renderDetail(refreshed?.data || state.detail);
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    };

    const toggle = async (medico) => {
        const activar = medico.estado !== 'activo';
        const confirmed = await confirmAction({
            title: activar ? 'Activar médico' : 'Desactivar médico',
            message: activar
                ? `¿Reactivar el perfil de ${medicoNombre(medico)} para agendar citas?`
                : `¿Desactivar a ${medicoNombre(medico)}? No podrá recibir nuevas citas y debe no tener citas futuras activas.`,
            confirmLabel: activar ? 'Sí, activar' : 'Sí, desactivar',
            danger: !activar,
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiFetch(`/api/medicos/${medico.id}/estado`, {
                method: 'PATCH',
                body: { estado: activar ? 'activo' : 'inactivo' },
            });
            toast('success', response?.message || 'Médico actualizado.');
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

    specialtyFilter?.addEventListener('change', () => {
        state.especialidad = specialtyFilter.value;
        state.page = 1;
        load();
    });

    estadoFilter?.addEventListener('change', () => {
        state.estado = estadoFilter.value;
        state.page = 1;
        load();
    });

    $('[data-medico-refresh]')?.addEventListener('click', () => {
        load();
        loadAux();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-medico-new]') || event.target.closest('[data-medico-new-inline]')) {
            openForm();
            return;
        }

        const action = event.target.closest('[data-medico-action]');

        if (!action) {
            return;
        }

        const medico = state.medicos.find((item) => Number(item.id) === Number(action.dataset.medicoId));

        if (!medico) {
            return;
        }

        if (action.dataset.medicoAction === 'edit') {
            openForm(medico);
        } else if (action.dataset.medicoAction === 'detail') {
            openDetail(medico);
        } else if (action.dataset.medicoAction === 'toggle') {
            toggle(medico);
        }
    });

    form.addEventListener('submit', save);

    const crearUsuario = form.querySelector('[name="crear_usuario"]');
    const accountFields = $('[data-medico-account-fields]');

    const syncAccountFields = () => {
        const enabled = crearUsuario ? crearUsuario.checked : false;

        if (accountFields) {
            accountFields.hidden = !enabled;
        }

        accountFields?.querySelectorAll('input').forEach((input) => {
            input.disabled = !enabled;
        });
    };

    crearUsuario?.addEventListener('change', syncAccountFields);
    syncAccountFields();

    loadAux().then(load);
}
