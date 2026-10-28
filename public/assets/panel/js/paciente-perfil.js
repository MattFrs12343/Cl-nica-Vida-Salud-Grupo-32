import {
    $,
    apiErrorMessage,
    apiFetch,
    clearFieldErrors,
    escapeHtml,
    formatDateNumeric,
    initials,
    patientName,
    renderFieldErrors,
    setLoading,
    sexLabel,
    toast,
} from './app.js';
import {
    loadProfile,
    renderPatientSummary,
} from './paciente-shared.js';

const form = $('[data-perfil-form]');
const submit = $('[data-perfil-submit]');
const summary = $('[data-perfil-summary]');
const identity = $('[data-perfil-identity]');

const PROFILE_FIELDS = [
    'telefono',
    'email',
    'direccion',
    'tipo_sangre',
    'alergias',
    'enfermedades_cronicas',
    'medicamentos',
    'antecedentes',
    'emergencia_nombre',
    'emergencia_parentesco',
    'emergencia_telefono',
];

function fillForm(paciente) {
    if (!form || !paciente) {
        return;
    }

    PROFILE_FIELDS.forEach((name) => {
        const field = form.elements.namedItem(name);
        if (field) {
            field.value = paciente[name] ?? '';
        }
    });
}

function renderIdentity(paciente) {
    if (!identity || !paciente) {
        return;
    }

    const edad = paciente.edad !== null && paciente.edad !== undefined
        ? `${paciente.edad} años`
        : 'Edad no registrada';

    identity.innerHTML = `
        <div class="history-profile">
            <span class="avatar">${escapeHtml(initials(patientName(paciente)))}</span>
            <div class="history-identity">
                <h3>${escapeHtml(patientName(paciente))}</h3>
                <p>CI ${escapeHtml(paciente.ci || '—')} · ${escapeHtml(formatDateNumeric(paciente.fecha_nacimiento))} · ${escapeHtml(sexLabel(paciente.sexo))}</p>
                <div class="history-badges">
                    <span class="badge badge-muted">${escapeHtml(edad)}</span>
                    ${paciente.tipo_sangre ? `<span class="badge badge-accent">Sangre ${escapeHtml(paciente.tipo_sangre)}</span>` : ''}
                </div>
            </div>
        </div>
        <p class="text-muted" style="margin-top:10px;font-size:13px;">La cédula, nombres, apellidos, fecha de nacimiento y sexo solo pueden cambiarse en recepción.</p>
    `;
}

async function load() {
    try {
        const paciente = await loadProfile();
        fillForm(paciente);
        renderIdentity(paciente);
        renderPatientSummary(summary, paciente);
    } catch (error) {
        toast('error', apiErrorMessage(error));
        if (identity) {
            identity.innerHTML = '<p class="text-muted">No pudimos cargar tu ficha. Recarga la página para intentarlo de nuevo.</p>';
        }
        if (summary) {
            summary.innerHTML = '<p class="text-muted">Sin datos disponibles.</p>';
        }
    }
}

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFieldErrors(form);
    setLoading(submit, true);

    const body = {};
    PROFILE_FIELDS.forEach((name) => {
        const field = form.elements.namedItem(name);
        body[name] = field ? String(field.value || '').trim() : '';
    });

    try {
        const response = await apiFetch('/api/me/perfil', {
            method: 'PUT',
            body,
        });

        const paciente = response?.data || null;
        if (paciente) {
            fillForm(paciente);
            renderIdentity(paciente);
            renderPatientSummary(summary, paciente);
        }

        toast('success', response?.message || 'Perfil actualizado.');
    } catch (error) {
        if (error.fields && Object.keys(error.fields).length > 0) {
            renderFieldErrors(form, error.fields);
        }
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(submit, false);
    }
});

load();
