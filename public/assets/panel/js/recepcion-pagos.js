import {
    $,
    apiFetch,
    apiErrorMessage,
    clearFieldErrors,
    closeModal,
    confirmAction,
    escapeHtml,
    formatDateNumeric,
    formatDateTime,
    formatMoney,
    formatTimeValue,
    initials,
    openModal,
    renderFieldErrors,
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
        adelantos: [],
        reembolsos: [],
        saldos: [],
        ordenes: [],
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
    const pendingAdelantos = $('[data-cobros-adelantos]');
    const pendingReembolsos = $('[data-cobros-reembolsos]');
    const pendingSaldos = $('[data-cobros-saldos]');
    const pendingOrdenes = $('[data-cobros-ordenes]');
    const cobroModal = $('#modal-cobro');
    const cobroForm = $('[data-cobro-form]');
    const cobroSummary = $('[data-cobro-summary]');
    const cobroTitle = $('[data-cobro-title]');
    const cobroKicker = $('[data-cobro-kicker]');
    const cobroSubmit = $('[data-cobro-submit]');
    const cobroNote = $('[data-cobro-note]');

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
        qr: 'badge-pine',
        pasarela: 'badge-muted',
    }[method] || 'badge-muted');

    /* Cobros pendientes --------------------------------------------------- */

    const renderEmpty = (container, message) => {
        if (!container) return;
        container.innerHTML = emptyStateHtml({
            icon: 'money',
            title: 'Nada pendiente',
            message,
        });
    };

    const renderPending = () => {
        if (pendingAdelantos) {
            pendingAdelantos.innerHTML = state.adelantos.length === 0
                ? emptyStateHtml({
                    icon: 'qr',
                    title: 'Sin adelantos pendientes',
                    message: 'Cuando un paciente reserve por web o kiosco, el adelanto aparecerá aquí para confirmarlo.',
                })
                : `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Cita</th>
                                <th class="align-right">Adelanto</th>
                                <th class="align-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${state.adelantos.map((row) => `
                                <tr>
                                    <td>
                                        <div class="cell-person">
                                            <span class="avatar avatar-sm">${escapeHtml(initials(row.paciente?.nombre_completo || ''))}</span>
                                            <div>
                                                <strong>${escapeHtml(row.paciente?.nombre_completo || 'Paciente')}</strong>
                                                <small>${escapeHtml(row.paciente?.ci || '')}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-stack">
                                            <strong>${escapeHtml(row.codigo)}</strong>
                                            <small>${escapeHtml(formatDateNumeric(row.fecha))} · ${escapeHtml(row.hora_inicio)}</small>
                                        </div>
                                    </td>
                                    <td class="align-right">
                                        <strong>${escapeHtml(formatMoney(row.adelanto))}</strong>
                                        <div><small>de ${escapeHtml(formatMoney(row.precio_total))}</small></div>
                                    </td>
                                    <td class="align-right">
                                        <button class="btn btn-primary btn-sm" type="button"
                                                data-cobro-adelanto="${row.cita_id}">
                                            Confirmar
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
        }

        if (pendingReembolsos) {
            pendingReembolsos.innerHTML = state.reembolsos.length === 0
                ? emptyStateHtml({
                    icon: 'refresh',
                    title: 'Sin reembolsos pendientes',
                    message: 'Las cancelaciones con adelanto pagado generan un comprobante y aparecen aquí.',
                })
                : `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Comprobante</th>
                                <th class="align-right">Monto</th>
                                <th class="align-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${state.reembolsos.map((row) => `
                                <tr>
                                    <td>
                                        <div class="cell-stack">
                                            <strong>${escapeHtml(row.paciente?.nombre_completo || 'Paciente')}</strong>
                                            <small>${escapeHtml(row.paciente?.ci || '')} · ${escapeHtml(row.codigo)}</small>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-accent">${escapeHtml(row.reembolso_codigo || '—')}</span></td>
                                    <td class="align-right"><strong>${escapeHtml(formatMoney(row.reembolso_monto))}</strong></td>
                                    <td class="align-right">
                                        <button class="btn btn-primary btn-sm" type="button"
                                                data-cobro-reembolso="${row.cita_id}">
                                            Entregar
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
        }

        if (pendingSaldos) {
            pendingSaldos.innerHTML = state.saldos.length === 0
                ? emptyStateHtml({
                    icon: 'check-circle',
                    title: 'Sin saldos pendientes',
                    message: 'Los saldos de consultas con adelanto confirmado aparecerán aquí.',
                })
                : `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Cita</th>
                                <th class="align-right">Saldo</th>
                                <th class="align-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${state.saldos.map((row) => `
                                <tr>
                                    <td>
                                        <div class="cell-stack">
                                            <strong>${escapeHtml(row.paciente?.nombre_completo || 'Paciente')}</strong>
                                            <small>${escapeHtml(row.paciente?.ci || '')}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-stack">
                                            <strong>${escapeHtml(row.codigo)}</strong>
                                            <small>${escapeHtml(formatDateNumeric(row.fecha))} · ${escapeHtml(row.hora_inicio)}</small>
                                        </div>
                                    </td>
                                    <td class="align-right">
                                        <strong>${escapeHtml(formatMoney(row.saldo))}</strong>
                                        <div><small>Pagó ${escapeHtml(formatMoney(row.adelanto))}</small></div>
                                    </td>
                                    <td class="align-right">
                                        <button class="btn btn-primary btn-sm" type="button"
                                                data-cobro-saldo="${row.cita_id}">
                                            Cobrar saldo
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
        }
    };

    const loadPending = async () => {
        if (!pendingAdelantos && !pendingReembolsos && !pendingSaldos) return;

        try {
            const response = await apiFetch('/api/cobros/pendientes');
            state.adelantos = response?.data?.adelantos || [];
            state.reembolsos = response?.data?.reembolsos || [];
            state.saldos = response?.data?.saldos || [];
            renderPending();
        } catch (error) {
            if (pendingAdelantos) renderLoadError(pendingAdelantos, error, loadPending);
            if (pendingReembolsos) pendingReembolsos.innerHTML = '';
            if (pendingSaldos) pendingSaldos.innerHTML = '';
        }
    };

    const renderOrdenes = () => {
        if (!pendingOrdenes) return;

        pendingOrdenes.innerHTML = state.ordenes.length === 0
            ? emptyStateHtml({
                icon: 'flask',
                title: 'Sin órdenes pendientes',
                message: 'Cuando el médico emita una orden con precios, aparecerá aquí para cobrarla.',
            })
            : `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Paciente</th>
                            <th>Médico</th>
                            <th class="align-right">Total</th>
                            <th class="align-right">Saldo</th>
                            <th class="align-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${state.ordenes.map((row) => `
                            <tr>
                                <td>
                                    <div class="cell-stack">
                                        <strong>${escapeHtml(row.codigo)}</strong>
                                        <small>${escapeHtml(formatDateNumeric(row.fecha))}</small>
                                    </div>
                                </td>
                                <td>${escapeHtml(patientName(row.paciente))}</td>
                                <td>${escapeHtml(doctorName(row.medico))}</td>
                                <td class="align-right">${escapeHtml(formatMoney(row.total))}</td>
                                <td class="align-right"><strong>${escapeHtml(formatMoney(row.saldo))}</strong></td>
                                <td class="align-right">
                                    <button class="btn btn-primary btn-sm" type="button"
                                            data-cobro-orden="${row.id}">
                                        Cobrar
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
    };

    const loadOrdenes = async () => {
        if (!pendingOrdenes) return;

        try {
            const response = await apiFetch('/api/cobros/ordenes-pendientes');
            state.ordenes = Array.isArray(response?.data) ? response.data : [];
            renderOrdenes();
        } catch (error) {
            renderLoadError(pendingOrdenes, error, loadOrdenes);
        }
    };

    const findAdelanto = (id) => state.adelantos.find((row) => String(row.cita_id) === String(id));
    const findReembolso = (id) => state.reembolsos.find((row) => String(row.cita_id) === String(id));
    const findSaldo = (id) => state.saldos.find((row) => String(row.cita_id) === String(id));
    const findOrden = (id) => state.ordenes.find((row) => String(row.id) === String(id));

    const openCobro = ({
        accion,
        id,
        endpoint,
        title,
        kicker,
        summary,
        nota,
        submitLabel,
        defaultMetodo = 'efectivo',
        monto,
    }) => {
        cobroForm.reset();
        clearFieldErrors(cobroForm);
        cobroForm.elements.accion.value = accion;
        cobroForm.elements.id.value = id;
        cobroForm.elements.monto.value = Number(monto || 0).toFixed(2);
        cobroForm.dataset.endpoint = endpoint;

        if (cobroTitle) cobroTitle.textContent = title;
        if (cobroKicker) cobroKicker.textContent = kicker;
        if (cobroSummary) cobroSummary.innerHTML = summary;
        if (cobroNote) cobroNote.textContent = nota || '';
        if (cobroSubmit) cobroSubmit.textContent = submitLabel || 'Confirmar cobro';

        const radio = cobroForm.querySelector(`[name="metodo"][value="${defaultMetodo}"]`);
        if (radio) radio.checked = true;

        openModal(cobroModal);
    };

    const summaryHtml = (rows) => `
        <dl class="cobro-summary-list">
            ${rows.map(([label, value]) => `
                <div>
                    <dt>${escapeHtml(label)}</dt>
                    <dd>${escapeHtml(value)}</dd>
                </div>
            `).join('')}
        </dl>
    `;

    document.addEventListener('click', (event) => {
        const adelantoBtn = event.target.closest('[data-cobro-adelanto]');
        if (adelantoBtn) {
            const row = findAdelanto(adelantoBtn.dataset.cobroAdelanto);
            if (!row) return;

            openCobro({
                accion: 'adelanto',
                id: row.cita_id,
                endpoint: `/api/citas/${row.cita_id}/adelanto/confirmar`,
                title: 'Confirmar adelanto',
                kicker: 'Adelanto de consulta',
                submitLabel: 'Confirmar adelanto',
                defaultMetodo: 'qr',
                monto: row.adelanto,
                summary: summaryHtml([
                    ['Paciente', row.paciente?.nombre_completo || ''],
                    ['Cita', `${row.codigo} · ${formatDateNumeric(row.fecha)} ${row.hora_inicio}`],
                    ['Adelanto', formatMoney(row.adelanto)],
                    ['Saldo al terminar', formatMoney(row.saldo)],
                ]),
                nota: 'El adelanto ya fue recibido por QR o en caja.',
            });
            return;
        }

        const saldoBtn = event.target.closest('[data-cobro-saldo]');
        if (saldoBtn) {
            const row = findSaldo(saldoBtn.dataset.cobroSaldo);
            if (!row) return;

            openCobro({
                accion: 'saldo',
                id: row.cita_id,
                endpoint: `/api/citas/${row.cita_id}/saldo`,
                title: 'Cobrar saldo',
                kicker: 'Saldo de consulta',
                submitLabel: 'Cobrar saldo',
                defaultMetodo: 'efectivo',
                monto: row.saldo,
                summary: summaryHtml([
                    ['Paciente', row.paciente?.nombre_completo || ''],
                    ['Cita', `${row.codigo} · ${formatDateNumeric(row.fecha)} ${row.hora_inicio}`],
                    ['Precio total', formatMoney(row.precio_total)],
                    ['Adelanto pagado', formatMoney(row.adelanto)],
                    ['Saldo', formatMoney(row.saldo)],
                ]),
                nota: 'Cobra el saldo al terminar la consulta.',
            });
            return;
        }

        const reembolsoBtn = event.target.closest('[data-cobro-reembolso]');
        if (reembolsoBtn) {
            const row = findReembolso(reembolsoBtn.dataset.cobroReembolso);
            if (!row) return;

            openCobro({
                accion: 'reembolso',
                id: row.cita_id,
                endpoint: `/api/citas/${row.cita_id}/reembolso/entregar`,
                title: 'Entregar reembolso',
                kicker: 'Reembolso con comprobante',
                submitLabel: 'Registrar entrega',
                defaultMetodo: 'efectivo',
                monto: row.reembolso_monto,
                summary: summaryHtml([
                    ['Paciente', row.paciente?.nombre_completo || ''],
                    ['Comprobante', row.reembolso_codigo || '—'],
                    ['Cita cancelada', row.codigo],
                    ['Monto a devolver', formatMoney(row.reembolso_monto)],
                ]),
                nota: 'Verifica el comprobante que presenta el paciente antes de entregar el dinero.',
            });
            return;
        }

        const ordenBtn = event.target.closest('[data-cobro-orden]');
        if (ordenBtn) {
            const row = findOrden(ordenBtn.dataset.cobroOrden);
            if (!row) return;

            openCobro({
                accion: 'orden',
                id: row.id,
                endpoint: `/api/ordenes/${row.id}/pago`,
                title: 'Cobrar orden',
                kicker: 'Laboratorio e imagen',
                submitLabel: 'Cobrar orden',
                defaultMetodo: 'efectivo',
                monto: row.saldo,
                summary: summaryHtml([
                    ['Orden', row.codigo],
                    ['Paciente', patientName(row.paciente)],
                    ['Total', formatMoney(row.total)],
                    ['Pagado', formatMoney(row.pagado)],
                    ['Saldo', formatMoney(row.saldo)],
                ]),
                nota: 'El precio proviene del catálogo de exámenes de recepción.',
            });
        }
    });

    cobroForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(cobroForm);

        const endpoint = cobroForm.dataset.endpoint;
        const monto = Number(cobroForm.elements.monto.value || 0);
        const metodo = cobroForm.querySelector('[name="metodo"]:checked')?.value || 'efectivo';
        const referencia = String(cobroForm.elements.referencia.value || '').trim();

        setLoading(cobroSubmit, true);

        try {
            const response = await apiFetch(endpoint, {
                method: 'POST',
                body: { monto, metodo, referencia: referencia || null },
            });

            toast('success', response?.message || 'Cobro registrado.');
            closeModal(cobroModal);
            loadPending();
            loadOrdenes();
            loadSummary();
            loadPayments();
        } catch (error) {
            if (error?.fields) renderFieldErrors(cobroForm, error.fields);
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(cobroSubmit, false);
        }
    });

    $('[data-cobros-refresh]')?.addEventListener('click', () => {
        loadPending();
        loadOrdenes();
    });

    $('[data-cobros-expirar]')?.addEventListener('click', async () => {
        const confirmed = await confirmAction({
            title: 'Liberar adelantos vencidos',
            message: 'Se cancelarán las citas cuyo adelanto no se pagó dentro del plazo. Los cupos quedarán disponibles.',
            confirmLabel: 'Liberar cupos',
            danger: true,
        });

        if (!confirmed) return;

        try {
            const response = await apiFetch('/api/cobros/expirar', { method: 'POST', body: {} });
            toast('success', response?.message || 'Adelantos vencidos liberados.');
            loadPending();
            loadPayments();
        } catch (error) {
            toast('error', apiErrorMessage(error));
        }
    });

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
        loadPending();
        loadOrdenes();

        if (new URLSearchParams(window.location.search).get('nuevo') === '1') {
            window.setTimeout(() => $('[data-pago-patient-search]')?.focus(), 250);
        }
    };

    bootstrap();
}
