import {
    $,
    apiErrorMessage,
    escapeHtml,
    toast,
} from './app.js';
import {
    loadHistory,
    renderHistoryList,
    renderPatientSummary,
    setText,
} from './paciente-shared.js';

const elements = {
    list: $('[data-historial-list]'),
    count: $('[data-historial-count]'),
    profile: $('[data-historial-profile]'),
    refresh: $('[data-historial-refresh]'),
};

let loading = false;

async function load() {
    if (loading) {
        return;
    }

    loading = true;
    if (elements.refresh) {
        elements.refresh.disabled = true;
    }

    try {
        const { paciente, consultas } = await loadHistory();

        setText(elements.count, `${consultas.length} ${consultas.length === 1 ? 'atención' : 'atenciones'}`);

        if (paciente) {
            renderPatientSummary(elements.profile, paciente);
        } else if (elements.profile) {
            elements.profile.innerHTML = '<p class="text-muted">No pudimos cargar tu ficha en este momento.</p>';
        }

        renderHistoryList(elements.list, consultas);
    } catch (error) {
        toast('error', apiErrorMessage(error));
        if (elements.list) {
            elements.list.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span>
                    <h2>No pudimos cargar tu historial</h2>
                    <p>${escapeHtml(apiErrorMessage(error))}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-historial-retry>Reintentar</button>
                </div>
            `;
            elements.list.querySelector('[data-historial-retry]')?.addEventListener('click', load);
        }
    } finally {
        loading = false;
        if (elements.refresh) {
            elements.refresh.disabled = false;
        }
    }
}

elements.refresh?.addEventListener('click', load);

load();
