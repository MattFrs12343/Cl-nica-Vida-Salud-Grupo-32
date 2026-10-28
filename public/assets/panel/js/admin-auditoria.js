import {
    $,
    apiFetch,
    apiErrorMessage,
    debounce,
    escapeHtml,
    openModal,
} from './app.js';
import {
    emptyStateHtml,
    formatDateNumeric,
    metaOf,
    normalizeData,
    personaNombre,
    renderPagination,
    roleBadge,
} from './admin-shared.js';

const ACCION_BADGES = {
    INSERT: 'badge-success',
    UPDATE: 'badge-info',
    DELETE: 'badge-danger',
};

export default function initAdminAuditoria() {
    const body = $('[data-auditoria-body]');
    const pagination = $('[data-auditoria-pagination]');
    const search = $('[data-auditoria-search]');
    const clear = $('[data-auditoria-clear]');
    const accion = $('[data-auditoria-accion]');
    const tabla = $('[data-auditoria-tabla]');
    const desde = $('[data-auditoria-desde]');
    const hasta = $('[data-auditoria-hasta]');
    const count = $('[data-auditoria-count]');

    const modal = $('#modal-auditoria');
    const modalBody = $('[data-auditoria-detalle]');

    if (!body) {
        return;
    }

    const state = {
        page: 1,
        perPage: 20,
        usuario: '',
        accion: '',
        tabla: '',
        desde: '',
        hasta: '',
        rows: [],
        meta: {},
    };

    const load = async () => {
        body.innerHTML = `<tr><td colspan="7"><div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(6)}</div></td></tr>`;

        try {
            const query = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
            });

            if (state.usuario) query.set('usuario', state.usuario);
            if (state.accion) query.set('accion', state.accion);
            if (state.tabla) query.set('tabla', state.tabla);
            if (state.desde) query.set('desde', state.desde);
            if (state.hasta) query.set('hasta', state.hasta);

            const response = await apiFetch(`/api/auditoria?${query.toString()}`);
            state.rows = normalizeData(response);
            state.meta = metaOf(response);

            renderRows();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.rows.length} registro(s)`;
            }
        } catch (error) {
            body.innerHTML = `<tr><td colspan="7">
                <div class="error-state">
                    <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <h2>No pudimos cargar la auditoría</h2>
                    <p>${escapeHtml(apiErrorMessage(error))}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-auditoria-retry>
                        Reintentar
                        <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    </button>
                </div>
            </td></tr>`;
            body.querySelector('[data-auditoria-retry]')?.addEventListener('click', load);
        }
    };

    const renderRows = () => {
        if (state.rows.length === 0) {
            body.innerHTML = `<tr><td colspan="7">${emptyStateHtml({
                icon: 'shield',
                title: 'Sin registros de auditoría',
                message: 'No hay actividad que coincida con los filtros.',
            })}</td></tr>`;
            return;
        }

        body.innerHTML = state.rows.map((row) => `
            <tr>
                <td>
                    <div class="cell-stack">
                        <strong>${escapeHtml(formatFechaHora(row.fecha))}</strong>
                        <small>#${escapeHtml(row.id)}</small>
                    </div>
                </td>
                <td>
                    ${row.usuario ? `
                        <div class="cell-stack">
                            <strong>${escapeHtml(personaNombre(row.usuario))}</strong>
                            <small>${escapeHtml(row.usuario.email || '')}</small>
                        </div>
                    ` : '<span class="text-muted">Sistema</span>'}
                </td>
                <td>${row.usuario ? roleBadge(row.usuario.rol) : '—'}</td>
                <td><span class="badge ${ACCION_BADGES[row.accion] || 'badge-muted'}">${escapeHtml(row.accion || '—')}</span></td>
                <td>
                    <div class="cell-stack">
                        <code class="code-pill">${escapeHtml(row.tabla_afectada || '—')}</code>
                        <small>ID ${escapeHtml(row.registro_id ?? '—')}</small>
                    </div>
                </td>
                <td><code class="code-pill">${escapeHtml(row.ip || '—')}</code></td>
                <td>
                    <div class="row-actions">
                        <button class="row-action" type="button" title="Ver detalle" aria-label="Ver detalle"
                                data-auditoria-view="${escapeHtml(row.id)}">
                            <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    };

    const openDetalle = async (id) => {
        modalBody.innerHTML = '<div class="table-loading"><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>';
        openModal(modal);

        try {
            const response = await apiFetch(`/api/auditoria/${id}`);
            renderDetalle(response?.data || {});
        } catch (error) {
            modalBody.innerHTML = `<p class="field-error">${escapeHtml(apiErrorMessage(error))}</p>`;
        }
    };

    const renderDetalle = (row) => {
        const cambios = Array.isArray(row.cambios) ? row.cambios : [];

        modalBody.innerHTML = `
            <div class="audit-summary">
                <div>
                    <span class="audit-label">Acción</span>
                    <strong><span class="badge ${ACCION_BADGES[row.accion] || 'badge-muted'}">${escapeHtml(row.accion || '—')}</span></strong>
                </div>
                <div>
                    <span class="audit-label">Tabla</span>
                    <strong><code class="code-pill">${escapeHtml(row.tabla_afectada || '—')}</code></strong>
                </div>
                <div>
                    <span class="audit-label">Registro</span>
                    <strong>#${escapeHtml(row.registro_id ?? '—')}</strong>
                </div>
                <div>
                    <span class="audit-label">Fecha</span>
                    <strong>${escapeHtml(formatFechaHora(row.fecha))}</strong>
                </div>
                <div>
                    <span class="audit-label">Usuario</span>
                    <strong>${row.usuario ? escapeHtml(personaNombre(row.usuario)) : 'Sistema'}</strong>
                    <small>${row.usuario ? escapeHtml(row.usuario.email || '') : ''}</small>
                </div>
                <div>
                    <span class="audit-label">IP</span>
                    <strong><code class="code-pill">${escapeHtml(row.ip || '—')}</code></strong>
                </div>
            </div>

            <div class="history-block">
                <h3>Cambios registrados</h3>
                ${cambios.length > 0 ? `
                    <div class="audit-diff">
                        <div class="audit-diff-head">
                            <span>Campo</span>
                            <span>Antes</span>
                            <span>Después</span>
                        </div>
                        ${cambios.map((cambio) => `
                            <div class="audit-diff-row">
                                <strong>${escapeHtml(cambio.campo)}</strong>
                                <code>${escapeHtml(formatValue(cambio.antes))}</code>
                                <code>${escapeHtml(formatValue(cambio.despues))}</code>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="field-hint">No hay diferencias de campos para este registro.</p>'}
            </div>

            ${(row.datos_antes || row.datos_despues) ? `
                <div class="history-block">
                    <h3>Datos completos</h3>
                    <div class="audit-payloads">
                        <div>
                            <span class="audit-label">Antes</span>
                            <pre>${escapeHtml(prettyJson(row.datos_antes))}</pre>
                        </div>
                        <div>
                            <span class="audit-label">Después</span>
                            <pre>${escapeHtml(prettyJson(row.datos_despues))}</pre>
                        </div>
                    </div>
                </div>
            ` : ''}
        `;
    };

    search?.addEventListener('input', debounce(() => {
        state.usuario = search.value.trim();
        state.page = 1;
        if (clear) clear.hidden = state.usuario === '';
        load();
    }, 280));

    clear?.addEventListener('click', () => {
        search.value = '';
        state.usuario = '';
        state.page = 1;
        clear.hidden = true;
        load();
    });

    accion?.addEventListener('change', () => {
        state.accion = accion.value;
        state.page = 1;
        load();
    });

    tabla?.addEventListener('change', () => {
        state.tabla = tabla.value;
        state.page = 1;
        load();
    });

    [desde, hasta].forEach((input) => {
        input?.addEventListener('change', () => {
            state.desde = desde?.value || '';
            state.hasta = hasta?.value || '';
            state.page = 1;
            load();
        });
    });

    $('[data-auditoria-refresh]')?.addEventListener('click', load);

    body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-auditoria-view]');

        if (button) {
            openDetalle(button.dataset.auditoriaView);
        }
    });

    load();
}

function formatFechaHora(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(String(value).replace(' ', 'T'));

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return `${formatDateNumeric(date)} ${date.toLocaleTimeString('es-BO', {
        hour: '2-digit',
        minute: '2-digit',
    })}`;
}

function formatValue(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

function prettyJson(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return JSON.stringify(value, null, 2);
}
