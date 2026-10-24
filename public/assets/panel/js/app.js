/**
 * Utilidades compartidas del panel.
 *
 * Expone helpers de red, feedback, modales y formato. Las páginas importan
 * solo lo que necesitan; no hay estado global de negocio.
 */

const PANEL = window.PANEL || {};

export const $ = (selector, scope = document) => scope.querySelector(selector);
export const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

export class ApiError extends Error {
    constructor(message, status = 0, code = '', fields = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.fields = fields || {};
    }
}

/**
 * Cliente JSON de la API. Adjunta el token CSRF en mutaciones y traduce los
 * errores del contrato (`error.code`, `error.fields`) a una ApiError.
 */
export async function apiFetch(url, options = {}) {
    const {
        method = 'GET',
        body = null,
        headers = {},
        redirectOn401 = true,
        ...rest
    } = options;

    const requestHeaders = {
        Accept: 'application/json',
        ...headers,
    };

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())) {
        requestHeaders['X-CSRF-Token'] = csrfToken();
    }

    let payload = body;

    if (body !== null && typeof body === 'object' && !(body instanceof FormData)) {
        requestHeaders['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    let response;

    try {
        response = await fetch(url, {
            method,
            body: payload,
            headers: requestHeaders,
            credentials: 'same-origin',
            ...rest,
        });
    } catch (error) {
        throw new ApiError('No pudimos conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.', 0, 'NETWORK_ERROR');
    }

    let data = null;

    try {
        data = await response.json();
    } catch (error) {
        data = null;
    }

    if (response.status === 401 && redirectOn401) {
        const next = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.assign(`/login?next=${next}`);
        throw new ApiError('Tu sesión expiró. Vuelve a iniciar sesión.', 401, 'UNAUTHENTICATED');
    }

    if (!response.ok || data?.ok === false) {
        const error = data?.error || {};
        throw new ApiError(
            error.message || 'No pudimos completar la operación.',
            response.status,
            error.code || '',
            error.fields || {}
        );
    }

    return data ?? { ok: true, data: null };
}

export function apiErrorMessage(error) {
    if (error instanceof ApiError) {
        return error.message || 'No pudimos completar la operación.';
    }

    return 'Ocurrió un error inesperado. Inténtalo de nuevo.';
}

/* Feedback ---------------------------------------------------------------- */
const TOAST_TITLES = {
    success: 'Listo',
    error: 'No se pudo completar',
    info: 'Información',
    warning: 'Atención',
};

const TOAST_ICONS = {
    success: 'check-circle',
    error: 'x-circle',
    info: 'info',
    warning: 'alert',
};

export function toast(type, message, options = {}) {
    const stack = document.querySelector('[data-toast-stack]');

    if (!stack) {
        if (type === 'error') {
            console.error(message);
        }
        return;
    }

    const variant = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    const node = document.createElement('div');
    node.className = `toast toast-${variant}`;
    node.setAttribute('role', variant === 'error' ? 'alert' : 'status');
    node.innerHTML = `
        <span class="toast-icon" aria-hidden="true">
            <svg class="icon"><use href="#i-${TOAST_ICONS[variant]}"></use></svg>
        </span>
        <div class="toast-copy">
            <strong>${escapeHtml(options.title || TOAST_TITLES[variant])}</strong>
            <p>${escapeHtml(message || '')}</p>
        </div>
        <button class="toast-close" type="button" aria-label="Cerrar aviso">
            <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
        </button>
    `;

    const dismiss = () => {
        node.classList.add('is-leaving');
        window.setTimeout(() => node.remove(), 190);
    };

    node.querySelector('.toast-close')?.addEventListener('click', dismiss);
    stack.appendChild(node);

    const timeout = Number(options.timeout ?? (variant === 'error' ? 6500 : 4200));
    if (timeout > 0) {
        window.setTimeout(dismiss, timeout);
    }

    return node;
}

export function setLoading(button, loading) {
    if (!button) {
        return;
    }

    if (loading) {
        if (button.dataset.originalHtml === undefined) {
            button.dataset.originalHtml = button.innerHTML;
        }
        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Procesando…</span>';
        return;
    }

    button.disabled = false;
    button.classList.remove('is-loading');
    button.removeAttribute('aria-busy');

    if (button.dataset.originalHtml !== undefined) {
        button.innerHTML = button.dataset.originalHtml;
        delete button.dataset.originalHtml;
    }
}

/* Formularios ------------------------------------------------------------- */
export function clearFieldErrors(form) {
    if (!form) {
        return;
    }

    form.querySelectorAll('.field-error').forEach((node) => node.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    form.querySelectorAll('.field.has-error, .form-section.has-error').forEach((node) => {
        node.classList.remove('has-error');
    });
}

/**
 * Pinta los errores 422 del contrato debajo de cada campo y enfoca el primero.
 */
export function renderFieldErrors(form, fields = {}) {
    clearFieldErrors(form);

    if (!form || !fields || typeof fields !== 'object') {
        return;
    }

    let first = null;

    Object.entries(fields).forEach(([key, rawMessage]) => {
        const message = Array.isArray(rawMessage) ? rawMessage[0] : rawMessage;
        if (!message) {
            return;
        }

        const escapedKey = typeof CSS !== 'undefined' && CSS.escape
            ? CSS.escape(key)
            : String(key).replace(/["\\]/g, '\\$&');

        const input = form.querySelector(`[name="${escapedKey}"]`)
            || form.querySelector(`[data-field="${escapedKey}"]`);

        if (!input) {
            return;
        }

        input.setAttribute('aria-invalid', 'true');
        const container = input.closest('.field') || input.closest('.form-section') || input.parentElement;
        const error = document.createElement('p');
        error.className = 'field-error';
        error.textContent = String(message);
        container?.appendChild(error);
        container?.classList.add('has-error');
        first = first || input;
    });

    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.setTimeout(() => first.focus({ preventScroll: true }), 220);
    }
}

/* Modales ---------------------------------------------------------------- */
let lastFocused = null;

export function openModal(target) {
    const modal = typeof target === 'string' ? document.getElementById(target) : target;

    if (!modal) {
        return null;
    }

    lastFocused = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    window.setTimeout(() => {
        const focusable = modal.querySelector(
            'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [href]'
        );
        focusable?.focus({ preventScroll: true });
    }, 80);

    return modal;
}

export function closeModal(target) {
    const modal = typeof target === 'string' ? document.getElementById(target) : target;

    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    if (!document.querySelector('.modal.is-open')) {
        document.body.classList.remove('modal-open');
    }

    if (lastFocused && typeof lastFocused.focus === 'function') {
        lastFocused.focus({ preventScroll: true });
    }
}

export function closeAllModals() {
    document.querySelectorAll('.modal.is-open').forEach((modal) => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    });
    document.body.classList.remove('modal-open');
}

/**
 * Confirmación accesible con la misma estética del panel.
 *
 * @param {string|{title?: string, message?: string, confirmLabel?: string, cancelLabel?: string, danger?: boolean}} options
 * @returns {Promise<boolean>}
 */
export function confirmAction(options = {}) {
    const config = typeof options === 'string' ? { message: options } : options;
    const danger = config.danger !== false;

    return new Promise((resolve) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'modal is-open';
        wrapper.setAttribute('aria-hidden', 'false');
        wrapper.innerHTML = `
            <div class="modal-backdrop" data-confirm-cancel></div>
            <div class="modal-panel confirm-panel" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
                <div class="confirm-body">
                    <span class="confirm-icon" aria-hidden="true">
                        <svg class="icon"><use href="#i-alert"></use></svg>
                    </span>
                    <h2 id="confirm-title">${escapeHtml(config.title || '¿Confirmas esta acción?')}</h2>
                    <p id="confirm-message">${escapeHtml(config.message || 'Esta acción no se puede deshacer.')}</p>
                </div>
                <div class="confirm-foot">
                    <button class="btn btn-ghost btn-sm" type="button" data-confirm-cancel>
                        ${escapeHtml(config.cancelLabel || 'Volver')}
                    </button>
                    <button class="btn ${danger ? 'btn-danger' : 'btn-primary'} btn-sm" type="button" data-confirm-ok>
                        ${escapeHtml(config.confirmLabel || 'Confirmar')}
                    </button>
                </div>
            </div>
        `;

        const finish = (value) => {
            wrapper.remove();
            document.body.classList.remove('modal-open');
            document.removeEventListener('keydown', onKey);
            resolve(value);
        };

        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                finish(false);
            }
        };

        wrapper.querySelectorAll('[data-confirm-cancel]').forEach((node) => {
            node.addEventListener('click', () => finish(false));
        });

        wrapper.querySelector('[data-confirm-ok]')?.addEventListener('click', () => finish(true));

        document.body.appendChild(wrapper);
        document.body.classList.add('modal-open');
        document.addEventListener('keydown', onKey);
        window.setTimeout(() => wrapper.querySelector('[data-confirm-ok]')?.focus(), 60);
    });
}

