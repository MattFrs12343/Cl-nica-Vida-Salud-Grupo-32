import {
    $,
    PANEL,
    addDays,
    apiErrorMessage,
    apiFetch,
    closeModal,
    confirmAction,
    escapeHtml,
    formatDateNumeric,
    formatMoney,
    formatTimeValue,
    initials,
    openModal,
    parseDateOnly,
    patientName,
    setLoading,
    statusBadge,
    toast,
    todayISO,
} from './app.js';

export const PACIENTE_ID = Number(PANEL.pacienteId || 0);

let profilePromise = null;

export function requirePacienteId() {
    if (PACIENTE_ID > 0) {
        return PACIENTE_ID;
    }

    toast('error', 'Tu cuenta no tiene un paciente asociado. Contacta a recepción.');
    return 0;
}

export async function loadProfile() {
    if (profilePromise) {
        return profilePromise;
    }

    profilePromise = apiFetch('/api/me/perfil')
        .then((response) => response?.data || null)
        .catch((error) => {
            profilePromise = null;
            throw error;
        });

    return profilePromise;
}

export async function loadAppointments() {
    const id = requirePacienteId();
    if (!id) {
        return { paciente: null, citas: [] };
    }

    const response = await apiFetch(`/api/pacientes/${id}/historial`);
    return {
        paciente: response?.data?.paciente || null,
        citas: Array.isArray(response?.data?.citas) ? response.data.citas : [],
    };
}

export async function loadHistory() {
    const id = requirePacienteId();
    if (!id) {
        return { paciente: null, consultas: [] };
    }

    const response = await apiFetch(`/api/pacientes/${id}/historia`);
    return {
        paciente: response?.data?.paciente || null,
        consultas: Array.isArray(response?.data?.consultas) ? response.data.consultas : [],
    };
}

export function isUpcoming(cita) {
    return ['programada', 'confirmada'].includes(String(cita?.estado || ''))
        && String(cita?.fecha || '') >= todayISO();
}

export function isPast(cita) {
    if (['atendida', 'cancelada', 'inasistencia'].includes(String(cita?.estado || ''))) {
        return true;
    }

    return String(cita?.fecha || '') < todayISO();
}

export function sortAppointments(citas) {
    return [...citas].sort((a, b) => {
        const left = `${a.fecha || ''} ${a.hora_inicio || a.hora || ''}`;
        const right = `${b.fecha || ''} ${b.hora_inicio || b.hora || ''}`;
        return left.localeCompare(right);
    });
}

export function appointmentCardHtml(cita, { allowCancel = true } = {}) {
    const fecha = parseDateOnly(cita.fecha);
    const day = fecha ? String(fecha.getDate()).padStart(2, '0') : '—';
    const month = fecha
        ? ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][fecha.getMonth()]
        : '';
    const hora = cita.hora_inicio || cita.hora;
    const doctor = cita.medico?.nombre_completo || cita.medico?.nombre || 'Médico';
    const especialidad = cita.especialidad?.nombre || '';
    const puedeCancelar = allowCancel && isUpcoming(cita);

    return `
        <article class="history-cita">
            <div class="history-cita-date">
                <strong>${escapeHtml(day)}</strong>
                <span>${escapeHtml(month)}</span>
            </div>
            <div class="history-cita-body">
                <div class="history-cita-top">
                    <strong>${escapeHtml(cita.motivo || 'Consulta médica')}</strong>
                    ${statusBadge(cita.estado)}
                    ${cita.es_reconsulta ? '<span class="badge badge-info">Reconsulta</span>' : ''}
                </div>
                <small>${escapeHtml(formatDateNumeric(cita.fecha))}${hora ? ` · ${escapeHtml(formatTimeValue(hora))}` : ''} · ${escapeHtml(doctor)}${especialidad ? ` · ${escapeHtml(especialidad)}` : ''}</small>
                ${cita.codigo ? `<p class="mono" style="margin:6px 0 0;font-size:12px;">${escapeHtml(cita.codigo)}</p>` : ''}
                ${puedeCancelar ? `
                    <div class="row-actions" style="margin-top:8px;">
                        <button class="btn btn-ghost btn-sm" type="button" data-cancelar-cita="${escapeHtml(cita.id)}">
                            <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                            Cancelar cita
                        </button>
                    </div>
                ` : ''}
            </div>
        </article>
    `;
}

