import {
    $,
    apiErrorMessage,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    toast,
    todayISO,
} from './app.js';
import {
    isPast,
    isUpcoming,
    loadAppointments,
    loadHistory,
    loadProfile,
    nextAppointment,
    renderAppointmentList,
    renderPatientSummary,
    setText,
    sortAppointments,
} from './paciente-shared.js';

const elements = {
    firstName: $('[data-patient-first-name]'),
    meta: $('[data-patient-meta]'),
    profile: $('[data-patient-profile]'),
    kpis: $('[data-patient-kpis]'),
    next: $('[data-patient-next]'),
    appointments: $('[data-patient-appointments]'),
    appointmentsTitle: $('[data-patient-appointments-title]'),
    date: $('[data-patient-date]'),
};

let loading = false;

function setKpi(key, value) {
    const node = document.querySelector(`[data-patient-kpi="${key}"]`);
    if (node) {
        node.textContent = String(value);
    }
}

function renderNext(cita, onChanged) {
    if (!elements.next) {
        return;
    }

    if (!cita) {
        elements.next.innerHTML = `
            <div class="empty-state">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-calendar"></use></svg></span>
                <h2>Sin próxima cita</h2>
                <p>Cuando reserves una cita aparecerá aquí.</p>
                <a class="btn btn-primary btn-sm" href="/reservar">Reservar cita</a>
            </div>
        `;
        return;
    }

    renderAppointmentList(elements.next, [cita], {
        allowCancel: true,
        onChanged,
    });

    elements.next.insertAdjacentHTML('afterbegin', `
        <div class="appointment-summary">
            <span class="appointment-date">${escapeHtml(formatDateNumeric(cita.fecha))}</span>
            <strong>${escapeHtml(formatTimeValue(cita.hora_inicio || cita.hora))}</strong>
            <span>${escapeHtml(cita.medico?.nombre_completo || cita.medico?.nombre || 'Médico')}</span>
            ${cita.especialidad?.nombre ? `<small>${escapeHtml(cita.especialidad.nombre)}</small>` : ''}
        </div>
    `);
}

async function load() {
    if (loading) {
        return;
    }

    loading = true;
    setText(elements.date, formatDateNumeric(todayISO()));

    try {
        const [profileResult, appointmentsResult, historyResult] = await Promise.allSettled([
            loadProfile(),
            loadAppointments(),
            loadHistory(),
        ]);

        if (profileResult.status === 'rejected') {
            toast('error', apiErrorMessage(profileResult.reason));
        }

        if (appointmentsResult.status === 'rejected') {
            toast('error', apiErrorMessage(appointmentsResult.reason));
        }

        if (historyResult.status === 'rejected') {
            toast('error', apiErrorMessage(historyResult.reason));
        }

        const paciente = profileResult.status === 'fulfilled'
            ? profileResult.value
            : (appointmentsResult.status === 'fulfilled' ? appointmentsResult.value.paciente : null);
        const citas = appointmentsResult.status === 'fulfilled' ? appointmentsResult.value.citas : [];
        const consultas = historyResult.status === 'fulfilled' ? historyResult.value.consultas : [];

        const firstName = String(paciente?.nombre_completo || paciente?.nombre || 'paciente').trim().split(/\s+/)[0] || 'paciente';
        setText(elements.firstName, firstName);

        if (paciente) {
            const edad = paciente.edad !== null && paciente.edad !== undefined
                ? `${paciente.edad} años`
                : 'Edad no registrada';
            setText(elements.meta, `${edad}${paciente.ci ? ` · CI ${paciente.ci}` : ''} · Tu información de salud, siempre a mano.`);
            renderPatientSummary(elements.profile, paciente);
        } else {
            setText(elements.meta, 'Tu información de salud, siempre a mano.');
            if (elements.profile) {
                elements.profile.innerHTML = '<p class="text-muted">No pudimos cargar tu ficha en este momento.</p>';
            }
        }

        const proximas = citas.filter(isUpcoming);
        const atendidas = citas.filter((cita) => String(cita.estado) === 'atendida');

        setKpi('proximas', proximas.length);
        setKpi('atendidas', atendidas.length);
        setKpi('consultas', consultas.length);
        setKpi('total', citas.length);

        const siguiente = nextAppointment(citas);
        renderNext(siguiente, load);

        const ordered = proximas.length > 0
            ? sortAppointments(proximas).slice(0, 4)
            : sortAppointments(citas.filter(isPast)).reverse().slice(0, 4);

        setText(elements.appointmentsTitle, proximas.length > 0 ? 'Tus próximas citas' : 'Citas recientes');

        renderAppointmentList(elements.appointments, ordered, {
            emptyMessage: 'Aún no tienes citas registradas.',
            allowCancel: true,
            onChanged: load,
        });
    } finally {
        loading = false;
    }
}

$('[data-patient-refresh]')?.addEventListener('click', load);

load();
