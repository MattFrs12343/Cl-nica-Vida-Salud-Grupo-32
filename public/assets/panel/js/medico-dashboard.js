import {
    $,
    apiErrorMessage,
    apiFetch,
    escapeHtml,
    formatDateNumeric,
    formatTimeValue,
    patientName,
    setLoading,
    toast,
    todayISO,
} from './app.js';
import {
    doctorSpecialties,
    loadDoctor,
    renderAgendaList,
    renderDoctorKpis,
    requireMedicoId,
} from './medico-shared.js';

const kpis = $('[data-doctor-kpis]');
const agenda = $('[data-doctor-agenda]');
const next = $('[data-doctor-next]');
const meta = $('[data-doctor-meta]');
const dateLabel = $('[data-doctor-date]');
const refresh = $('[data-doctor-refresh]');

function renderNext(data) {
    if (!next) {
        return;
    }

    const cita = data?.siguiente_cita;

    if (!cita) {
        next.innerHTML = `
            <div class="empty-state" style="padding:18px 12px;">
                <span class="empty-icon" aria-hidden="true"><svg class="icon"><use href="#i-sun"></use></svg></span>
                <h2>Sin pendientes</h2>
                <p>No quedan citas por atender en el resto de tu jornada.</p>
            </div>
        `;
        return;
    }

    next.innerHTML = `
        <div class="appointment-summary">
            <div class="appointment-summary-top">
                <strong>${escapeHtml(cita.paciente || 'Paciente')}</strong>
                <span class="badge badge-info">Siguiente</span>
            </div>
            <p>${escapeHtml(cita.motivo || 'Consulta médica')}</p>
            <div class="appointment-summary-meta">
                <span>${escapeHtml(formatTimeValue(cita.hora))}</span>
                <span>${escapeHtml(formatDateNumeric(data.fecha || todayISO()))}</span>
            </div>
        </div>
        <a class="btn btn-primary btn-block btn-sm" style="margin-top:10px;" href="/medico/agenda">
            Preparar la consulta
            <span class="btn-icon" aria-hidden="true"><svg class="icon"><use href="#i-arrow-right"></use></svg></span>
        </a>
    `;
}

async function load() {
    if (!requireMedicoId()) {
        if (agenda) {
            agenda.innerHTML = '<div class="agenda-empty"><p>No hay un médico asociado a tu cuenta.</p></div>';
        }
        return;
    }

    setLoading(refresh, true);

    try {
        const [doctor, response] = await Promise.all([
            loadDoctor(),
            apiFetch(`/api/dashboard/medico?fecha=${encodeURIComponent(todayISO())}`),
        ]);
        const data = response?.data || {};

        if (meta) {
            meta.textContent = doctor
                ? `${doctor.codigo || ''}${doctor.codigo ? ' · ' : ''}${doctorSpecialties(doctor)}`
                : 'Jornada de hoy';
        }

        if (dateLabel) {
            dateLabel.textContent = formatDateNumeric(data.fecha || todayISO());
        }

        renderDoctorKpis(kpis, data);
        renderNext(data);
        renderAgendaList(agenda, data.agenda || [], {
            emptyMessage: 'No tienes citas agendadas para hoy.',
            onChanged: load,
        });
    } catch (error) {
        const message = apiErrorMessage(error);
        if (agenda) {
            agenda.innerHTML = `
                <div class="error-state">
                    <span class="error-icon" aria-hidden="true"><svg class="icon"><use href="#i-alert"></use></svg></span>
                    <h2>No pudimos cargar tu jornada</h2>
                    <p>${escapeHtml(message)}</p>
                    <button class="btn btn-ghost btn-sm" type="button" data-doctor-retry>
                        Reintentar
                        <svg class="icon" aria-hidden="true"><use href="#i-refresh"></use></svg>
                    </button>
                </div>
            `;
            agenda.querySelector('[data-doctor-retry]')?.addEventListener('click', load);
        }
        toast('error', message);
    } finally {
        setLoading(refresh, false);
    }
}

refresh?.addEventListener('click', load);
load();
