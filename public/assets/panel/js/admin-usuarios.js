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
    ROLE_LABELS,
    confirmAction,
    emptyStateHtml,
    estadoBadge,
    metaOf,
    normalizeData,
    renderLoadError,
    renderPagination,
    roleBadge,
} from './admin-shared.js';

export default function initAdminUsuarios() {
    const table = $('[data-users-table]');
    const pagination = $('[data-user-pagination]');
    const search = $('[data-user-search]');
    const clear = $('[data-user-clear]');
    const roleFilter = $('[data-user-role]');
    const estadoFilter = $('[data-user-estado]');
    const count = $('[data-user-count]');
    const modal = $('#modal-usuario');
    const form = $('[data-user-form]');
    const title = $('[data-user-modal-title]');
    const submit = $('[data-user-submit]');

    const state = {
        page: 1,
        perPage: 15,
        q: '',
        rol: '',
        estado: '',
        usuarios: [],
        meta: {},
        editing: null,
    };

    if (!table || !form) {
        return;
    }

    const load = async () => {
        table.innerHTML = `<div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(6)}</div>`;

        try {
            const query = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
            });

            if (state.q) query.set('q', state.q);
            if (state.rol) query.set('rol', state.rol);
            if (state.estado) query.set('estado', state.estado);

            const response = await apiFetch(`/api/usuarios?${query.toString()}`);
            state.usuarios = normalizeData(response);
            state.meta = metaOf(response);

            renderRows();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.usuarios.length} cuenta(s)`;
            }
        } catch (error) {
            renderLoadError(table, error, load);
        }
    };

    const loadResumen = async () => {
        try {
            const [total, activos, bloqueados] = await Promise.all([
                apiFetch('/api/usuarios?per_page=1'),
                apiFetch('/api/usuarios?per_page=1&estado=activo'),
                apiFetch('/api/usuarios?per_page=1&bloqueado=1'),
            ]);

            setText('[data-user-kpi="total"]', metaOf(total).total ?? 0);
            setText('[data-user-kpi="activos"]', metaOf(activos).total ?? 0);
            setText('[data-user-kpi="bloqueados"]', metaOf(bloqueados).total ?? 0);
        } catch (error) {
            // Los contadores son complementarios: el listado ya muestra el error real.
        }
    };

    const renderRows = () => {
        if (state.usuarios.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'users',
                title: 'Sin usuarios para mostrar',
                message: state.q || state.rol || state.estado
                    ? 'No hay cuentas que coincidan con los filtros.'
                    : 'Todavía no hay cuentas registradas.',
                action: '<button class="btn btn-primary btn-sm" type="button" data-user-new-inline>Nuevo usuario</button>',
            });
            return;
        }

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último acceso</th>
                        <th><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${state.usuarios.map((usuario) => `
                        <tr>
                            <td>
                                <div class="cell-person">
                                    <span class="avatar avatar-sm">${escapeHtml(initials(usuario.username || usuario.email))}</span>
                                    <div>
                                        <strong>${escapeHtml(usuario.username || 'Sin usuario')}</strong>
                                        <small>${usuario.bloqueado ? `Bloqueado · ${usuario.intentos_fallidos} intentos` : 'Cuenta del sistema'}</small>
                                    </div>
                                </div>
                            </td>
                            <td>${escapeHtml(usuario.email || '—')}</td>
                            <td>${roleBadge(usuario.rol)}</td>
                            <td>${estadoBadge(Boolean(usuario.activo), Boolean(usuario.bloqueado))}</td>
                            <td>${escapeHtml(usuario.ultimo_acceso || 'Nunca')}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="row-action" type="button" title="Editar" aria-label="Editar"
                                            data-user-action="edit" data-user-id="${usuario.id}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                    </button>
                                    ${usuario.bloqueado ? `
                                        <button class="row-action" type="button" title="Desbloquear" aria-label="Desbloquear"
                                                data-user-action="unlock" data-user-id="${usuario.id}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-lock"></use></svg>
                                        </button>
                                    ` : `
                                        <button class="row-action ${usuario.activo ? 'is-danger' : ''}" type="button"
                                                title="${usuario.activo ? 'Desactivar' : 'Activar'}"
                                                aria-label="${usuario.activo ? 'Desactivar' : 'Activar'}"
                                                data-user-action="toggle" data-user-id="${usuario.id}">
                                            <svg class="icon" aria-hidden="true"><use href="#i-${usuario.activo ? 'x-circle' : 'check-circle'}"></use></svg>
                                        </button>
                                    `}
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    };

    const openForm = (usuario = null) => {
        state.editing = usuario;
        clearFieldErrors(form);
        form.reset();

        const id = form.querySelector('[name="id"]');
        const password = form.querySelector('[name="password"]');
        const confirmation = form.querySelector('[name="password_confirmation"]');

        if (id) id.value = usuario ? String(usuario.id) : '';
        form.querySelector('[name="username"]').value = usuario?.username || '';
        form.querySelector('[name="email"]').value = usuario?.email || '';
        form.querySelector('[name="rol"]').value = usuario?.rol || 'recepcionista';
        form.querySelector('[name="activo"]').checked = usuario ? Boolean(usuario.activo) : true;

        if (password) {
            password.value = '';
            password.required = !usuario;
        }

        if (confirmation) {
            confirmation.value = '';
        }

        if (title) {
            title.textContent = usuario ? `Editar ${usuario.username || 'usuario'}` : 'Nuevo usuario';
        }

        if (submit) {
            submit.textContent = usuario ? 'Guardar cambios' : 'Crear usuario';
        }

        openModal(modal);
    };

    const save = async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const data = new FormData(form);
        const id = String(data.get('id') || '');
        const password = String(data.get('password') || '');
        const body = {
            username: String(data.get('username') || '').trim() || null,
            email: String(data.get('email') || '').trim() || null,
            rol: String(data.get('rol') || ''),
            activo: data.get('activo') === 'on',
        };

        if (!id || password !== '') {
            body.password = password;
            body.password_confirmation = String(data.get('password_confirmation') || '');
        }

        setLoading(submit, true);

        try {
            const response = await apiFetch(id ? `/api/usuarios/${id}` : '/api/usuarios', {
                method: id ? 'PUT' : 'POST',
                body,
            });

            toast('success', response?.message || 'Usuario guardado.');
            closeModal(modal);
            await Promise.all([load(), loadResumen()]);
        } catch (error) {
            renderFieldErrors(form, error?.fields || {});
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submit, false);
        }
    };

    const toggle = async (usuario) => {
        const activar = !usuario.activo;
        const confirmed = await confirmAction({
            title: activar ? 'Activar cuenta' : 'Desactivar cuenta',
            message: activar
                ? `¿Permitir nuevamente el acceso de ${usuario.username}?`
                : `¿Bloquear el acceso de ${usuario.username}? La cuenta dejará de poder iniciar sesión.`,
            confirmLabel: activar ? 'Sí, activar' : 'Sí, desactivar',
            danger: !activar,
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiFetch(`/api/usuarios/${usuario.id}/estado`, {
                method: 'PATCH',
                body: { activo: activar },
            });
            toast('success', response?.message || 'Usuario actualizado.');
            await Promise.all([load(), loadResumen()]);
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    };

    const unlock = async (usuario) => {
        const confirmed = await confirmAction({
            title: 'Desbloquear cuenta',
            message: `¿Restablecer el acceso de ${usuario.username}? Se limpiarán los intentos fallidos.`,
            confirmLabel: 'Sí, desbloquear',
            danger: false,
        });

        if (!confirmed) {
            return;
        }

        try {
            const response = await apiFetch(`/api/usuarios/${usuario.id}/desbloquear`, { method: 'PATCH' });
            toast('success', response?.message || 'Usuario desbloqueado.');
            await Promise.all([load(), loadResumen()]);
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

    roleFilter?.addEventListener('change', () => {
        state.rol = roleFilter.value;
        state.page = 1;
        load();
    });

    estadoFilter?.addEventListener('change', () => {
        state.estado = estadoFilter.value;
        state.page = 1;
        load();
    });

    $('[data-user-refresh]')?.addEventListener('click', () => {
        load();
        loadResumen();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-user-new]') || event.target.closest('[data-user-new-inline]')) {
            openForm();
            return;
        }

        const action = event.target.closest('[data-user-action]');

        if (!action) {
            return;
        }

        const usuario = state.usuarios.find((item) => Number(item.id) === Number(action.dataset.userId));

        if (!usuario) {
            return;
        }

        if (action.dataset.userAction === 'edit') {
            openForm(usuario);
        } else if (action.dataset.userAction === 'toggle') {
            toggle(usuario);
        } else if (action.dataset.userAction === 'unlock') {
            unlock(usuario);
        }
    });

    form.addEventListener('submit', save);

    if (roleFilter) {
        roleFilter.innerHTML = '<option value="">Todos los roles</option>'
            + Object.entries(ROLE_LABELS)
                .map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`)
                .join('');
    }

    const rolSelect = form.querySelector('[name="rol"]');
    if (rolSelect) {
        rolSelect.innerHTML = Object.entries(ROLE_LABELS)
            .map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`)
            .join('');
    }

    load();
    loadResumen();
}

function setText(selector, value) {
    const node = document.querySelector(selector);
    if (node) {
        node.textContent = String(value ?? '—');
    }
}
