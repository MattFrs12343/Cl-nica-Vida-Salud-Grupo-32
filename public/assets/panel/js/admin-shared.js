import {
    $,
    PANEL,
    apiFetch,
    apiErrorMessage,
    confirmAction,
    escapeHtml,
    formatDateNumeric,
    initials,
    toast,
} from './app.js';

export { PANEL, confirmAction, escapeHtml, formatDateNumeric, initials, toast };

export const ROLE = PANEL.role || document.body.dataset.role || '';

export const ROLE_LABELS = {
    super_admin: 'Super administrador',
    admin: 'Administrador',
    recepcionista: 'Recepcionista',
    recepcionista_medico: 'Recepción médica',
    medico: 'Médico',
    paciente: 'Paciente',
};

export const ROLE_BADGES = {
    super_admin: 'badge-pine',
    admin: 'badge-pine',
    recepcionista: 'badge-info',
    recepcionista_medico: 'badge-info',
    medico: 'badge-accent',
    paciente: 'badge-muted',
};

export const DIAS = [
    'Domingo',
    'Lunes',
    'Martes',
    'Miércoles',
    'Jueves',
    'Viernes',
    'Sábado',
];

export function roleLabel(role) {
    return ROLE_LABELS[role] || role || '—';
}

export function roleBadge(role) {
    return `<span class="badge ${ROLE_BADGES[role] || 'badge-muted'}">${escapeHtml(roleLabel(role))}</span>`;
}

export function estadoBadge(activo, bloqueado = false) {
    if (bloqueado) {
        return '<span class="status status-cancelada">Bloqueado</span>';
    }

    return activo
        ? '<span class="status status-activo">Activo</span>'
        : '<span class="status status-inactivo">Inactivo</span>';
}

export function normalizeData(response) {
    if (Array.isArray(response)) {
        return response;
    }

    if (Array.isArray(response?.data)) {
        return response.data;
    }

    if (Array.isArray(response?.data?.data)) {
        return response.data.data;
    }

    return [];
}

export function metaOf(response) {
    return response?.meta || response?.data?.meta || {};
}

export function emptyStateHtml({ icon = 'search', title, message = '', action = '' }) {
    return `
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true">
                <svg class="icon"><use href="#i-${escapeHtml(icon)}"></use></svg>
            </span>
            <h2>${escapeHtml(title)}</h2>
            ${message ? `<p>${escapeHtml(message)}</p>` : ''}
            ${action}
        </div>
    `;
}

export function errorStateHtml({ message, retryLabel = 'Reintentar' }) {
    return `
        <div class="error-state">
            <span class="error-icon" aria-hidden="true">
                <svg class="icon"><use href="#i-alert"></use></svg>
            </span>
            <h2>No pudimos cargar la información</h2>
            <p>${escapeHtml(message)}</p>
            <button class="btn btn-ghost btn-sm" type="button" data-retry>
                ${escapeHtml(retryLabel)}
                <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
            </button>
        </div>
    `;
}

export function renderLoadError(container, error, onRetry) {
    if (!container) {
        return;
    }

    container.innerHTML = errorStateHtml({ message: apiErrorMessage(error) });
    container.querySelector('[data-retry]')?.addEventListener('click', onRetry);
}

export function renderPagination(container, meta, onPage) {
    if (!container) {
        return;
    }

    const page = Number(meta?.page || 1);
    const perPage = Number(meta?.per_page || 0);
    const total = Number(meta?.total || 0);
    const totalPages = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;

    if (total <= perPage && page <= 1) {
        container.hidden = true;
        container.innerHTML = '';
        return;
    }

    const from = total === 0 ? 0 : (page - 1) * perPage + 1;
    const to = Math.min(total, page * perPage);

    container.hidden = false;
    container.innerHTML = `
        <span class="pagination-info">Mostrando ${from}–${to} de ${total}</span>
        <div class="pagination-actions">
            <button class="pagination-btn" type="button" data-page-prev ${page <= 1 ? 'disabled' : ''}>
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
                Anterior
            </button>
            <span class="pagination-page">${page} / ${totalPages}</span>
            <button class="pagination-btn" type="button" data-page-next ${page >= totalPages ? 'disabled' : ''}>
                Siguiente
                <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
        </div>
    `;

    container.querySelector('[data-page-prev]')?.addEventListener('click', () => {
        if (page > 1) {
            onPage(page - 1);
        }
    });

    container.querySelector('[data-page-next]')?.addEventListener('click', () => {
        if (page < totalPages) {
            onPage(page + 1);
        }
    });
}