/* Cancelación con reprogramación o reembolso ----------------------------- */

const CANCEL_MODAL_ID = 'modal-cancelacion-paciente';
let cancelState = null;

function ensureCancelModal() {
    let modal = document.getElementById(CANCEL_MODAL_ID);

    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.className = 'modal';
    modal.id = CANCEL_MODAL_ID;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="cancelacion-title">
            <header class="modal-head">
                <div>
                    <span class="modal-kicker">Mi cita</span>
                    <h2 id="cancelacion-title" data-cancelacion-title>Cancelar cita</h2>
                </div>
                <button class="modal-close" type="button" data-modal-close aria-label="Cerrar">
                    <svg class="icon" aria-hidden="true"><use href="#i-close"></use></svg>
                </button>
            </header>
            <div class="modal-body" data-cancelacion-body></div>
        </div>
    `;
    document.body.append(modal);

    return modal;
}

function cancelSummaryHtml(opciones) {
    return `
        <div class="cancel-summary">
            <dl class="cancel-summary-list">
                <div><dt>Cita</dt><dd>${escapeHtml(opciones.codigo || '—')}</dd></div>
                <div><dt>Fecha original</dt><dd>${escapeHtml(formatDateNumeric(opciones.fecha))} · ${escapeHtml(formatTimeValue(opciones.hora_inicio))}</dd></div>
                <div><dt>Consulta</dt><dd>${escapeHtml(formatMoney(opciones.precio_total))}</dd></div>
                <div><dt>Adelanto</dt><dd>${escapeHtml(formatMoney(opciones.adelanto))}</dd></div>
                <div><dt>Saldo</dt><dd>${escapeHtml(formatMoney(opciones.saldo))}</dd></div>
            </dl>
        </div>
    `;
}

function cancelSlotsHtml(opciones, seleccionada) {
    const slots = Array.isArray(opciones.slots) ? opciones.slots : [];

    if (slots.length === 0) {
        return '<p class="picker-empty">No hay horarios libres ese día. Prueba con otra fecha.</p>';
    }

    return `
        <div class="slot-grid" role="group" aria-label="Horarios disponibles">
            ${slots.map((slot) => `
                <button class="slot ${seleccionada === slot.hora ? 'is-selected' : ''}" type="button"
                        data-cancel-slot="${escapeHtml(slot.hora)}"
                        aria-pressed="${seleccionada === slot.hora ? 'true' : 'false'}">
                    ${escapeHtml(formatTimeValue(slot.hora))}
                </button>
            `).join('')}
        </div>
    `;
}

function renderCancelSlots(modal) {
    if (!cancelState) {
        return;
    }

    const { opciones, hora } = cancelState;
    const slotsNode = $('[data-cancel-slots]', modal);
    const selectedNode = $('[data-cancel-selected]', modal);
    const submit = $('[data-cancel-reprogramar]', modal);
    const sugerencia = opciones.sugerencia || {};

    if (slotsNode) {
        slotsNode.innerHTML = cancelSlotsHtml(opciones, hora);
    }

    if (selectedNode) {
        selectedNode.textContent = cancelState.fecha && hora
            ? `Nuevo horario: ${formatDateNumeric(cancelState.fecha)} · ${formatTimeValue(hora)}`
            : 'Elige fecha y hora para reprogramar.';
    }

    if (submit) {
        submit.disabled = !(cancelState.fecha && hora);
    }

    slotsNode?.querySelectorAll('[data-cancel-slot]').forEach((button) => {
        button.addEventListener('click', () => {
            cancelState.hora = button.dataset.cancelSlot;
            renderCancelSlots(modal);
        });
    });

    const sugerenciaNode = $('[data-cancel-sugerencia]', modal);

    if (sugerenciaNode) {
        sugerenciaNode.innerHTML = `
            <strong>${escapeHtml(formatDateNumeric(sugerencia.fecha))} · ${escapeHtml(formatTimeValue(sugerencia.hora))}</strong>
            <small>${escapeHtml(sugerencia.descripcion || '')}</small>
            ${sugerencia.disponible
                ? '<span class="badge badge-pine">Disponible</span>'
                : '<span class="badge badge-muted">Ocupado</span>'}
        `;
    }
}

async function loadCancelDate(modal, fecha) {
    if (!cancelState || !fecha) {
        return;
    }

    const slotsNode = $('[data-cancel-slots]', modal);

    if (slotsNode) {
        slotsNode.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-line"></div>
                <div class="skeleton skeleton-line"></div>
            </div>
        `;
    }

    try {
        const response = await apiFetch(
            `/api/citas/${cancelState.cita.id}/cancelacion/opciones?fecha=${encodeURIComponent(fecha)}`
        );
        cancelState.opciones = response?.data || cancelState.opciones;
        cancelState.fecha = cancelState.opciones.fecha_consulta || fecha;
        cancelState.hora = '';
        renderCancelSlots(modal);
    } catch (error) {
        if (slotsNode) {
            slotsNode.innerHTML = `<p class="picker-empty">${escapeHtml(apiErrorMessage(error))}</p>`;
        }
    }
}

