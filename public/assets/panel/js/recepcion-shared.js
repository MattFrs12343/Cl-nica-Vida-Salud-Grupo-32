import {
    $,
    $$,
    PANEL,
    apiFetch,
    apiErrorMessage,
    addDays,
    confirmAction,
    debounce,
    doctorName,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    initials,
    patientName,
    setLoading,
    statusBadge,
    toast,
    todayISO,
} from './app.js';

export { PANEL, todayISO, addDays, doctorName, patientName, statusBadge, initials };

export const ROLE = PANEL.role || document.body.dataset.role || '';
export const MEDICO_IDS = (PANEL.medicoIds || []).map(Number).filter((id) => id > 0);
export const IS_MEDICO_SCOPE = ROLE === 'recepcionista_medico';
export const IS_ADMIN = ['admin', 'super_admin'].includes(ROLE);

let medicosPromise = null;
let especialidadesPromise = null;

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

export async function getMedicos() {
    if (medicosPromise) {
        return medicosPromise;
    }

    medicosPromise = apiFetch('/api/public/medicos').then((response) => {
        let medicos = normalizeData(response);

        if (IS_MEDICO_SCOPE && MEDICO_IDS.length > 0) {
            medicos = medicos.filter((medico) => MEDICO_IDS.includes(Number(medico.id)));
        } else if (ROLE === 'medico' && PANEL.medicoId) {
            medicos = medicos.filter((medico) => Number(medico.id) === Number(PANEL.medicoId));
        }

        return medicos;
    });

    return medicosPromise;
}

export async function getEspecialidades() {
    if (especialidadesPromise) {
        return especialidadesPromise;
    }

    especialidadesPromise = apiFetch('/api/public/especialidades')
        .then((response) => normalizeData(response));

    return especialidadesPromise;
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
            item.nombre_completo || item.nombre || item.label || item[valueKey] || ''
        );
        select.appendChild(option);
    });

    if (current !== '') {
        select.value = current;
    }

    select.disabled = Boolean(disabled);
}

export function medicalOptionLabel(medico) {
    const specialties = (medico.especialidades || []).map((item) => item.nombre).filter(Boolean).join(', ');
    return specialties
        ? `${medico.nombre_completo || doctorName(medico)} · ${specialties}`
        : (medico.nombre_completo || doctorName(medico));
}

export function originLabel(origin) {
    return {
        web: 'Web',
        kiosco: 'Kiosco',
        recepcion: 'Recepción',
    }[origin] || origin || '—';
}

export function methodLabel(method) {
    return {
        efectivo: 'Efectivo',
        visa: 'Visa',
        mastercard: 'Mastercard',
    }[method] || method || '—';
}

