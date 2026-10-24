import {
    $,
    apiFetch,
    apiErrorMessage,
    escapeHtml,
    formatDateNumeric,
    formatDateTime,
    formatMoney,
    formatTimeValue,
    initials,
    renderFieldErrors,
    clearFieldErrors,
    setLoading,
    toast,
    todayISO,
} from './app.js';
import {
    PatientPicker,
    doctorName,
    emptyStateHtml,
    fillSelect,
    getMedicos,
    medicalOptionLabel,
    methodLabel,
    patientName,
    renderLoadError,
} from './recepcion-shared.js';

export default function initPagos() {
    const state = {
        doctors: [],
        rows: [],
        summary: [],
    };

    const form = $('[data-pago-form]');
    const doctorSelect = $('[data-pago-medico]');
    const amount = $('[data-pago-monto]');
    const reference = $('[data-pago-referencia]');
    const table = $('[data-pagos-table]');
    const tableFoot = $('[data-pago-table-foot]');
    const tableCount = $('[data-pago-table-count]');
    const tableTotal = $('[data-pago-table-total]');
    const fromInput = $('[data-pago-desde]');
    const toInput = $('[data-pago-hasta]');
    const filterDoctor = $('[data-pago-filtro-medico]');
    const totalNode = $('[data-pago-total]');
    const countNode = $('[data-pago-count]');

    const picker = new PatientPicker({
        search: $('[data-pago-patient-search]'),
        results: $('[data-pago-patient-results]'),
        selected: $('[data-pago-patient-selected]'),
        hidden: $('[data-pago-patient-id]'),
    });

    const loadSummary = async () => {
        try {
            const today = todayISO();
            const response = await apiFetch(
                `/api/pagos?desde=${today}&hasta=${today}&per_page=100`
            );
            state.summary = Array.isArray(response?.data) ? response.data : [];
            renderSummary();
        } catch (error) {
            if (totalNode) totalNode.textContent = '—';
            if (countNode) countNode.textContent = 'No pudimos cargar el resumen';
        }
    };

    const renderSummary = () => {
        const total = state.summary.reduce((sum, row) => sum + Number(row.monto || 0), 0);
        const methods = {
            efectivo: 0,
            visa: 0,
            mastercard: 0,
        };

        state.summary.forEach((row) => {
            if (Object.hasOwn(methods, row.metodo)) {
                methods[row.metodo] += Number(row.monto || 0);
            }
        });

        if (totalNode) totalNode.textContent = formatMoney(total);
        if (countNode) {
            countNode.textContent = state.summary.length === 0
                ? 'Sin movimientos todavía'
                : `${state.summary.length} movimiento${state.summary.length === 1 ? '' : 's'} hoy`;
        }

        Object.entries(methods).forEach(([method, value]) => {
            const node = document.querySelector(`[data-pago-metodo="${method}"]`);
            if (node) node.textContent = formatMoney(value);
        });
    };

    const loadPayments = async () => {
        table.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 5 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

        try {
            const params = new URLSearchParams({ per_page: '100' });
            if (fromInput?.value) params.set('desde', fromInput.value);
            if (toInput?.value) params.set('hasta', toInput.value);
            if (filterDoctor?.value) params.set('medico', filterDoctor.value);

            const response = await apiFetch(`/api/pagos?${params.toString()}`);
            state.rows = Array.isArray(response?.data) ? response.data : [];
            renderPayments();
        } catch (error) {
            renderLoadError(table, error, () => {
                loadPayments();
                loadSummary();
            });
            if (tableFoot) tableFoot.hidden = true;
        }
    };

    const renderPayments = () => {
        if (state.rows.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'card',
                title: 'Sin pagos en este rango',
                message: 'Ajusta las fechas o registra un cobro con el formulario de arriba.',
            });
            if (tableFoot) tableFoot.hidden = true;
            return;
        }

        const total = state.rows.reduce((sum, row) => sum + Number(row.monto || 0), 0);

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Médico</th>
                        <th>Método</th>
                        <th>Referencia</th>
                        <th>Registró</th>
                        <th class="align-right">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.rows.map((row) => `
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <strong>${escapeHtml(formatDateNumeric(row.fecha))}</strong>
                                    <small>${escapeHtml(row.creado_at ? formatTimeValue(row.creado_at) : '')}</small>
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
                            <td>
                                <div class="cell-stack">
                                    <strong>${escapeHtml(doctorName(row.medico))}</strong>
                                    <small>${escapeHtml(row.especialidad?.nombre || '')}</small>
                                </div>
                            </td>
                            <td><span class="badge ${methodBadge(row.metodo)}">${escapeHtml(methodLabel(row.metodo))}</span></td>
                            <td>${escapeHtml(row.referencia_paciente || '—')}</td>
                            <td>${escapeHtml(row.registrado_por?.nombre_completo || row.registrado_por?.username || '—')}</td>
                            <td class="align-right"><strong>${escapeHtml(formatMoney(row.monto))}</strong></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        if (tableFoot) {
            tableFoot.hidden = false;
            if (tableCount) tableCount.textContent = `${state.rows.length} movimiento${state.rows.length === 1 ? '' : 's'}`;
            if (tableTotal) tableTotal.textContent = formatMoney(total);
        }
    };

    const methodBadge = (method) => ({
        efectivo: 'badge-success',
        visa: 'badge-info',
        mastercard: 'badge-accent',
    }[method] || 'badge-muted');

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const submit = form.querySelector('button[type="submit"]');
        const patientId = Number(picker.hidden.value || 0);
        const medicoId = Number(doctorSelect?.value || 0);
        const monto = Number(amount?.value || 0);
        const metodo = form.querySelector('[name="metodo"]:checked')?.value || '';
        const referencia = String(reference?.value || '').trim();

        if (!patientId) {
            toast('error', 'Selecciona el paciente que realizó el pago.');
            $('[data-pago-patient-search]')?.focus();
            return;
        }

        if (!medicoId) {
            toast('error', 'Selecciona el médico de la atención.');
            doctorSelect?.focus();
            return;
        }

        if (!Number.isFinite(monto) || monto <= 0) {
            renderFieldErrors(form, { monto: 'Ingresa un monto mayor a cero.' });
            toast('error', 'Revisa el monto del pago.');
            return;
        }

        if (!metodo) {
            toast('error', 'Selecciona el método de pago.');
            return;
        }

        setLoading(submit, true);

        try {
            const response = await apiFetch('/api/pagos', {
                method: 'POST',
                body: {
                    paciente_id: patientId,
                    medico_id: medicoId,
                    monto,
                    metodo,
                    referencia_paciente: referencia || null,
                },
            });

            toast('success', response?.message || 'Pago registrado.');
            picker.reset();
            if (amount) amount.value = '';
            if (reference) reference.value = '';
            const efectivo = form.querySelector('[name="metodo"][value="efectivo"]');
            if (efectivo) efectivo.checked = true;

            loadSummary();
            loadPayments();
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
    filterDoctor?.addEventListener('change', loadPayments);
    $('[data-pago-refresh]')?.addEventListener('click', () => {
        loadSummary();
        loadPayments();
    });

    const bootstrap = async () => {
        try {
            state.doctors = await getMedicos();
            fillSelect(doctorSelect, state.doctors, {
                placeholder: 'Selecciona un médico',
                label: medicalOptionLabel,
            });

            fillSelect(filterDoctor, state.doctors, {
                placeholder: 'Todos los médicos',
                label: medicalOptionLabel,
            });

            if (state.doctors.length === 1 && doctorSelect) {
                doctorSelect.value = String(state.doctors[0].id);
            }
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }

        loadSummary();
        loadPayments();

        if (new URLSearchParams(window.location.search).get('nuevo') === '1') {
            window.setTimeout(() => $('[data-pago-patient-search]')?.focus(), 250);
        }
    };

    bootstrap();
}