function renderCancelacion(modal, cita, opciones, onChanged) {
    const body = $('[data-cancelacion-body]', modal);
    const title = $('[data-cancelacion-title]', modal);
    const sugerencia = opciones.sugerencia || {};
    const reembolso = opciones.reembolso || {};
    const puedeReembolsar = Boolean(opciones.puede_reembolsar);

    cancelState = {
        cita,
        opciones,
        fecha: opciones.fecha_consulta || sugerencia.fecha || '',
        hora: '',
        onChanged,
    };

    if (title) {
        title.textContent = `Cancelar ${opciones.codigo || 'mi cita'}`;
    }

    if (!body) {
        return;
    }

    body.innerHTML = `
        ${cancelSummaryHtml(opciones)}

        <div class="callout callout-info">
            <svg class="icon" aria-hidden="true"><use href="#i-info"></use></svg>
            <p>Puedes mover tu cita sin perder el adelanto o cancelarla y pedir el reembolso. La inasistencia retiene el adelanto.</p>
        </div>

        <section class="cancel-option" aria-labelledby="cancel-reprogramar-title">
            <div class="cancel-option-head">
                <span class="cancel-option-icon tone-primary" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                <div>
                    <h3 id="cancel-reprogramar-title">Reprogramar sin perder el adelanto</h3>
                    <p>Te sugerimos el mismo día y hora de la semana siguiente. También puedes elegir otro horario libre.</p>
                </div>
            </div>

            <div class="cancel-sugerencia" data-cancel-sugerencia></div>

            <div class="field-grid field-grid-2">
                <div class="field">
                    <label for="cancel-fecha">Otra fecha</label>
                    <input id="cancel-fecha" type="date" min="${escapeHtml(todayISO())}" max="${escapeHtml(addDays(todayISO(), 60))}" value="${escapeHtml(cancelState.fecha)}" data-cancel-fecha>
                </div>
                <div class="field" style="align-self:end;">
                    <button class="btn btn-ghost btn-sm" type="button" data-cancel-ver-fecha>
                        <svg class="icon" aria-hidden="true"><use href="#i-search"></use></svg>
                        Ver horarios
                    </button>
                </div>
            </div>

            <div class="cancel-slots" data-cancel-slots></div>
            <p class="field-help" data-cancel-selected></p>

            <div class="row-actions" style="margin-top:10px;">
                <button class="btn btn-primary btn-sm" type="button" data-cancel-reprogramar disabled>
                    <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    Reprogramar cita
                </button>
            </div>
            <p class="field-error" data-cancel-reprogramar-error role="alert" hidden></p>
        </section>

        <section class="cancel-option" aria-labelledby="cancel-definitiva-title">
            <div class="cancel-option-head">
                <span class="cancel-option-icon tone-accent" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span>
                <div>
                    <h3 id="cancel-definitiva-title">Cancelar definitivamente</h3>
                    <p>
                        ${puedeReembolsar
                            ? `Se emitirá un comprobante de reembolso por ${escapeHtml(formatMoney(reembolso.monto || opciones.adelanto))} para que lo presentes en recepción.`
                            : 'No hay adelanto pagado: solo se libera el cupo de la agenda.'}
                    </p>
                </div>
            </div>

            <div class="field">
                <label for="cancel-motivo">Motivo de la cancelación *</label>
                <textarea id="cancel-motivo" rows="3" maxlength="200" placeholder="Cuéntanos por qué no podrás asistir…" data-cancel-motivo></textarea>
            </div>

            <div class="row-actions" style="margin-top:10px;">
                <button class="btn btn-ghost btn-sm btn-danger" type="button" data-cancel-definitiva>
                    <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
                    ${puedeReembolsar ? 'Cancelar y pedir reembolso' : 'Cancelar cita'}
                </button>
            </div>
            <p class="field-error" data-cancel-definitiva-error role="alert" hidden></p>
        </section>
    `;

    const fechaInput = $('[data-cancel-fecha]', modal);
    const submitReprogramar = $('[data-cancel-reprogramar]', modal);

    renderCancelSlots(modal);

    $('[data-cancel-sugerencia]', modal)?.addEventListener('click', () => {
        if (!sugerencia.fecha) {
            return;
        }

        if (fechaInput) {
            fechaInput.value = sugerencia.fecha;
        }

        if (sugerencia.disponible && sugerencia.hora) {
            cancelState.fecha = sugerencia.fecha;
            cancelState.hora = sugerencia.hora;
            cancelState.opciones = { ...cancelState.opciones, slots: [{ hora: sugerencia.hora, disponible: true }] };
            renderCancelSlots(modal);
        } else {
            loadCancelDate(modal, sugerencia.fecha);
        }
    });

    $('[data-cancel-ver-fecha]', modal)?.addEventListener('click', () => {
        loadCancelDate(modal, fechaInput?.value || '');
    });

    fechaInput?.addEventListener('change', () => {
        if (fechaInput.value) {
            loadCancelDate(modal, fechaInput.value);
        }
    });

    submitReprogramar?.addEventListener('click', async () => {
        if (!cancelState?.fecha || !cancelState?.hora) {
            return;
        }

        const errorNode = $('[data-cancel-reprogramar-error]', modal);
        if (errorNode) errorNode.hidden = true;

        setLoading(submitReprogramar, true);

        try {
            const response = await apiFetch(`/api/citas/${cita.id}/cancelacion/reprogramar`, {
                method: 'POST',
                body: {
                    fecha: cancelState.fecha,
                    hora: cancelState.hora,
                    motivo: 'Reprogramación solicitada por el paciente',
                },
            });
            const nueva = response?.data?.reconsulta || {};
            toast('success', `Cita reprogramada para el ${formatDateNumeric(nueva.fecha)} a las ${formatTimeValue(nueva.hora_inicio)}.`);
            closeModal(modal);
            cancelState?.onChanged?.();
        } catch (error) {
            if (errorNode) {
                errorNode.textContent = apiErrorMessage(error);
                errorNode.hidden = false;
            }
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submitReprogramar, false);
        }
    });

    $('[data-cancel-definitiva]', modal)?.addEventListener('click', async (event) => {
        const motivo = String($('[data-cancel-motivo]', modal)?.value || '').trim();
        const errorNode = $('[data-cancel-definitiva-error]', modal);

        if (errorNode) errorNode.hidden = true;

        if (motivo === '') {
            if (errorNode) {
                errorNode.textContent = 'Indica el motivo de la cancelación.';
                errorNode.hidden = false;
            }
            return;
        }

        const confirmed = await confirmAction({
            title: puedeReembolsar ? 'Cancelar y pedir reembolso' : 'Cancelar cita',
            message: puedeReembolsar
                ? `Se cancelará la cita ${opciones.codigo || ''} y se emitirá un comprobante de reembolso por ${formatMoney(reembolso.monto || opciones.adelanto)}.`
                : `Se cancelará la cita ${opciones.codigo || ''} y el cupo quedará libre.`,
            confirmLabel: 'Sí, cancelar',
            cancelLabel: 'Volver',
            danger: true,
        });

        if (!confirmed) {
            return;
        }

        const button = event.currentTarget;
        setLoading(button, true);

        try {
            const response = await apiFetch(`/api/citas/${cita.id}/cancelacion/definitiva`, {
                method: 'POST',
                body: { motivo },
            });
            const data = response?.data || {};

            if (data.comprobante && data.comprobante.codigo) {
                renderComprobante(modal, data.comprobante, cancelState?.onChanged);
            } else {
                toast('success', response?.message || 'Cita cancelada.');
                closeModal(modal);
                cancelState?.onChanged?.();
            }
        } catch (error) {
            if (errorNode) {
                errorNode.textContent = apiErrorMessage(error);
                errorNode.hidden = false;
            }
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(button, false);
        }
    });
}

