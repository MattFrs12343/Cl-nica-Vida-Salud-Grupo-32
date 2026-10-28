import {
    $,
    PANEL,
    addDays,
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
import { normalizeData } from './recepcion-shared.js';

export { PANEL, addDays, todayISO, normalizeData };

export const MEDICO_ID = Number(PANEL.medicoId || 0);

let doctorPromise = null;

export function requireMedicoId() {
    if (MEDICO_ID > 0) {
        return MEDICO_ID;
    }

    toast('error', 'Tu cuenta no tiene un médico asociado. Contacta al administrador.');
    return 0;
}

export async function loadDoctor() {
    if (!requireMedicoId()) {
        return null;
    }

    if (doctorPromise) {
        return doctorPromise;
    }

    doctorPromise = apiFetch(`/api/medicos/${MEDICO_ID}`)
        .then((response) => response?.data || null);

    return doctorPromise;
}

export function doctorSpecialties(doctor) {
    const names = (doctor?.especialidades || [])
        .map((item) => item.nombre)
        .filter(Boolean);

    return names.length > 0 ? names.join(' · ') : 'Sin especialidad asignada';
}

export function patientHref(patientId) {
    return `/medico/historia?paciente_id=${encodeURIComponent(patientId)}`;
}

export function consultationHref(citaId) {
    return `/medico/consultas?cita_id=${encodeURIComponent(citaId)}`;
}

export function pedidoHref(consultaId) {
    return `/medico/pedidos?consulta_id=${encodeURIComponent(consultaId)}`;
}

export function emptyAgendaHtml(message = 'No hay citas para este período.') {
    return `
        <div class="agenda-empty">
            <svg class="icon" aria-hidden="true"><use href="#i-calendar"></use></svg>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

export function agendaItemHtml(cita, { showDate = false } = {}) {
    const fecha = parseDateOnly(cita.fecha);
    const day = fecha ? String(fecha.getDate()).padStart(2, '0') : '—';
    const month = fecha
        ? ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][fecha.getMonth()]
        : '';
    const estado = String(cita.estado || '');
    const puedeAtender = ['programada', 'confirmada'].includes(estado);
    const puedeInasistencia = ['programada', 'confirmada'].includes(estado);
    const motivo = cita.motivo || 'Sin motivo registrado';
    const especialidad = cita.especialidad?.nombre || '';
    const pacienteNombre = typeof cita.paciente === 'string' ? cita.paciente : patientName(cita.paciente);
    const hora = cita.hora || cita.hora_inicio;

    const actions = [];

    if (puedeAtender) {
        actions.push(`
            <a class="row-action" href="${consultationHref(cita.id)}" title="Atender" aria-label="Atender a ${escapeHtml(pacienteNombre)}">
                <svg class="icon" aria-hidden="true"><use href="#i-stethoscope"></use></svg>
            </a>
        `);
    }

    if (puedeInasistencia) {
        actions.push(`
            <button class="row-action is-danger" type="button" data-inasistencia="${escapeHtml(cita.id)}"
                    title="Registrar inasistencia" aria-label="Registrar inasistencia">
                <svg class="icon" aria-hidden="true"><use href="#i-x-circle"></use></svg>
            </button>
        `);
    }

    if (cita.paciente_id) {
        actions.push(`
            <a class="row-action" href="${patientHref(cita.paciente_id)}" title="Ver historia" aria-label="Ver historia clínica">
                <svg class="icon" aria-hidden="true"><use href="#i-file"></use></svg>
            </a>
        `);
    }

    return `
        <div class="agenda-item">
            <div class="agenda-time">
                ${showDate ? `<small>${escapeHtml(day)} ${escapeHtml(month)}</small>` : ''}
                <span>${escapeHtml(formatTimeValue(hora))}</span>
                <small>${escapeHtml(cita.codigo || '')}</small>
            </div>
            <div class="agenda-body${cita.es_reconsulta ? ' is-reconsulta' : ''}">
                <div class="agenda-main">
                    <div class="agenda-main-top">
                        <strong>${escapeHtml(pacienteNombre)}</strong>
                        ${statusBadge(estado)}
                        ${cita.es_prioritaria ? '<span class="badge badge-accent">Prioritaria</span>' : ''}
                    </div>
                    <p>${escapeHtml(motivo)}${especialidad ? ` · <span class="mono">${escapeHtml(especialidad)}</span>` : ''}</p>
                </div>
                <div class="agenda-actions">${actions.join('')}</div>
            </div>
        </div>
    `;
}

export function renderAgendaList(container, citas, { showDate = false, emptyMessage = '', onChanged = null } = {}) {
    if (!container) {
        return;
    }

    if (!Array.isArray(citas) || citas.length === 0) {
        container.innerHTML = emptyAgendaHtml(emptyMessage || 'No hay citas para este período.');
        return;
    }

    container.innerHTML = citas
        .map((cita) => agendaItemHtml(cita, { showDate }))
        .join('');

    container.querySelectorAll('[data-inasistencia]').forEach((button) => {
        button.addEventListener('click', async () => {
            const cita = citas.find((item) => Number(item.id) === Number(button.dataset.inasistencia));

            if (!cita) {
                return;
            }

            const confirmed = await confirmAction({
                title: 'Registrar inasistencia',
                message: `¿Confirmas que ${typeof cita.paciente === 'string' ? cita.paciente : patientName(cita.paciente)} no se presentó a su cita?`,
                confirmLabel: 'Sí, inasistencia',
                danger: true,
            });

            if (!confirmed) {
                return;
            }

            try {
                const response = await apiFetch(`/api/citas/${cita.id}/inasistencia`, { method: 'PATCH' });
                toast('success', response?.message || 'Inasistencia registrada.');
                onChanged?.();
            } catch (error) {
                toast('error', apiErrorMessage(error));
            }
        });
    });
}

export function vitalSignsHtml(signs) {
    if (!signs || typeof signs !== 'object') {
        return '';
    }

    const labels = {
        pa: 'PA',
        fc: 'FC',
        temp: 'Temp.',
        peso: 'Peso',
        spo2: 'SpO₂',
        talla: 'Talla',
    };
    const units = {
        fc: 'lpm',
        temp: '°C',
        peso: 'kg',
        spo2: '%',
        talla: 'm',
    };

    const items = Object.entries(labels)
        .filter(([key]) => signs[key] !== undefined && signs[key] !== null && signs[key] !== '')
        .map(([key, label]) => {
            const value = signs[key];
            const unit = units[key] ? ` ${units[key]}` : '';
            return `<span class="badge badge-muted">${escapeHtml(label)}: ${escapeHtml(value)}${escapeHtml(unit)}</span>`;
        });

    if (items.length === 0) {
        return '';
    }

    return `<div class="history-badges" style="margin-top:8px;">${items.join('')}</div>`;
}

export function consultaCardHtml(consulta) {
    const fecha = parseDateOnly(consulta.fecha || consulta.cita?.fecha);
    const day = fecha ? String(fecha.getDate()).padStart(2, '0') : '—';
    const month = fecha
        ? ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][fecha.getMonth()]
        : '';
    const estado = consulta.cita?.estado || '';
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
                    ${estado ? statusBadge(estado) : ''}
                    ${consulta.especialidad?.nombre ? `<span class="badge badge-muted">${escapeHtml(consulta.especialidad.nombre)}</span>` : ''}
                </div>
                <small>${escapeHtml(formatDateNumeric(consulta.fecha || consulta.cita?.fecha))}${consulta.cita?.hora_inicio ? ` · ${escapeHtml(formatTimeValue(consulta.cita.hora_inicio))}` : ''}</small>
                ${lines.join('')}
                ${vitalSignsHtml(consulta.signos_vitales)}
                <div class="row-actions" style="margin-top:8px;">
                    <a class="btn btn-ghost btn-sm" href="${pedidoHref(consulta.id)}">
                        <svg class="icon" aria-hidden="true"><use href="#i-ticket"></use></svg>
                        Emitir receta u orden
                    </a>
                </div>
            </div>
        </article>
    `;
}

export function renderConsultasTimeline(container, consultas, { emptyMessage = 'Sin atenciones registradas.' } = {}) {
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

    container.innerHTML = `<div class="history-timeline">${consultas.map(consultaCardHtml).join('')}</div>`;
}

export function renderPatientProfile(container, paciente, { consultas = 0, citas = 0 } = {}) {
    if (!container || !paciente) {
        return;
    }

    const edad = paciente.edad ? `${paciente.edad} años` : 'Edad no registrada';
    const sexo = paciente.sexo === 'femenino' ? 'Femenino' : (paciente.sexo === 'masculino' ? 'Masculino' : '—');
    const sangre = paciente.tipo_sangre ? ` · ${paciente.tipo_sangre}` : '';

    container.innerHTML = `
        <div class="history-profile">
            <span class="avatar">${escapeHtml(initials(patientName(paciente)))}</span>
            <div class="history-identity">
                <h3>${escapeHtml(patientName(paciente))}</h3>
                <p>CI ${escapeHtml(paciente.ci || '—')} · ${escapeHtml(edad)} · ${escapeHtml(sexo)}${escapeHtml(sangre)}</p>
                <div class="history-badges">
                    ${paciente.telefono ? `<span class="badge badge-muted">${escapeHtml(paciente.telefono)}</span>` : ''}
                    ${paciente.alergias ? `<span class="badge badge-accent">Alergias: ${escapeHtml(paciente.alergias)}</span>` : ''}
                </div>
            </div>
            <div class="history-count">
                <strong>${escapeHtml(consultas)}</strong>
                <span>Atenciones</span>
            </div>
            <div class="history-count">
                <strong>${escapeHtml(citas)}</strong>
                <span>Citas</span>
            </div>
        </div>
    `;
}

export function renderDoctorKpis(root, data) {
    if (!root) {
        return;
    }

    const values = {
        total: data?.citas_hoy ?? 0,
        confirmadas: data?.en_espera ?? 0,
        atendidas: data?.atendidas ?? 0,
        inasistencias: data?.inasistencias ?? 0,
    };

    Object.entries(values).forEach(([key, value]) => {
        const node = root.querySelector(`[data-doctor-kpi="${key}"]`);
        if (node) {
            node.textContent = String(value);
        }
    });
}

export function weekRange(value) {
    const date = parseDateOnly(value) || parseDateOnly(todayISO()) || new Date();
    const day = date.getDay();
    const diffToMonday = day === 0 ? -6 : 1 - day;
    const monday = new Date(date);
    monday.setDate(monday.getDate() + diffToMonday);

    return {
        desde: monday.toISOString().slice(0, 10),
        hasta: addDays(monday, 6),
    };
}

export function setText(selector, value) {
    const node = typeof selector === 'string' ? $(selector) : selector;
    if (node) {
        node.textContent = String(value ?? '');
    }
}