export function summarizeCitas(citas) {
    const counts = {
        total: citas.length,
        programadas: 0,
        confirmadas: 0,
        atendidas: 0,
        canceladas: 0,
        inasistencias: 0,
    };

    citas.forEach((cita) => {
        if (Object.hasOwn(counts, cita.estado)) {
            counts[cita.estado] += 1;
        }
    });

    return counts;
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

/* Selector de paciente ---------------------------------------------------- */
export class PatientPicker {
    constructor({ search, results, selected, hidden, onSelect = null }) {
        this.search = search;
        this.results = results;
        this.selected = selected;
        this.hidden = hidden;
        this.onSelect = onSelect;
        this.items = [];
        this.patient = null;
        this.searchWrap = search?.closest('.input-wrap') || null;

        if (!this.search || !this.results || !this.selected || !this.hidden) {
            return;
        }

        this.search.addEventListener('input', debounce(() => this.searchPatients(this.search.value), 260));
        this.search.addEventListener('focus', () => {
            if (this.search.value.trim().length >= 2) {
                this.searchPatients(this.search.value);
            }
        });

        this.results.addEventListener('click', (event) => {
            const button = event.target.closest('[data-picker-id]');
            if (!button) {
                return;
            }

            const patient = this.items.find((item) => Number(item.id) === Number(button.dataset.pickerId));
            if (patient) {
                this.select(patient);
            }
        });

        document.addEventListener('click', (event) => {
            if (!this.results.hidden
                && !this.results.contains(event.target)
                && !this.search.contains(event.target)) {
                this.results.hidden = true;
            }
        });
    }

    async searchPatients(value) {
        const query = String(value || '').trim();

        if (query.length < 2) {
            this.results.hidden = true;
            this.results.innerHTML = '';
            return;
        }

        this.results.hidden = false;
        this.results.innerHTML = '<p class="picker-empty">Buscando pacientes…</p>';

        try {
            const response = await apiFetch(`/api/pacientes?q=${encodeURIComponent(query)}&per_page=8`);
            this.items = normalizeData(response);
            this.renderResults();
        } catch (error) {
            this.results.innerHTML = `<p class="picker-empty">${escapeHtml(apiErrorMessage(error))}</p>`;
        }
    }

    renderResults() {
        if (this.items.length === 0) {
            this.results.innerHTML = '<p class="picker-empty">No encontramos pacientes con ese dato.</p>';
            return;
        }

        this.results.innerHTML = this.items.map((patient) => `
            <button class="picker-result" type="button" data-picker-id="${escapeHtml(patient.id)}">
                <span class="avatar avatar-sm">${escapeHtml(initials(patientName(patient)))}</span>
                <span class="picker-result-copy">
                    <strong>${escapeHtml(patientName(patient))}</strong>
                    <small>CI ${escapeHtml(patient.ci || '—')}${patient.edad ? ` · ${escapeHtml(patient.edad)} años` : ''}</small>
                </span>
            </button>
        `).join('');
    }

    async selectById(id) {
        if (!id) {
            return null;
        }

        try {
            const response = await apiFetch(`/api/pacientes/${id}`);
            const patient = response?.data?.paciente || response?.data;
            if (patient && patient.id) {
                this.select(patient);
                return patient;
            }
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }

        return null;
    }

    select(patient) {
        this.patient = patient;
        this.hidden.value = String(patient?.id || '');
        this.results.hidden = true;
        this.results.innerHTML = '';

        if (this.searchWrap) {
            this.searchWrap.hidden = true;
        }

        if (patient) {
            this.selected.hidden = false;
            this.selected.innerHTML = `
                <span class="avatar avatar-sm">${escapeHtml(initials(patientName(patient)))}</span>
                <span class="picker-selected-copy">
                    <strong>${escapeHtml(patientName(patient))}</strong>
                    <small>CI ${escapeHtml(patient.ci || '—')}${patient.edad ? ` · ${escapeHtml(patient.edad)} años` : ''}</small>
                </span>
                <button class="picker-selected-clear" type="button" data-picker-clear aria-label="Cambiar paciente">
                    <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
                </button>
            `;

            this.selected.querySelector('[data-picker-clear]')?.addEventListener('click', () => this.clear());
        } else {
            this.selected.hidden = true;
            this.selected.innerHTML = '';
        }

        this.onSelect?.(patient);
    }

    clear() {
        this.patient = null;
        this.hidden.value = '';
        this.items = [];
        this.results.hidden = true;
        this.results.innerHTML = '';
        this.selected.hidden = true;
        this.selected.innerHTML = '';

        if (this.searchWrap) {
            this.searchWrap.hidden = false;
        }

        if (this.search) {
            this.search.value = '';
            this.search.focus({ preventScroll: true });
        }

        this.onSelect?.(null);
    }

    reset() {
        this.patient = null;
        this.hidden.value = '';
        this.items = [];
        this.results.hidden = true;
        this.results.innerHTML = '';
        this.selected.hidden = true;
        this.selected.innerHTML = '';
        if (this.searchWrap) {
            this.searchWrap.hidden = false;
        }
        if (this.search) {
            this.search.value = '';
        }
        this.onSelect?.(null);
    }
}

/* Slots ------------------------------------------------------------------- */
let slotToken = 0;

export async function loadSlots({
    container,
    hint,
    medicoId,
    fecha,
    horaInput,
    selected = '',
    onSelect = null,
}) {
    if (!container) {
        return;
    }

    const token = ++slotToken;
    container.dataset.slotToken = String(token);

    if (horaInput) {
        horaInput.value = '';
    }

    if (!medicoId || !fecha) {
        container.innerHTML = '<p class="slot-placeholder">Elige médico y fecha para ver horarios.</p>';
        if (hint) {
            hint.textContent = 'Elige médico y fecha';
        }
        return;
    }

    if (hint) {
        hint.textContent = 'Buscando cupos…';
    }

    container.innerHTML = '<p class="slot-placeholder">Buscando cupos disponibles…</p>';

    try {
        const response = await apiFetch(
            `/api/disponibilidad?medico=${encodeURIComponent(medicoId)}&fecha=${encodeURIComponent(fecha)}&canal=recepcion`
        );

        if (container.dataset.slotToken !== String(token)) {
            return;
        }

        const slots = Array.isArray(response?.data?.slots) ? response.data.slots : [];
        const available = slots.filter((slot) => slot.disponible !== false);

        if (available.length === 0) {
            container.innerHTML = '<p class="slot-placeholder">No hay cupos disponibles para esta fecha. Prueba con otro día.</p>';
            if (hint) {
                hint.textContent = 'Sin cupos';
            }
            return;
        }

        container.innerHTML = '';

        available.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'slot';
            button.dataset.hora = String(slot.hora || '').slice(0, 5);
            const free = Number(slot.cupos_libres ?? slot.cupo_restante ?? 1);
            button.innerHTML = `${escapeHtml(button.dataset.hora)}${free > 1 ? `<small>${free} cupos</small>` : ''}`;

            if (selected && button.dataset.hora === selected) {
                button.classList.add('is-selected');
                if (horaInput) {
                    horaInput.value = selected;
                }
            }

            button.addEventListener('click', () => {
                container.querySelectorAll('.slot').forEach((node) => node.classList.remove('is-selected'));
                button.classList.add('is-selected');
                if (horaInput) {
                    horaInput.value = button.dataset.hora;
                }
                onSelect?.(button.dataset.hora);
            });

            container.appendChild(button);
        });

        if (hint) {
            hint.textContent = `${available.length} horario${available.length === 1 ? '' : 's'} disponible${available.length === 1 ? '' : 's'}`;
        }
    } catch (error) {
        if (container.dataset.slotToken !== String(token)) {
            return;
        }

        container.innerHTML = `<p class="slot-placeholder">${escapeHtml(apiErrorMessage(error))}</p>`;
        if (hint) {
            hint.textContent = 'No se pudieron cargar los cupos';
        }
    }
}