function renderComprobante(modal, comprobante, onChanged) {
    const body = $('[data-cancelacion-body]', modal);
    const title = $('[data-cancelacion-title]', modal);

    if (title) {
        title.textContent = 'Comprobante de reembolso';
    }

    if (!body) {
        return;
    }

    body.innerHTML = `
        <div class="cancel-comprobante">
            <div class="callout callout-success">
                <svg class="icon" aria-hidden="true"><use href="#i-check-circle"></use></svg>
                <p>Tu cita fue cancelada. Presenta este comprobante en recepción para recibir el reembolso.</p>
            </div>

            <div class="cancel-comprobante-code">
                <span>Comprobante</span>
                <strong>${escapeHtml(comprobante.codigo || '—')}</strong>
            </div>

            <dl class="cancel-summary-list">
                <div><dt>Paciente</dt><dd>${escapeHtml(comprobante.paciente || '—')}</dd></div>
                <div><dt>CI</dt><dd>${escapeHtml(comprobante.ci || '—')}</dd></div>
                <div><dt>Cita</dt><dd>${escapeHtml(comprobante.cita_codigo || '—')}</dd></div>
                <div><dt>Fecha de la cita</dt><dd>${escapeHtml(formatDateNumeric(comprobante.fecha_cita))} · ${escapeHtml(formatTimeValue(comprobante.hora_cita))}</dd></div>
                <div><dt>Monto a devolver</dt><dd><strong>${escapeHtml(formatMoney(comprobante.monto))}</strong></dd></div>
            </dl>

            <p class="text-muted" style="font-size:12.5px;margin:12px 0 0;">${escapeHtml(comprobante.instrucciones || '')}</p>

            <div class="row-actions" style="margin-top:14px;">
                <button class="btn btn-primary btn-sm" type="button" data-comprobante-cerrar>Entendido</button>
            </div>
        </div>
    `;

    $('[data-comprobante-cerrar]', modal)?.addEventListener('click', () => {
        closeModal(modal);
        onChanged?.();
    });
}

