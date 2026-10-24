import {
    $,
    apiFetch,
    apiErrorMessage,
    closeModal,
    debounce,
    escapeHtml,
    formatDateNumeric,
    formatDateTime,
    formatTimeValue,
    initials,
    openModal,
    setLoading,
    toast,
} from './app.js';
import {
    emptyStateHtml,
    errorStateHtml,
    methodLabel,
    patientName as sharedPatientName,
    renderLoadError,
    renderPagination,
    statusBadge,
} from './recepcion-shared.js';

export default function initFichas() {
    const state = {
        q: '',
        fecha: '',
        page: 1,
        perPage: 15,
        rows: [],
        meta: {},
        current: null,
    };

    const search = $('[data-ficha-search]');
    const clear = $('[data-ficha-clear]');
    const dateInput = $('[data-ficha-fecha]');
    const table = $('[data-fichas-table]');
    const pagination = $('[data-ficha-pagination]');
    const count = $('[data-ficha-count]');
    const dayLabel = $('[data-ficha-day-label]');
    const modal = $('#modal-ficha');
    const detail = $('[data-ficha-detail]');
    const modalTitle = $('[data-ficha-modal-title]');
    const reprints = $('[data-ficha-reprints]');
    const printButton = $('[data-ficha-print]');

    const load = async () => {
        table.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 6 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

        try {
            const params = new URLSearchParams({
                page: String(state.page),
                per_page: String(state.perPage),
            });

            if (state.q) params.set('q', state.q);
            if (state.fecha) params.set('fecha', state.fecha);

            const response = await apiFetch(`/api/fichas?${params.toString()}`);
            state.rows = Array.isArray(response?.data) ? response.data : [];
            state.meta = response?.meta || {};

            renderRows();
            renderPagination(pagination, state.meta, (page) => {
                state.page = page;
                load();
            });

            if (count) {
                count.textContent = `${state.meta.total ?? state.rows.length} ficha${Number(state.meta.total ?? state.rows.length) === 1 ? '' : 's'}`;
            }

            if (dayLabel) {
                dayLabel.textContent = state.fecha ? formatDateNumeric(state.fecha) : 'Todas las fechas';
            }
        } catch (error) {
            renderLoadError(table, error, load);
            if (count) {
                count.textContent = 'Error de carga';
            }
        }
    };

    const renderRows = () => {
        if (state.rows.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'ticket',
                title: 'Sin fichas para mostrar',
                message: state.q || state.fecha
                    ? 'No encontramos fichas con esos criterios. Revisa el código, la CI o la fecha.'
                    : 'Las fichas se emiten en el kiosco. Cuando un paciente genere una, aparecerá aquí.',
            });
            return;
        }

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ficha</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Cita</th>
                        <th>Canal</th>
                        <th>Emitida</th>
                        <th><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    ${state.rows.map((ficha) => `
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <span class="cell-code">${escapeHtml(ficha.codigo || '—')}</span>
                                    <small>${escapeHtml(ficha.cita_codigo || '')}</small>
                                </div>
                            </td>
                            <td>
                                <div class="cell-person">
                                    <span class="avatar avatar-sm">${escapeHtml(initials(sharedPatientName(ficha.paciente)))}</span>
                                    <div>
                                        <strong>${escapeHtml(sharedPatientName(ficha.paciente))}</strong>
                                        <small>${escapeHtml(ficha.paciente?.ci || '')}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <strong>${escapeHtml(ficha.medico?.nombre_completo || '—')}</strong>
                                    <small>${escapeHtml(ficha.especialidad?.nombre || '')}</small>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <strong>${escapeHtml(ficha.fecha ? formatDateNumeric(ficha.fecha) : '—')}</strong>
                                    <small>${escapeHtml(formatTimeValue(ficha.hora_inicio))}</small>
                                </div>
                            </td>
                            <td><span class="badge badge-muted">${escapeHtml(channelLabel(ficha.canal))}</span></td>
                            <td>${escapeHtml(formatDateTime(ficha.emitida_at))}</td>
                            <td>
                                <div class="row-actions">
                                    <button class="row-action" type="button" title="Ver ficha"
                                            aria-label="Ver ficha" data-ficha-open="${escapeHtml(ficha.codigo)}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-eye"></use></svg>
                                    </button>
                                    <button class="row-action" type="button" title="Reimprimir"
                                            aria-label="Reimprimir ficha" data-ficha-reprint="${escapeHtml(ficha.codigo)}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-print"></use></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    };

    const openFicha = async (codigo) => {
        state.current = state.rows.find((item) => item.codigo === codigo) || null;
        detail.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-block"></div>
                <div class="skeleton skeleton-row"></div>
            </div>
        `;

        if (modalTitle) {
            modalTitle.textContent = codigo;
        }

        openModal(modal);

        try {
            const response = await apiFetch(`/api/fichas/${encodeURIComponent(codigo)}`);
            const ficha = response?.data || state.current;
            state.current = ficha;
            renderDetail(ficha);
        } catch (error) {
            detail.innerHTML = errorStateHtml({ message: apiErrorMessage(error) });
        }
    };

    const renderDetail = (ficha) => {
        if (!ficha) {
            detail.innerHTML = errorStateHtml({ message: 'No encontramos la ficha.' });
            return;
        }

        if (modalTitle) {
            modalTitle.textContent = ficha.codigo || 'Detalle de la ficha';
        }

        detail.innerHTML = `
            <div class="ficha-ticket">
                ${ticketMarkup(ficha)}
            </div>
            <div class="ficha-summary">
                <div class="ficha-summary-item">
                    <span>Canal de emisión</span>
                    <strong>${escapeHtml(channelLabel(ficha.canal))}</strong>
                </div>
                <div class="ficha-summary-item">
                    <span>Emitida</span>
                    <strong>${escapeHtml(formatDateTime(ficha.emitida_at))}</strong>
                </div>
                <div class="ficha-summary-item">
                    <span>Código de acceso</span>
                    <strong>${escapeHtml(ficha.codigo_acceso || '—')}</strong>
                </div>
                <div class="ficha-summary-item">
                    <span>Estado de la cita</span>
                    <strong>${ficha.cita_estado ? statusBadge(ficha.cita_estado) : '—'}</strong>
                </div>
            </div>
        `;

        if (reprints) {
            const total = Number(ficha.reimpresiones || 0);
            reprints.textContent = total === 0
                ? 'Ficha original · sin reimpresiones'
                : `${total} reimpresión${total === 1 ? '' : 'es'} registrada${total === 1 ? '' : 's'}`;
        }
    };

    const ticketMarkup = (ficha) => `
        <div class="ticket-head">
            <div class="ticket-brand">
                <span class="ticket-brand-mark">v</span>
                <div>
                    <strong>Clínica Vida Salud</strong>
                    <small>FICHA DE ATENCIÓN</small>
                </div>
            </div>
            <div class="ticket-code">
                <strong>${escapeHtml(ficha.codigo || '—')}</strong>
                <small>${escapeHtml(channelLabel(ficha.canal))}</small>
            </div>
        </div>
        <div class="ticket-body">
            <div class="ticket-patient">
                <span class="avatar avatar-sm">${escapeHtml(initials(sharedPatientName(ficha.paciente)))}</span>
                <div class="ticket-patient-copy">
                    <strong>${escapeHtml(sharedPatientName(ficha.paciente))}</strong>
                    <small>CI ${escapeHtml(ficha.paciente?.ci || '—')} · ${escapeHtml(ficha.paciente?.codigo || '')}</small>
                </div>
            </div>
            <div class="ticket-grid">
                <div class="ticket-cell">
                    <span>Fecha</span>
                    <strong>${escapeHtml(ficha.fecha ? formatDateNumeric(ficha.fecha) : '—')}</strong>
                </div>
                <div class="ticket-cell">
                    <span>Hora</span>
                    <strong>${escapeHtml(formatTimeValue(ficha.hora_inicio))}</strong>
                </div>
                <div class="ticket-cell">
                    <span>Médico</span>
                    <strong>${escapeHtml(ficha.medico?.nombre_completo || '—')}</strong>
                </div>
                <div class="ticket-cell">
                    <span>Especialidad</span>
                    <strong>${escapeHtml(ficha.especialidad?.nombre || '—')}</strong>
                </div>
            </div>
            <div class="ticket-access">
                <div class="ticket-access-copy">
                    <span>Código de acceso</span>
                    <strong>Preséntalo en consultorio</strong>
                </div>
                <div class="ticket-access-code">${escapeHtml(ficha.codigo_acceso || '••••')}</div>
            </div>
        </div>
        <div class="ticket-foot">
            <span>Emitida ${escapeHtml(formatDateTime(ficha.emitida_at))}</span>
            <span class="ticket-bars" aria-hidden="true">${bars()}</span>
        </div>
    `;

    const reimprimir = async (codigo, button = null) => {
        if (!codigo) {
            return;
        }

        if (button) {
            setLoading(button, true);
        }

        try {
            const response = await apiFetch(`/api/fichas/${encodeURIComponent(codigo)}/reimprimir`, {
                method: 'POST',
            });

            const ficha = response?.data?.ficha || response?.data || state.current;

            if (ficha && state.current?.codigo === codigo) {
                state.current = { ...state.current, ...ficha };
                renderDetail(state.current);
            }

            toast('success', response?.message || 'Reimpresión registrada.');
            window.setTimeout(() => printFicha(ficha || state.current), 320);
        } catch (error) {
            toast('error', apiErrorMessage(error));
        } finally {
            if (button) {
                setLoading(button, false);
            }
        }
    };

    const printFicha = (ficha) => {
        if (!ficha) {
            toast('error', 'No hay una ficha lista para imprimir.');
            return;
        }

        let frame = document.getElementById('ficha-print-frame');
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = 'ficha-print-frame';
            frame.className = 'print-frame';
            frame.setAttribute('title', 'Impresión de ficha');
            document.body.appendChild(frame);
        }

        frame.srcdoc = printDocument(ficha);
        frame.onload = () => {
            window.setTimeout(() => {
                try {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                } catch (error) {
                    toast('error', 'No pudimos abrir el diálogo de impresión.');
                }
            }, 120);
        };
    };

    const printDocument = (ficha) => `<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>${escapeHtml(ficha.codigo || 'Ficha')}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "DM Sans", Arial, sans-serif; margin: 0; padding: 18px; color: #12241f; }
        .ticket { border: 1px dashed #8a9992; border-radius: 12px; padding: 18px; width: 340px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; gap: 12px; padding-bottom: 10px; border-bottom: 1px dashed #b9c4be; }
        .brand strong { display: block; font-size: 15px; }
        .brand small, .code small { display: block; font-size: 8px; letter-spacing: .12em; text-transform: uppercase; color: #68766f; }
        .code { text-align: right; font-family: monospace; }
        .code strong { font-size: 15px; }
        .patient { padding: 12px 0 8px; }
        .patient strong { display: block; font-size: 17px; }
        .patient small { font-size: 9px; color: #68766f; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin: 8px 0; }
        .cell { padding: 6px 7px; border-radius: 6px; background: #f1f4ef; }
        .cell span { display: block; font-size: 7px; letter-spacing: .08em; text-transform: uppercase; color: #68766f; }
        .cell strong { display: block; font-size: 10px; margin-top: 1px; }
        .access { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px; border-radius: 8px; background: #0b3d35; color: #f4f6f0; }
        .access span { display: block; font-size: 7px; letter-spacing: .1em; text-transform: uppercase; opacity: .65; }
        .access small { display: block; font-size: 8px; opacity: .75; }
        .access strong { font-family: monospace; font-size: 25px; letter-spacing: .14em; color: #cfe8da; }
        .foot { display: flex; justify-content: space-between; gap: 8px; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #b9c4be; font-size: 7px; color: #68766f; }
        .bars { display: flex; gap: 1px; height: 16px; }
        .bars i { width: 2px; background: #12241f; }
        @page { margin: 8mm; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="head">
            <div class="brand">
                <strong>Clínica Vida Salud</strong>
                <small>Ficha de atención</small>
            </div>
            <div class="code">
                <strong>${escapeHtml(ficha.codigo || '')}</strong>
                <small>${escapeHtml(channelLabel(ficha.canal))}</small>
            </div>
        </div>
        <div class="patient">
            <strong>${escapeHtml(sharedPatientName(ficha.paciente))}</strong>
            <small>CI ${escapeHtml(ficha.paciente?.ci || '—')} · ${escapeHtml(ficha.paciente?.codigo || '')}</small>
        </div>
        <div class="grid">
            <div class="cell"><span>Fecha</span><strong>${escapeHtml(ficha.fecha ? formatDateNumeric(ficha.fecha) : '—')}</strong></div>
            <div class="cell"><span>Hora</span><strong>${escapeHtml(formatTimeValue(ficha.hora_inicio))}</strong></div>
            <div class="cell"><span>Médico</span><strong>${escapeHtml(ficha.medico?.nombre_completo || '—')}</strong></div>
            <div class="cell"><span>Especialidad</span><strong>${escapeHtml(ficha.especialidad?.nombre || '—')}</strong></div>
        </div>
        <div class="access">
            <div>
                <span>Código de acceso</span>
                <small>Preséntalo en consultorio</small>
            </div>
            <strong>${escapeHtml(ficha.codigo_acceso || '••••')}</strong>
        </div>
        <div class="foot">
            <span>Emitida ${escapeHtml(formatDateTime(ficha.emitida_at))}</span>
            <span class="bars" aria-hidden="true">${bars()}</span>
        </div>
    </div>
</body>
</html>`;

    const channelLabel = (channel) => channelLabelMap[channel] || channel || 'Recepción';

    table?.addEventListener('click', (event) => {
        const open = event.target.closest('[data-ficha-open]');
        if (open) {
            openFicha(open.dataset.fichaOpen);
            return;
        }

        const reprint = event.target.closest('[data-ficha-reprint]');
        if (reprint) {
            reimprimir(reprint.dataset.fichaReprint, reprint);
        }
    });

    printButton?.addEventListener('click', () => {
        if (state.current?.codigo) {
            reimprimir(state.current.codigo, printButton);
        }
    });

    search?.addEventListener('input', debounce(() => {
        state.q = search.value.trim();
        state.page = 1;
        clear.hidden = state.q === '';
        load();
    }, 320));

    clear?.addEventListener('click', () => {
        search.value = '';
        state.q = '';
        state.page = 1;
        clear.hidden = true;
        search.focus();
        load();
    });

    dateInput?.addEventListener('change', () => {
        state.fecha = dateInput.value;
        state.page = 1;
        load();
    });

    $('[data-ficha-refresh]')?.addEventListener('click', load);

    load();
}

const channelLabelMap = {
    web: 'Web',
    kiosco: 'Kiosco',
    recepcion: 'Recepción',
};

function bars() {
    return Array.from({ length: 48 }, (_, index) => {
        const width = (index % 5) + 1;
        return `<i style="width:${width}px"></i>`;
    }).join('');
}
