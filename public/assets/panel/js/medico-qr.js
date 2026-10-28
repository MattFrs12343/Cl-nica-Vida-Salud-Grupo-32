import {
    $,
    apiErrorMessage,
    apiFetch,
    escapeHtml,
    formatDateNumeric,
    setLoading,
    toast,
} from './app.js';
import {
    MEDICO_ID,
    doctorSpecialties,
    loadDoctor,
    requireMedicoId,
} from './medico-shared.js';

const image = $('[data-qr-image]');
const empty = $('[data-qr-empty]');
const preview = $('[data-qr-preview]');
const fileInput = $('[data-qr-file]');
const form = $('[data-qr-form]');
const nameNode = $('[data-qr-name]');
const specialtiesNode = $('[data-qr-specialties]');
const codeNode = $('[data-qr-code]');
const metaNode = $('[data-qr-meta]');
const download = $('[data-qr-download]');
const print = $('[data-qr-print]');
const refresh = $('[data-qr-refresh]');
const fileName = $('[data-qr-file-name]');
const submit = form?.querySelector('[data-qr-submit]')
    ?? form?.querySelector('button[type="submit"]');

const emptyDefault = empty?.innerHTML || '';

let doctor = null;
let previewUrl = null;

function setImage(url) {
    if (image) {
        if (url) {
            image.src = url;
            image.hidden = false;
        } else {
            image.removeAttribute('src');
            image.hidden = true;
        }
    }

    if (empty) {
        empty.hidden = Boolean(url);
        if (url && empty.innerHTML !== emptyDefault) {
            empty.innerHTML = emptyDefault;
        }
    }

    if (download) {
        download.hidden = !url;
        if (url) {
            download.href = url;
            download.setAttribute('download', `qr-${doctor?.codigo || MEDICO_ID}.${url.split('.').pop() || 'png'}`);
        }
    }

    if (print) {
        print.hidden = !url;
    }
}

function renderDoctor(data) {
    doctor = data;

    if (nameNode) {
        nameNode.textContent = doctor
            ? `Dr(a). ${doctor.nombres || ''} ${doctor.apellidos || ''}`.trim()
            : 'Médico';
    }

    if (specialtiesNode) {
        specialtiesNode.textContent = doctorSpecialties(doctor);
    }

    if (codeNode) {
        codeNode.textContent = doctor?.codigo || '—';
    }

    if (metaNode) {
        const partes = [];
        if (doctor?.email) {
            partes.push(doctor.email);
        }
        if (doctor?.telefono) {
            partes.push(doctor.telefono);
        }
        if (doctor?.updated_at) {
            partes.push(`Actualizado ${formatDateNumeric(doctor.updated_at)}`);
        }
        metaNode.textContent = partes.join(' · ');
    }

    setImage(doctor?.qr_url || '');
}

async function loadQr() {
    if (!requireMedicoId()) {
        return;
    }

    try {
        renderDoctor(await loadDoctor());
    } catch (error) {
        toast('error', apiErrorMessage(error));
    }
}

function resetPreview() {
    if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }
    if (preview) {
        preview.removeAttribute('src');
        preview.hidden = true;
    }
    if (fileName) {
        fileName.textContent = '';
        fileName.hidden = true;
    }
    if (submit) {
        submit.disabled = true;
    }
}

fileInput?.addEventListener('change', () => {
    resetPreview();
    const file = fileInput.files?.[0];

    if (!file) {
        return;
    }

    if (!['image/png', 'image/jpeg'].includes(file.type)) {
        toast('error', 'El archivo debe ser una imagen PNG o JPG.');
        fileInput.value = '';
        resetPreview();
        return;
    }

    if (file.size > 1_048_576) {
        toast('error', 'La imagen no puede superar 1 MB.');
        fileInput.value = '';
        resetPreview();
        return;
    }

    previewUrl = URL.createObjectURL(file);
    if (preview) {
        preview.src = previewUrl;
        preview.hidden = false;
    }
    if (fileName) {
        const kb = Math.max(1, Math.round(file.size / 1024));
        fileName.textContent = `${file.name} · ${kb} KB`;
        fileName.hidden = false;
    }
    if (submit) {
        submit.disabled = false;
    }
});

form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const file = fileInput?.files?.[0];
    if (!file) {
        toast('error', 'Selecciona una imagen PNG o JPG.');
        fileInput?.focus();
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    const data = new FormData();
    data.append('qr', file);

    setLoading(button, true);

    try {
        const response = await apiFetch(`/api/medicos/${MEDICO_ID}/qr`, {
            method: 'POST',
            body: data,
        });
        const actualizado = response?.data?.medico || doctor;
        if (actualizado) {
            renderDoctor({
                ...actualizado,
                qr_url: response?.data?.qr_url || actualizado.qr_url || '',
            });
        }
        resetPreview();
        if (fileInput) {
            fileInput.value = '';
        }
        toast('success', response?.message || 'Código QR actualizado.');
    } catch (error) {
        toast('error', apiErrorMessage(error));
    } finally {
        setLoading(button, false);
        if (submit) {
            submit.disabled = !fileInput?.files?.length;
        }
    }
});

refresh?.addEventListener('click', loadQr);

print?.addEventListener('click', () => {
    window.print();
});

image?.addEventListener('error', () => {
    setImage('');
    if (empty) {
        empty.hidden = false;
        empty.innerHTML = `
            <svg class="icon" aria-hidden="true"><use href="#i-qr"></use></svg>
            <p>No se pudo cargar la imagen. Vuelve a subir tu código QR.</p>
        `;
    }
});

if (requireMedicoId()) {
    loadQr();
}