async function openCancelacion(cita, onChanged) {
    const modal = ensureCancelModal();
    const body = $('[data-cancelacion-body]', modal);
    const title = $('[data-cancelacion-title]', modal);

    cancelState = null;

    if (title) {
        title.textContent = 'Cancelar cita';
    }

    if (body) {
        body.innerHTML = `
            <div class="table-loading">
                <div class="skeleton skeleton-line"></div>
                <div class="skeleton skeleton-line"></div>
                <div class="skeleton skeleton-line"></div>
            </div>
        `;
    }

    openModal(modal);

    try {
        const response = await apiFetch(`/api/citas/${cita.id}/cancelacion/opciones`);
        renderCancelacion(modal, cita, response?.data || {}, onChanged);
    } catch (error) {
        if (body) {
            body.innerHTML = `
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-x-circle"></use></svg></span>
                    <h2>No pudimos cargar las opciones</h2>
                    <p>${escapeHtml(apiErrorMessage(error))}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-modal-close>Volver</button>
                </div>
            `;
        }
        toast('error', apiErrorMessage(error));
    }
}

export function renderAppointmentList(container, citas, { emptyMessage = 'No hay citas para mostrar.', allowCancel = true, onChanged = null } = {}) {
    if (!container) {
        return;
    }

    if (!Array.isArray(citas) || citas.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                <h2>Sin citas</h2>
                <p>${escapeHtml(emptyMessage)}</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `<div class="history-timeline">${citas.map((cita) => appointmentCardHtml(cita, { allowCancel })).join('')}</div>`;

    container.querySelectorAll('[data-cancelar-cita]').forEach((button) => {
        button.addEventListener('click', () => {
            const cita = citas.find((item) => Number(item.id) === Number(button.dataset.cancelarCita));
            if (!cita) {
                return;
            }

            openCancelacion(cita, onChanged);
        });
    });
}

export function vitalsHtml(signs) {
    if (!signs || typeof signs !== 'object') {
        return '';
    }

    const labels = { pa: 'PA', fc: 'FC', temp: 'Temp.', peso: 'Peso', spo2: 'SpO₂', talla: 'Talla' };
    const units = { fc: 'lpm', temp: '°C', peso: 'kg', spo2: '%', talla: 'm' };
    const items = Object.entries(labels)
        .filter(([key]) => signs[key] !== undefined && signs[key] !== null && signs[key] !== '')
        .map(([key, label]) => {
            const unit = units[key] ? ` ${units[key]}` : '';
            return `<span class="badge badge-muted">${escapeHtml(label)}: ${escapeHtml(signs[key])}${escapeHtml(unit)}</span>`;
        });

    return items.length > 0 ? `<div class="history-badges" style="margin-top:8px;">${items.join('')}</div>` : '';
}

export function renderHistoryList(container, consultas, { emptyMessage = 'Todavía no tienes atenciones registradas.' } = {}) {
    if (!container) {
        return;
    }

    if (!Array.isArray(consultas) || consultas.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-file"></use></svg></span>
                <h2>Sin atenciones</h2>
                <p>${escapeHtml(emptyMessage)}</p>
            </div>
        `;
        return;
    }

    container.innerHTML = `<div class="history-timeline">${consultas.map((consulta) => {
        const fecha = parseDateOnly(consulta.fecha);
        const day = fecha ? String(fecha.getDate()).padStart(2, '0') : '—';
        const month = fecha
            ? ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][fecha.getMonth()]
            : '';
        const lines = [];

        if (consulta.diagnostico) {
            lines.push(`<p><strong>Diagnóstico:</strong> ${escapeHtml(consulta.diagnostico)}</p>`);
        }
        if (consulta.tratamiento) {
            lines.push(`<p><strong>Tratamiento:</strong> ${escapeHtml(consulta.tratamiento)}</p>`);
        }
        if (consulta.indicaciones) {
            lines.push(`<p><strong>Indicaciones:</strong> ${escapeHtml(consulta.indicaciones)}</p>`);
        }

        return `
            <article class="history-cita">
                <div class="history-cita-date">
                    <strong>${escapeHtml(day)}</strong>
                    <span>${escapeHtml(month)}</span>
                </div>
                <div class="history-cita-body">
                    <div class="history-cita-top">
                        <strong>${escapeHtml(consulta.motivo || 'Consulta médica')}</strong>
                        ${consulta.medico?.nombre_completo ? `<span class="badge badge-muted">${escapeHtml(consulta.medico.nombre_completo)}</span>` : ''}
                    </div>
                    <small>${escapeHtml(formatDateNumeric(consulta.fecha))}${consulta.cita?.hora_inicio ? ` · ${escapeHtml(formatTimeValue(consulta.cita.hora_inicio))}` : ''}</small>
                    ${lines.join('')}
                    ${vitalsHtml(consulta.signos_vitales)}
                </div>
            </article>
        `;
    }).join('')}</div>`;
}

export function renderPatientSummary(container, paciente) {
    if (!container || !paciente) {
        return;
    }

    const edad = paciente.edad !== null && paciente.edad !== undefined
        ? `${paciente.edad} años`
        : 'Edad no registrada';
    const sexo = paciente.sexo === 'femenino' ? 'Femenino' : (paciente.sexo === 'masculino' ? 'Masculino' : '—');

    container.innerHTML = `
        <div class="history-profile">
            <span class="avatar">${escapeHtml(initials(patientName(paciente)))}</span>
            <div class="history-identity">
                <h3>${escapeHtml(patientName(paciente))}</h3>
                <p>CI ${escapeHtml(paciente.ci || '—')} · ${escapeHtml(edad)} · ${escapeHtml(sexo)}</p>
                <div class="history-badges">
                    ${paciente.telefono ? `<span class="badge badge-muted">${escapeHtml(paciente.telefono)}</span>` : ''}
                    ${paciente.email ? `<span class="badge badge-muted">${escapeHtml(paciente.email)}</span>` : ''}
                    ${paciente.tipo_sangre ? `<span class="badge badge-accent">Sangre ${escapeHtml(paciente.tipo_sangre)}</span>` : ''}
                </div>
            </div>
        </div>
    `;
}

export function nextAppointment(citas) {
    return sortAppointments(citas).find(isUpcoming) || null;
}

export function setText(selector, value) {
    const node = typeof selector === 'string' ? $(selector) : selector;
    if (node) {
        node.textContent = String(value ?? '');
    }
}
