import {
    $,
    $$,
    addDays,
    apiErrorMessage,
    apiFetch,
    doctorName,
    escapeHtml,
    formatDate,
    setLoading,
    toast,
    todayISO,
} from './app.js';
import {
    renderAgendaList,
    requireMedicoId,
    weekRange,
} from './medico-shared.js';

const dateInput = $('[data-agenda-fecha]');
const list = $('[data-agenda-list]');
const count = $('[data-agenda-count]');
const dateLabel = $('[data-agenda-date-label]');
const status = $('[data-agenda-status]');
const estadoSelect = $('[data-agenda-estado]');
const refresh = $('[data-agenda-refresh]');
const kpiRoot = $('[data-agenda-kpis]');

let mode = 'day';
let currentDate = dateInput?.value || todayISO();
let currentEstado = estadoSelect?.value || '';

function setMode(nextMode) {
    mode = nextMode;
    $$('[data-agenda-mode]').forEach((button) => {
        const active = button.dataset.agendaMode === mode;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    updateDateLabel();
}

function range() {
    return mode === 'week' ? weekRange(currentDate) : { desde: currentDate, hasta: currentDate };
}

function updateDateLabel() {
    if (!dateLabel) {
        return;
    }

    if (mode === 'week') {
        const { desde, hasta } = range();
        dateLabel.textContent = `${formatDate(desde)} – ${formatDate(hasta)}`;
        return;
    }

    dateLabel.textContent = formatDate(currentDate, 'long');
}

function renderKpis(contadores, doctor) {
    if (!kpiRoot) {
        return;
    }

    const values = {
        total: contadores?.total ?? 0,
        programadas: contadores?.programadas ?? 0,
        confirmadas: contadores?.confirmadas ?? 0,
        atendidas: contadores?.atendidas ?? 0,
        inasistencias: contadores?.inasistencias ?? 0,
    };

    Object.entries(values).forEach(([key, value]) => {
        const node = kpiRoot.querySelector(`[data-agenda-kpi="${key}"]`);
        if (node) {
            node.textContent = String(value);
        }
    });

    kpiRoot.querySelectorAll('[data-agenda-doctor]').forEach((node) => {
        node.textContent = doctor ? doctorName(doctor) : 'Mi agenda';
    });
}

async function load() {
    if (!requireMedicoId()) {
        if (list) {
            list.innerHTML = '<div class="agenda-empty"><p>No hay un médico asociado a tu cuenta.</p></div>';
        }
        return;
    }

    const { desde, hasta } = range();
    const params = new URLSearchParams();

    if (mode === 'week') {
        params.set('desde', desde);
        params.set('hasta', hasta);
    } else {
        params.set('fecha', desde);
    }

    if (currentEstado) {
        params.set('estado', currentEstado);
    }

    setLoading(refresh, true);
    if (status) {
        status.textContent = 'Actualizando…';
    }

    try {
        const response = await apiFetch(`/api/medicos/${requireMedicoId()}/agenda?${params.toString()}`);
        const data = response?.data || {};
        const citas = data.citas || [];

        updateDateLabel();
        renderKpis(data.contadores || {}, data.medico);
        renderAgendaList(list, citas, {
            showDate: mode === 'week',
            emptyMessage: mode === 'week'
                ? 'No hay citas en esta semana.'
                : 'No hay citas para este día.',
            onChanged: load,
        });

        if (count) {
            count.textContent = `${citas.length} cita${citas.length === 1 ? '' : 's'}`;
        }

        if (status) {
            status.textContent = currentEstado
                ? `Filtro: ${estadoSelect?.selectedOptions?.[0]?.textContent || currentEstado}`
                : 'Agenda actualizada';
        }
    } catch (error) {
        const message = apiErrorMessage(error);
        if (list) {
            list.innerHTML = `
                <div class="error-state">
                    <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <h2>No pudimos cargar la agenda</h2>
                    <p>${escapeHtml(message)}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-agenda-retry>
                        Reintentar
                        <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    </button>
                </div>
            `;
            list.querySelector('[data-agenda-retry]')?.addEventListener('click', load);
        }
        if (status) {
            status.textContent = 'Sin conexión';
        }
        toast('error', message);
    } finally {
        setLoading(refresh, false);
    }
}

$$('[data-agenda-mode]').forEach((button) => {
    button.addEventListener('click', () => {
        setMode(button.dataset.agendaMode === 'week' ? 'week' : 'day');
        load();
    });
});

dateInput?.addEventListener('change', () => {
    currentDate = dateInput.value || todayISO();
    load();
});

estadoSelect?.addEventListener('change', () => {
    currentEstado = estadoSelect.value;
    load();
});

$('[data-agenda-prev]')?.addEventListener('click', () => {
    currentDate = addDays(currentDate, mode === 'week' ? -7 : -1);
    if (dateInput) {
        dateInput.value = currentDate;
    }
    load();
});

$('[data-agenda-next]')?.addEventListener('click', () => {
    currentDate = addDays(currentDate, mode === 'week' ? 7 : 1);
    if (dateInput) {
        dateInput.value = currentDate;
    }
    load();
});

$('[data-agenda-today]')?.addEventListener('click', () => {
    currentDate = todayISO();
    if (dateInput) {
        dateInput.value = currentDate;
    }
    load();
});

refresh?.addEventListener('click', load);

setMode('day');
load();
