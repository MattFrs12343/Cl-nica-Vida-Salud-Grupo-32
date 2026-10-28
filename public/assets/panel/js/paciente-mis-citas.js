import {
    $,
    $$,
    apiErrorMessage,
    escapeHtml,
    toast,
} from './app.js';
import {
    isPast,
    isUpcoming,
    loadAppointments,
    renderAppointmentList,
    setText,
    sortAppointments,
} from './paciente-shared.js';

const elements = {
    list: $('[data-citas-list]'),
    count: $('[data-citas-count]'),
    total: $('[data-citas-total]'),
    proximas: $('[data-citas-proximas]'),
    pasadas: $('[data-citas-pasadas]'),
    refresh: $('[data-citas-refresh]'),
};

const validFilters = ['proximas', 'pasadas', 'todas'];

let citas = [];
let filter = new URLSearchParams(window.location.search).get('filtro') || 'proximas';
let loading = false;

if (!validFilters.includes(filter)) {
    filter = 'proximas';
}

function updateFilterButtons() {
    $$('[data-citas-filter]').forEach((button) => {
        const active = button.dataset.citasFilter === filter;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
        button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
}

function filteredAppointments() {
    if (filter === 'pasadas') {
        return sortAppointments(citas.filter(isPast)).reverse();
    }

    if (filter === 'todas') {
        return sortAppointments(citas);
    }

    return sortAppointments(citas.filter(isUpcoming));
}

function render() {
    const proximas = citas.filter(isUpcoming);
    const pasadas = citas.filter(isPast);
    const visible = filteredAppointments();

    setText(elements.total, citas.length);
    setText(elements.proximas, proximas.length);
    setText(elements.pasadas, pasadas.length);
    setText(elements.count, `${visible.length} ${visible.length === 1 ? 'cita' : 'citas'}`);

    const emptyMessages = {
        proximas: 'No tienes citas próximas. Puedes reservar una nueva cuando lo necesites.',
        pasadas: 'Todavía no tienes citas anteriores.',
        todas: 'Aún no tienes citas registradas.',
    };

    renderAppointmentList(elements.list, visible, {
        emptyMessage: emptyMessages[filter],
        allowCancel: filter !== 'pasadas',
        onChanged: load,
    });

    updateFilterButtons();
}

async function load() {
    if (loading) {
        return;
    }

    loading = true;
    if (elements.refresh) {
        elements.refresh.disabled = true;
    }

    try {
        const result = await loadAppointments();
        citas = result.citas;
        render();
    } catch (error) {
        toast('error', apiErrorMessage(error));
        if (elements.list) {
            elements.list.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span>
                    <h2>No pudimos cargar tus citas</h2>
                    <p>${escapeHtml(apiErrorMessage(error))}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-citas-retry>Reintentar</button>
                </div>
            `;
            elements.list.querySelector('[data-citas-retry]')?.addEventListener('click', load);
        }
    } finally {
        loading = false;
        if (elements.refresh) {
            elements.refresh.disabled = false;
        }
    }
}

$$('[data-citas-filter]').forEach((button) => {
    button.addEventListener('click', () => {
        const next = button.dataset.citasFilter;
        if (!validFilters.includes(next)) {
            return;
        }

        filter = next;
        const url = new URL(window.location.href);
        if (filter === 'proximas') {
            url.searchParams.delete('filtro');
        } else {
            url.searchParams.set('filtro', filter);
        }
        window.history.replaceState({}, '', url);
        render();
    });
});

elements.refresh?.addEventListener('click', load);

load();