/* Formato ----------------------------------------------------------------- */
const MONTHS_SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const MONTHS_LONG = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
];
const WEEKDAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

export function parseDateOnly(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }

    const raw = String(value);
    const date = new Date(raw.length <= 10 ? `${raw}T00:00:00` : raw);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function todayISO() {
    return PANEL.today || document.body.dataset.today || new Date().toISOString().slice(0, 10);
}

export function addDays(value, days) {
    const date = parseDateOnly(value) || new Date();
    date.setDate(date.getDate() + Number(days || 0));
    return date.toISOString().slice(0, 10);
}

export function formatDate(value, format = 'short') {
    const date = parseDateOnly(value);

    if (!date) {
        return '—';
    }

    if (format === 'long') {
        return `${WEEKDAYS[date.getDay()]}, ${date.getDate()} de ${MONTHS_LONG[date.getMonth()]} de ${date.getFullYear()}`;
    }

    if (format === 'month') {
        return `${MONTHS_LONG[date.getMonth()]} ${date.getFullYear()}`;
    }

    return `${String(date.getDate()).padStart(2, '0')} ${MONTHS_SHORT[date.getMonth()]} ${date.getFullYear()}`;
}

export function formatDateNumeric(value) {
    const date = parseDateOnly(value);

    if (!date) {
        return '—';
    }

    return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
}

