import {
    $,
    apiErrorMessage,
    apiFetch,
    clearFieldErrors,
    escapeHtml,
    formatDateNumeric,
    formatDateTime,
    formatMoney,
    initials,
    patientName,
    renderFieldErrors,
    setLoading,
    toast,
    todayISO,
} from './app.js';
import {
    PatientPicker,
    emptyStateHtml,
    methodLabel,
    renderLoadError,
} from './recepcion-shared.js';
import {
    MEDICO_ID,
    requireMedicoId,
} from './medico-shared.js';

const form = $('[data-mpago-form]');
const amount = $('[data-mpago-monto]');
const reference = $('[data-mpago-referencia]');
const table = $('[data-mpago-table]');
const countNode = $('[data-mpago-count]');
const totalNode = $('[data-mpago-total]');
const fromInput = $('[data-mpago-desde]');
const toInput = $('[data-mpago-hasta]');
const refresh = $('[data-mpago-refresh]');

let rows = [];

const picker = new PatientPicker({
    search: $('[data-mpago-patient-search]'),
    results: $('[data-mpago-patient-results]'),
    selected: $('[data-mpago-patient-selected]'),
    hidden: $('[data-mpago-patient-id]'),
});

function methodBadge(method) {
    return {
        efectivo: 'badge-success',
        visa: 'badge-info',
        mastercard: 'badge-accent',
    }[method] || 'badge-muted';
}

function setStat(key, value) {
    const node = document.querySelector(`[data-mpago-stat="${key}"]`);
    if (node) {
        node.textContent = value;
    }
}

function renderSummary() {
    const total = rows.reduce((sum, row) => sum + Number(row.monto || 0), 0);
    const methods = { efectivo: 0, visa: 0, mastercard: 0 };

    rows.forEach((row) => {
        if (Object.hasOwn(methods, row.metodo)) {
            methods[row.metodo] += Number(row.monto || 0);
        }
    });

    setStat('total', formatMoney(total));
    setStat('count', String(rows.length));
    setStat('efectivo', formatMoney(methods.efectivo));
    setStat('visa', formatMoney(methods.visa));
    setStat('mastercard', formatMoney(methods.mastercard));

    if (countNode) {
        countNode.textContent = `${rows.length} movimiento${rows.length === 1 ? '' : 's'}`;
    }
    if (totalNode) {
        totalNode.textContent = formatMoney(total);
    }
}

function renderPayments() {
    if (!table) {
        return;
    }

    if (rows.length === 0) {
        table.innerHTML = emptyStateHtml({
            icon: 'card',
            title: 'Sin pagos en este rango',
            message: 'Ajusta las fechas o registra un cobro con el formulario.',
        });
        return;
    }

    table.innerHTML = `
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th class="align-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map((row) => `
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <strong>${escapeHtml(formatDateNumeric(row.fecha))}</strong>
                                    <small>${escapeHtml(row.creado_at ? formatDateTime(row.creado_at) : '')}</small>
                                </div>
                            </td>
                            <td>
                                <div class="cell-person">
                                    <span class="avatar avatar-sm">${escapeHtml(initials(patientName(row.paciente)))}</span>
                                    <div>
                                        <strong>${escapeHtml(patientName(row.paciente))}</strong>
                                        <small>${escapeHtml(row.paciente?.ci || '')}</small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge ${methodBadge(row.metodo)}">${escapeHtml(methodLabel(row.metodo))}</span></td>
                            <td>${escapeHtml(row.referencia_paciente || '—')}</td>
                            <td class="align-right"><strong>${escapeHtml(formatMoney(row.monto))}</strong></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadPayments() {
    if (!table) {
        return;
    }

    table.innerHTML = `
        <div class="table-loading">
            ${Array.from({ length: 4 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
        </div>
    `;

    const params = new URLSearchParams({
        medico: String(MEDICO_ID),
        per_page: '100',
    });

    if (fromInput?.value) {
        params.set('desde', fromInput.value);
    }
    if (toInput?.value) {
        params.set('hasta', toInput.value);
    }

    try {
        const response = await apiFetch(`/api/pagos?${params.toString()}`);
        rows = Array.isArray(response?.data) ? response.data : [];
        renderSummary();
        renderPayments();
    } catch (error) {
        renderLoadError(table, error, loadPayments);
        rows = [];
        renderSummary();
    }
}

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(form);

    const patientId = Number(picker.hidden?.value || 0);
    const monto = Number(amount?.value || 0);
    const metodo = form.querySelector('[name="metodo"]:checked')?.value || '';
    const ref = String(reference?.value || '').trim();

    if (!patientId) {
        toast('error', 'Selecciona el paciente que realizó el pago.');
        $('[data-mpago-patient-search]')?.focus();
        return;
    }

    if (!Number.isFinite(monto) || monto <= 0) {
        renderFieldErrors(form, { monto: 'Ingresa un monto mayor a cero.' });
        return;
    }

    if (!metodo) {
        toast('error', 'Selecciona el método de pago.');
        return;
    }

    const submit = form.querySelector('button[type="submit"]');
    setLoading(submit, true);

    try {
        const response = await apiFetch('/api/pagos', {
            method: 'POST',
            body: {
                paciente_id: patientId,
                medico_id: MEDICO_ID,
                monto,
                metodo,
                referencia_paciente: ref || null,
            },
        });

        toast('success', response?.message || 'Pago registrado.');
        picker.reset();
        if (amount) {
            amount.value = '';
        }
        if (reference) {
            reference.value = '';
        }
        const efectivo = form.querySelector('[name="metodo"][value="efectivo"]');
        if (efectivo) {
            efectivo.checked = true;
        }
        await loadPayments();
    } catch (error) {
        if (error?.fields) {
            renderFieldErrors(form, error.fields);
        }
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(submit, false);
    }
});

fromInput?.addEventListener('change', loadPayments);
toInput?.addEventListener('change', loadPayments);
refresh?.addEventListener('click', loadPayments);

if (fromInput && !fromInput.value) {
    fromInput.value = todayISO();
}
if (toInput && !toInput.value) {
    toInput.value = todayISO();
}

if (requireMedicoId()) {
    loadPayments();
}
