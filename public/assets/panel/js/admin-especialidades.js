import {
    $,
    apiFetch,
    apiErrorMessage,
    clearFieldErrors,
    closeModal,
    debounce,
    escapeHtml,
    openModal,
    renderFieldErrors,
    setLoading,
    toast,
} from './app.js';
import {
    confirmAction,
    emptyStateHtml,
    estadoBadge,
    metaOf,
    normalizeData,
    renderLoadError,
    renderPagination,
} from './admin-shared.js';

const ICONOS = ['stethoscope', 'heart', 'users', 'shield', 'spark', 'activity', 'user', 'file', 'clock', 'clipboard', 'calendar'];

export default function initAdminEspecialidades() {
    const table = $('[data-especialidades-table]');
    const pagination = $('[data-especialidad-pagination]');
    const search = $('[data-especialidad-search]');
    const clear = $('[data-especialidad-clear]');
    const estadoFilter = $('[data-especialidad-estado]');
    const destacadaFilter = $('[data-especialidad-destacada]');
    const count = $('[data-especialidad-count]');
    const modal = $('#modal-especialidad');
    const form = $('[data-especialidad-form]');
    const title = $('[data-especialidad-modal-title]');
    const submit = $('[data-especialidad-submit]');

    if (!table || !form) {
        return;
    }

    const state = {
        page: 1,
        perPage: 15,
        q: '',
        estado: '',
        destacada: '',
        especialidades: [],
        meta: {},
        editing: null,
    };

    const load = async () => {
        table.innerHTML = `<div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(6)}</div>`;

        try {
            const query = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
            });

            if (state.q) query.set('q', state.q);
            if (state.estado) query.set('estado', state.estado);
            if (state.destacada) query.set('destacada', state.destacada);

            const response = await apiFetch(`/api/especialidades?${query.toString()}`);
            state.especialidades = normalizeData(response);
            state.meta = metaOf(response);

            renderRows();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.especialidades.length} especialidad(es)`;
            }
        } catch (error) {
            renderLoadError(table, error, load);
        }
    };

    const renderRows = () => {
        if (state.especialidades.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'stethoscope',
                title: 'Sin especialidades para mostrar',
                message: state.q || state.estado || state.destacada
                    ? 'No hay especialidades que coincidan con los filtros.'
                    : 'Crea la primera especialidad del catálogo.',
                action: '<button class="btn btn-primary btn-sm" type="button" data-especialidad-new-inline>Nueva especialidad</button>',
            });
            return;
        }

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Especialidad</th>
                        <th>Restricción</th>
                        <th>Médicos</th>
                        <th>Destacada</th>
                        <th>Estado</th>
                        <th><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${state.especialidades.map((especialidad) => `
                        <tr>
                            <td>
                                <div class="cell-person">
                                    <span class="avatar avatar-sm"><svg class="icon" aria-hidden="true"><use href="#i-${escapeHtml(especialidad.icono || 'stethoscope')}"></use></svg></span>
                                    <div>
                                        <strong>${escapeHtml(especialidad.nombre)}</strong>
                                        <small>${escapeHtml(especialidad.descripcion || 'Sin descripción')}</small>
                                    </div>
                                </div>
                            </td>
                            <td>${escapeHtml(restriccion(especialidad))}</td>
                            <td>${escapeHtml(especialidad.medicos_count ?? 0)}</td>
                            <td>
                                ${especialidad.destacada
                                    ? '<span class="badge badge-pine"><svg class="icon" aria-hidden="true"><use href="#i-spark"></use></svg> Destacada</span>'
                                    : '<span class="badge badge-muted">—</span>'}
                            </td>
                            <td>${estadoBadge(especialidad.estado === 'activo')}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="row-action" type="button" title="Editar" aria-label="Editar"
                                            data-especialidad-action="edit" data-especialidad-id="${especialidad.id}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                    </button>
                                    <button class="row-action ${especialidad.estado === 'activo' ? 'is-danger' : ''}" type="button"
                                            title="${especialidad.estado === 'activo' ? 'Desactivar' : 'Activar'}"
                                            aria-label="${especialidad.estado === 'activo' ? 'Desactivar' : 'Activar'}"
                                            data-especialidad-action="toggle" data-especialidad-id="${especialidad.id}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-${especialidad.estado === 'activo' ? 'x-circle' : 'check-circle'}"></use></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    };

    const restriccion = (especialidad) => {
        const partes = [];

        if (especialidad.sexo_permitido === 'femenino') {
            partes.push('Solo femenino');
        } else if (especialidad.sexo_permitido === 'masculino') {
            partes.push('Solo masculino');
        }

        if (especialidad.edad_min !== null && especialidad.edad_min !== undefined) {
            partes.push(`Desde ${especialidad.edad_min} años`);
        }

        if (especialidad.edad_max !== null && especialidad.edad_max !== undefined) {
            partes.push(`Hasta ${especialidad.edad_max} años`);
        }

        return partes.length > 0 ? partes.join(' · ') : 'Sin restricciones';
    };

    const openForm = (especialidad = null) => {
        state.editing = especialidad;
        clearFieldErrors(form);
        form.reset();

        form.querySelector('[name="id"]').value = especialidad ? String(especialidad.id) : '';
        form.querySelector('[name="nombre"]').value = especialidad?.nombre || '';
        form.querySelector('[name="descripcion"]').value = especialidad?.descripcion || '';
        form.querySelector('[name="sexo_permitido"]').value = especialidad?.sexo_permitido || 'ambos';
        form.querySelector('[name="edad_min"]').value = especialidad?.edad_min ?? '';
        form.querySelector('[name="edad_max"]').value = especialidad?.edad_max ?? '';
        form.querySelector('[name="estado"]').value = especialidad?.estado || 'activo';
        form.querySelector('[name="destacada"]').checked = Boolean(especialidad?.destacada);

        const icono = form.querySelector('[name="icono"]');
        if (icono) {
            const valor = especialidad?.icono || 'stethoscope';
            if (!Array.from(icono.options).some((option) => option.value === valor)) {
                const option = document.createElement('option');
                option.value = valor;
                option.textContent = valor;
                icono.appendChild(option);
            }
            icono.value = valor;
        }

        if (title) {
            title.textContent = especialidad ? `Editar ${especialidad.nombre}` : 'Nueva especialidad';
        }

        if (submit) {
            submit.textContent = especialidad ? 'Guardar cambios' : 'Crear especialidad';
        }

        openModal(modal);
    };

    const save = async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const data = new FormData(form);
        const id = String(data.get('id') || '');
        const edadMin = String(data.get('edad_min') || '').trim();
        const edadMax = String(data.get('edad_max') || '').trim();
        const body = {
            nombre: String(data.get('nombre') || '').trim(),
            descripcion: String(data.get('descripcion') || '').trim() || null,
            sexo_permitido: String(data.get('sexo_permitido') || 'ambos'),
            edad_min: edadMin === '' ? null : Number(edadMin),
            edad_max: edadMax === '' ? null : Number(edadMax),
            destacada: data.get('destacada') === 'on',
            icono: String(data.get('icono') || '') || null,
            estado: String(data.get('estado') || 'activo'),
        };

        setLoading(submit, true);

        try {
            const response = await apiFetch(id ? `/api/especialidades/${id}` : '/api/especialidades', {
                method: id ? 'PUT' : 'POST',
                body,
            });

            toast('success', response?.message || 'Especialidad guardada.');
            closeModal(modal);
            await load();
        } catch (error) {
            renderFieldErrors(form, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submit, false);
        }
    };

    const toggle = async (especialidad) => {
        const activar = especialidad.estado !== 'activo';
        const confirmed = await confirmAction({
            title: activar ? 'Activar especialidad' : 'Desactivar especialidad',
            message: activar
                ? `¿Volver a mostrar ${especialidad.nombre} en la web y permitir nuevas citas?`
                : `¿Desactivar ${especialidad.nombre}? Dejará de estar disponible para nuevas citas.`,
            confirmLabel: activar ? 'Sí, activar' : 'Sí, desactivar',
            danger: !activar,
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiFetch(`/api/especialidades/${especialidad.id}/estado`, {
                method: 'PATCH',
                body: { estado: activar ? 'activo' : 'inactivo' },
            });
            toast('success', response?.message || 'Especialidad actualizada.');
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

    estadoFilter?.addEventListener('change', () => {
        state.estado = estadoFilter.value;
        state.page = 1;
        load();
    });

    destacadaFilter?.addEventListener('change', () => {
        state.destacada = destacadaFilter.value;
        state.page = 1;
        load();
    });

    $('[data-especialidad-refresh]')?.addEventListener('click', load);

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-especialidad-new]') || event.target.closest('[data-especialidad-new-inline]')) {
            openForm();
            return;
        }

        const action = event.target.closest('[data-especialidad-action]');

        if (!action) {
            return;
        }

        const especialidad = state.especialidades.find((item) => Number(item.id) === Number(action.dataset.especialidadId));

        if (!especialidad) {
            return;
        }

        if (action.dataset.especialidadAction === 'edit') {
            openForm(especialidad);
        } else if (action.dataset.especialidadAction === 'toggle') {
            toggle(especialidad);
        }
    });

    form.addEventListener('submit', save);

    const icono = form.querySelector('[name="icono"]');
    if (icono) {
        icono.innerHTML = ICONOS.map((item) => `<option value="${item}">${item}</option>`).join('');
    }

    load();
}
