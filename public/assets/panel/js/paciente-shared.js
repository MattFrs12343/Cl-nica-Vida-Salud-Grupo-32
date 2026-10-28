import {
    $,
    PANEL,
    apiErrorMessage,
    apiFetch,
    confirmAction,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    initials,
    parseDateOnly,
    patientName,
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
        button.addEventListener('click', async () => {
            const cita = citas.find((item) => Number(item.id) === Number(button.dataset.cancelarCita));
            if (!cita) {
                return;
            }

            const confirmed = await confirmAction({
                title: 'Cancelar cita',
                message: `¿Confirmas que deseas cancelar tu cita del ${formatDateNumeric(cita.fecha)} a las ${formatTimeValue(cita.hora_inicio || cita.hora)}? Se registrará como cancelación solicitada por el paciente.`,
                confirmLabel: 'Sí, cancelar',
                cancelLabel: 'Volver',
                danger: true,
            });

            if (!confirmed) {
                return;
            }

            try {
                const response = await apiFetch(`/api/citas/${cita.id}/cancelar`, {
                    method: 'PATCH',
                    body: { motivo: 'Cancelación solicitada por el paciente' },
                });
                toast('success', response?.message || 'Cita cancelada.');
                onChanged?.();
            } catch (error) {
                toast('error', apiErrorMessage(error));
            }
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