export function fillSelect(select, items, options = {}) {
    if (!select) {
        return;
    }

    const {
        placeholder = 'Selecciona una opción',
        selected = '',
        valueKey = 'id',
        label = null,
        disabled = false,
    } = options;

    const current = String(selected ?? select.value ?? '');
    const placeholderOption = document.createElement('option');
    placeholderOption.value = '';
    placeholderOption.textContent = placeholder;
    select.replaceChildren(placeholderOption);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item[valueKey] ?? '');
        option.textContent = label ? label(item) : String(
            item.nombre_completo || item.nombre || item.username || item.label || item[valueKey] || ''
        );
        select.appendChild(option);
    });

    if (current !== '') {
        select.value = current;
    }

    select.disabled = Boolean(disabled);
}

export function medicoNombre(medico) {
    return medico?.nombre_completo
        || [medico?.nombres, medico?.apellidos].filter(Boolean).join(' ')
        || medico?.username
        || 'Médico';
}

export function medicoOptionLabel(medico) {
    const nombre = medicoNombre(medico);
    return medico?.codigo ? `${nombre} · ${medico.codigo}` : nombre;
}

export function personaNombre(persona) {
    return persona?.nombre_completo
        || persona?.username
        || [persona?.nombres, persona?.apellidos].filter(Boolean).join(' ')
        || 'Usuario';
}

export function fechaLegible(value) {
    return formatDateNumeric(value);
}

export function horaRango(inicio, fin) {
    return `${String(inicio || '').slice(0, 5) || '—'} – ${String(fin || '').slice(0, 5) || '—'}`;
}

let medicosPromise = null;
let especialidadesPromise = null;
let recepcionistasPromise = null;

export async function loadMedicos(force = false) {
    if (medicosPromise && !force) {
        return medicosPromise;
    }

    medicosPromise = apiFetch('/api/medicos?per_page=100')
        .then((response) => normalizeData(response));

    return medicosPromise;
}

export async function loadEspecialidades(force = false) {
    if (especialidadesPromise && !force) {
        return especialidadesPromise;
    }

    especialidadesPromise = apiFetch('/api/especialidades?per_page=100')
        .then((response) => normalizeData(response));

    return especialidadesPromise;
}

export async function loadRecepcionistas(force = false) {
    if (recepcionistasPromise && !force) {
        return recepcionistasPromise;
    }

    recepcionistasPromise = apiFetch('/api/usuarios?rol=recepcionista_medico&per_page=100')
        .then((response) => normalizeData(response));

    return recepcionistasPromise;
}

export function renderSpecialtyCheckboxes(container, especialidades, selectedIds = []) {
    if (!container) {
        return;
    }

    const selected = new Set(selectedIds.map(Number));
    const activas = especialidades.filter((item) => item.estado === 'activo');

    if (activas.length === 0) {
        container.innerHTML = '<p class="field-hint">No hay especialidades activas. Crea una primero.</p>';
        return;
    }

    container.innerHTML = activas.map((especialidad) => `
        <label class="check-card">
            <input type="checkbox" name="especialidades[]" value="${escapeHtml(especialidad.id)}"
                   ${selected.has(Number(especialidad.id)) ? 'checked' : ''}>
            <span class="check-card-box" aria-hidden="true">
                <svg class="icon"><use href="#i-check"></use></svg>
            </span>
            <span>
                <strong>${escapeHtml(especialidad.nombre)}</strong>
                <small>${escapeHtml(especialidad.medicos_count ?? 0)} médico(s)</small>
            </span>
        </label>
    `).join('');
}

export function checkedValues(container, name) {
    if (!container) {
        return [];
    }

    return Array.from(container.querySelectorAll(`input[name="${name}"]:checked`))
        .map((input) => Number(input.value))
        .filter((value) => value > 0);
}

export function downloadCsv(filename, rows) {
    const escapeCell = (value) => {
        const text = String(value ?? '').replaceAll('"', '""');
        return `"${text}"`;
    };

    const csv = rows.map((row) => row.map(escapeCell).join(';')).join('\r\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

export function setText(selector, value) {
    const node = $(selector);
    if (node) {
        node.textContent = String(value ?? '—');
    }
}
