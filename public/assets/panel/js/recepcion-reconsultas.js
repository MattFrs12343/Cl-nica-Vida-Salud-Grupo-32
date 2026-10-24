import {
    $,
    $$,
    apiFetch,
    apiErrorMessage,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    humanDate,
    initials,
    todayISO,
} from './app.js';
import {
    ReconsultaController,
    doctorName,
    emptyStateHtml,
    originLabel,
    patientName,
    renderLoadError,
    statusBadge,
} from './recepcion-shared.js';

export default function initReconsultas() {
    const state = {
        tab: 'seguimiento',
        fecha: '',
        citas: [],
    };

    const dateInput = $('[data-reconsultas-fecha]');
    const list = $('[data-reconsultas-list]');
    const total = $('[data-reconsultas-total]');
    const title = $('[data-reconsultas-title]');
    const dateLabel = $('[data-reconsultas-date-label]');

    state.fecha = dateInput?.value || todayISO();

    const reconsulta = new ReconsultaController({
        onSuccess: () => load(),
    });

    const load = async () => {
        list.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 4 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

        try {
            const response = await apiFetch(
                `/api/citas?fecha=${encodeURIComponent(state.fecha)}&per_page=100&sort=hora&direction=asc`
            );
            state.citas = Array.isArray(response?.data) ? response.data : [];

            renderCounts();
            renderList();

            if (dateLabel) {
                dateLabel.textContent = formatDateNumeric(state.fecha);
            }
        } catch (error) {
            renderLoadError(list, error, load);
            if (total) {
                total.textContent = 'Error de carga';
            }
        }
    };

    const eligibleFor = (tab) => {
        if (tab === 'seguimiento') {
            return state.citas.filter((cita) => cita.estado === 'atendida');
        }

        return state.citas.filter((cita) => ['programada', 'confirmada'].includes(cita.estado));
    };

    const renderCounts = () => {
        const seguimiento = eligibleFor('seguimiento').length;
        const ausencia = eligibleFor('ausencia_medico').length;

        const seguimientoNode = document.querySelector('[data-reconsultas-count="seguimiento"]');
        const ausenciaNode = document.querySelector('[data-reconsultas-count="ausencia_medico"]');

        if (seguimientoNode) seguimientoNode.textContent = String(seguimiento);
        if (ausenciaNode) ausenciaNode.textContent = String(ausencia);

        if (total) {
            const count = state.tab === 'seguimiento' ? seguimiento : ausencia;
            total.textContent = `${count} cita${count === 1 ? '' : 's'} elegible${count === 1 ? '' : 's'}`;
        }
    };

    const renderList = () => {
        const citas = eligibleFor(state.tab);

        if (title) {
            title.textContent = state.tab === 'seguimiento' ? 'Seguimiento' : 'Ausencia del médico';
        }

        if (citas.length === 0) {
            list.innerHTML = emptyStateHtml({
                icon: 'refresh',
                title: state.tab === 'seguimiento' ? 'Sin atenciones para seguimiento' : 'Sin ausencias por resolver',
                message: state.tab === 'seguimiento'
                    ? 'Las citas atendidas de esta fecha aparecerán aquí para agendar su seguimiento.'
                    : 'Las citas programadas o confirmadas de esta fecha aparecerán aquí si el médico no puede atender.',
            });
            return;
        }

        list.innerHTML = citas.map((cita) => `
            <article class="reconsulta-card">
                <span class="avatar">${escapeHtml(initials(patientName(cita.paciente)))}</span>
                <div class="reconsulta-card-main">
                    <div class="reconsulta-card-top">
                        <strong>${escapeHtml(patientName(cita.paciente))}</strong>
                        ${statusBadge(cita.estado)}
                        ${cita.es_reconsulta ? '<span class="badge-reconsulta">Reconsulta</span>' : ''}
                    </div>
                    <p>${escapeHtml(doctorName(cita.medico))} · ${escapeHtml(cita.especialidad?.nombre || 'Consulta')}</p>
                    <div class="reconsulta-card-meta">
                        <span>${escapeHtml(formatTimeValue(cita.hora_inicio))}–${escapeHtml(formatTimeValue(cita.hora_fin))}</span>
                        <span>${escapeHtml(cita.codigo || '')}</span>
                        <span>${escapeHtml(originLabel(cita.origen))}</span>
                        ${cita.motivo ? `<span>${escapeHtml(cita.motivo)}</span>` : ''}
                    </div>
                </div>
                <button class="btn btn-ghost btn-sm" type="button" data-reconsulta-open data-cita-id="${escapeHtml(cita.id)}">
                    <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    Crear reconsulta
                </button>
            </article>
        `).join('');
    };

    list?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-reconsulta-open]');
        if (!button) {
            return;
        }

        const cita = state.citas.find((item) => Number(item.id) === Number(button.dataset.citaId));
        if (cita) {
            reconsulta.open(cita);
        }
    });

    $$('[data-reconsultas-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            state.tab = button.dataset.reconsultasTab;
            $$('[data-reconsultas-tab]').forEach((item) => {
                const active = item === button;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            renderCounts();
            renderList();
        });
    });

    dateInput?.addEventListener('change', () => {
        state.fecha = dateInput.value || todayISO();
        load();
    });

    $('[data-reconsultas-refresh]')?.addEventListener('click', load);

    load();
}