/* Acciones de citas ------------------------------------------------------- */
export async function changeAttendance(cita, action) {
    const atendiendo = action === 'atender';
    const confirmed = await confirmAction({
        title: atendiendo ? 'Marcar como atendida' : 'Registrar inasistencia',
        message: atendiendo
            ? `¿Confirmas que ${patientName(cita.paciente)} fue atendido(a)?`
            : `¿Confirmas que ${patientName(cita.paciente)} no se presentó?`,
        confirmLabel: atendiendo ? 'Sí, atendida' : 'Sí, inasistencia',
        danger: !atendiendo,
    });

    if (!confirmed) {
        return false;
    }

    try {
        const response = await apiFetch(`/api/citas/${cita.id}/${atendiendo ? 'atender' : 'inasistencia'}`, {
            method: 'PATCH',
        });
        toast('success', response?.message || 'Cita actualizada.');
        return true;
    } catch (error) {
        toast('error', apiErrorMessage(error));
        return false;
    }
}

/* Reconsulta -------------------------------------------------------------- */
export class ReconsultaController {
    constructor({ onSuccess = null } = {}) {
        this.onSuccess = onSuccess;
        this.modal = document.getElementById('modal-reconsulta');
        this.form = this.modal?.querySelector('[data-reconsulta-form]') || null;
        this.origin = null;
        this.type = null;

        if (!this.modal || !this.form) {
            return;
        }

        this.info = this.modal.querySelector('[data-reconsulta-info]');
        this.typeLabel = this.modal.querySelector('[data-reconsulta-type-label]');
        this.typeHelp = this.modal.querySelector('[data-reconsulta-type-help]');
        this.typeInput = this.modal.querySelector('[data-reconsulta-tipo]');
        this.dateInput = this.modal.querySelector('[data-reconsulta-fecha]');
        this.slots = this.modal.querySelector('[data-reconsulta-slots]');
        this.slotHint = this.modal.querySelector('[data-reconsulta-slot-hint]');
        this.horaInput = this.modal.querySelector('[data-reconsulta-hora]');

        this.dateInput?.addEventListener('change', () => this.refreshSlots());

        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submit();
        });
    }

    open(cita) {
        if (!this.modal || !cita) {
            return;
        }

        this.origin = cita;
        this.type = cita.estado === 'atendida' ? 'seguimiento' : 'ausencia_medico';

        if (this.info) {
            this.info.innerHTML = `
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
        }

        if (this.typeLabel) {
            this.typeLabel.textContent = this.type === 'seguimiento'
                ? 'Reconsulta de seguimiento'
                : 'Reconsulta por ausencia del médico';
        }

        if (this.typeHelp) {
            this.typeHelp.textContent = this.type === 'seguimiento'
                ? 'La cita original ya fue atendida y se conserva en el historial.'
                : 'La cita original se cancelará y se creará una nueva fecha para el paciente.';
        }

        if (this.typeInput) {
            this.typeInput.value = this.type;
        }

        if (this.dateInput) {
            this.dateInput.value = addDays(todayISO(), 7);
        }

        if (this.horaInput) {
            this.horaInput.value = '';
        }

        this.form.reset();
        this.form.querySelector('[name="tipo"]').value = this.type;
        this.form.querySelector('[name="fecha"]').value = addDays(todayISO(), 7);

        this.refreshSlots();
        openModalSafe(this.modal);
    }

    refreshSlots() {
        loadSlots({
            container: this.slots,
            hint: this.slotHint,
            medicoId: this.origin?.medico_id,
            fecha: this.dateInput?.value,
            horaInput: this.horaInput,
        });
    }

    async submit() {
        if (!this.origin) {
            return;
        }

        const submitButton = this.form.querySelector('button[type="submit"]');
        const fecha = this.dateInput?.value;
        const hora = this.horaInput?.value;
        const motivo = this.form.querySelector('[name="motivo"]')?.value?.trim() || '';

        if (!fecha) {
            toast('error', 'Selecciona la nueva fecha.');
            this.dateInput?.focus();
            return;
        }

        if (!hora) {
            toast('error', 'Selecciona un horario disponible.');
            return;
        }

        setLoading(submitButton, true);

        try {
            const response = await apiFetch(`/api/citas/${this.origin.id}/reconsulta`, {
                method: 'POST',
                body: {
                    tipo: this.type,
                    fecha,
                    hora,
                    motivo: motivo || null,
                },
            });

            toast('success', response?.message || 'Reconsulta creada.');
            closeModalSafe(this.modal);
            this.onSuccess?.(response?.data);
        } catch (error) {
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submitButton, false);
        }
    }
}

/* Pequeños wrappers para no repetir imports en los módulos. */
export function openModalSafe(modal) {
    import('./app.js').then(({ openModal }) => openModal(modal));
}

export function closeModalSafe(modal) {
    import('./app.js').then(({ closeModal }) => closeModal(modal));
}