export function formatDateTime(value) {
    const date = parseDateOnly(value);

    if (!date) {
        return '—';
    }

    return `${formatDateNumeric(date)} · ${formatTimeValue(date)}`;
}

export function formatTimeValue(value) {
    if (!value) {
        return '—';
    }

    if (value instanceof Date) {
        return `${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}`;
    }

    const raw = String(value);

    if (/^\d{4}-\d{2}-\d{2}[ T]/.test(raw)) {
        const date = new Date(raw.replace(' ', 'T'));

        if (!Number.isNaN(date.getTime())) {
            return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
        }
    }

    return raw.slice(0, 5);
}

export function formatMoney(value) {
    const amount = Number(value ?? 0);
    const formatted = new Intl.NumberFormat('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(amount) ? amount : 0);

    return `Bs ${formatted}`;
}

export function humanDate(value) {
    const date = parseDateOnly(value);
    const today = parseDateOnly(todayISO());

    if (!date || !today) {
        return formatDateNumeric(value);
    }

    const diff = Math.round((date - today) / 86400000);

    if (diff === 0) {
        return 'Hoy';
    }

    if (diff === 1) {
        return 'Mañana';
    }

    if (diff === -1) {
        return 'Ayer';
    }

    return formatDateNumeric(value);
}

export function initials(name) {
    return String(name || '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase() || '?';
}

export function statusMeta(status) {
    const map = {
        programada: { label: 'Programada', className: 'status-programada' },
        confirmada: { label: 'Confirmada', className: 'status-confirmada' },
        atendida: { label: 'Atendida', className: 'status-atendida' },
        cancelada: { label: 'Cancelada', className: 'status-cancelada' },
        inasistencia: { label: 'Inasistencia', className: 'status-inasistencia' },
        activo: { label: 'Activo', className: 'status-activo' },
        inactivo: { label: 'Inactivo', className: 'status-inactivo' },
        emitida: { label: 'Emitida', className: 'status-atendida' },
    };

    return map[status] || { label: status || '—', className: 'status-cancelada' };
}

export function statusBadge(status) {
    const meta = statusMeta(status);
    return `<span class="status ${meta.className}">${escapeHtml(meta.label)}</span>`;
}

export function debounce(fn, wait = 280) {
    let timer = null;

    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), wait);
    };
}

export function ageFromBirthdate(value) {
    const date = parseDateOnly(value);

    if (!date) {
        return null;
    }

    const today = new Date();
    let age = today.getFullYear() - date.getFullYear();
    const month = today.getMonth() - date.getMonth();

    if (month < 0 || (month === 0 && today.getDate() < date.getDate())) {
        age -= 1;
    }

    return Math.max(0, age);
}

export function sexLabel(sex) {
    return sex === 'femenino' ? 'Femenino' : (sex === 'masculino' ? 'Masculino' : '—');
}

export function patientName(patient) {
    if (!patient) {
        return 'Paciente';
    }

    return patient.nombre_completo
        || patient.nombre
        || [patient.nombres, patient.apellidos].filter(Boolean).join(' ')
        || 'Paciente';
}

export function doctorName(doctor) {
    if (!doctor) {
        return 'Médico';
    }

    return doctor.nombre_completo
        || doctor.nombre
        || [doctor.nombres, doctor.apellidos].filter(Boolean).join(' ')
        || 'Médico';
}

/* Inicialización global --------------------------------------------------- */
function initShell() {
    const sidebar = document.querySelector('[data-sidebar]');
    const openSidebar = () => {
        sidebar?.classList.add('is-open');
        document.body.classList.add('sidebar-open');
    };
    const closeSidebar = () => {
        sidebar?.classList.remove('is-open');
        document.body.classList.remove('sidebar-open');
    };

    document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
        button.addEventListener('click', openSidebar);
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', closeSidebar);
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 1160) {
            closeSidebar();
        }
    });

    document.addEventListener('click', (event) => {
        document.querySelectorAll('.user-menu[open]').forEach((details) => {
            if (!details.contains(event.target)) {
                details.removeAttribute('open');
            }
        });
    });

    document.querySelectorAll('[data-flash-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.panel-flash')?.remove());
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => closeModal(button.closest('.modal')));
    });

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const open = document.querySelector('.modal.is-open');
            if (open) {
                closeModal(open);
            }
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initShell);
} else {
    initShell();
}

export { PANEL };
