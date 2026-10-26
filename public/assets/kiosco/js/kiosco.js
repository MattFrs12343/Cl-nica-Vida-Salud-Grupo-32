/**
 * Kiosco táctil de fichas — flujo completo en el cliente.
 *
 * El token HMAC se recibe embebido desde el servidor y se renueva contra
 * /kiosco/token. Ningún dato sensible del dispositivo viaja en el HTML.
 */
(() => {
    'use strict';

    const app = document.getElementById('kiosk-app');
    if (!app) {
        return;
    }

    const API = (app.dataset.apiBase || '/api/kiosco').replace(/\/$/, '');
    const IMG_BASE = (app.dataset.imgBase || '/assets/kiosco/img').replace(/\/$/, '');
    const TOKEN_URL = app.dataset.tokenUrl || '/kiosco/token';
    const DEMO_CI = app.dataset.demoCi || '10000001';
    const INACTIVITY_MS = 120000;
    const FICHA_RESET_MS = 30000;

    const $ = (selector, root = app) => root.querySelector(selector);
    const $$ = (selector, root = app) => Array.from(root.querySelectorAll(selector));

    class KioskError extends Error {
        constructor(message, code = 'ERROR', status = 0, fields = {}) {
            super(message);
            this.name = 'KioskError';
            this.code = code;
            this.status = status;
            this.fields = fields;
        }
    }

    const state = {
        token: '',
        tokenExpiresAt: 0,
        screen: 'welcome',
        busy: false,
        ci: '',
        paciente: null,
        especialidades: [],
        especialidad: null,
        medicos: [],
        medico: null,
        fechas: [],
        fecha: null,
        slots: [],
        slot: null,
        slotsRequest: 0,
        cita: null,
        ficha: null,
        inactivityTimer: null,
        fichaTimer: null,
        fichaDeadline: 0,
        verifyFrame: null,
        demo: false,
    };

    const els = {
        steps: $('[data-steps]'),
        clock: $('[data-clock]'),
        ciInput: $('[data-ci-input]'),
        ciError: $('[data-ci-error]'),
        registerForm: $('[data-register-form]'),
        registerCi: $('[data-register-ci]'),
        registerError: $('[data-register-error]'),
        patientGreeting: $('[data-patient-greeting]'),
        specialties: $('[data-specialties]'),
        specialtiesLoading: $('[data-specialties-loading]'),
        specialtiesError: $('[data-specialties-error]'),
        specialtiesErrorText: $('[data-specialties-error-text]'),
        specialtyBadge: $('[data-specialty-badge]'),
        doctors: $('[data-doctors]'),
        doctorsLoading: $('[data-doctors-loading]'),
        doctorsError: $('[data-doctors-error]'),
        doctorSubtitle: $('[data-doctor-subtitle]'),
        scheduleSubtitle: $('[data-schedule-subtitle]'),
        scheduleDoctor: $('[data-schedule-doctor]'),
        dates: $('[data-dates]'),
        slots: $('[data-slots]'),
        slotsLoading: $('[data-slots-loading]'),
        slotsEmpty: $('[data-slots-empty]'),
        selectionNote: $('[data-selection-note]'),
        confirmError: $('[data-confirm-error]'),
        confirmAvatar: $('[data-confirm-avatar]'),
        confirmPatient: $('[data-confirm-patient]'),
        confirmCi: $('[data-confirm-ci]'),
        confirmSpecialty: $('[data-confirm-specialty]'),
        confirmDoctor: $('[data-confirm-doctor]'),
        confirmDate: $('[data-confirm-date]'),
        confirmTime: $('[data-confirm-time]'),
        confirmDuration: $('[data-confirm-duration]'),
        confirmBtn: $('[data-action="confirm"]'),
        confirmLabel: $('[data-confirm-label]'),
        fichaContent: $('[data-ficha-content]'),
        fichaFallback: $('[data-ficha-fallback]'),
        fichaError: $('[data-ficha-error]'),
        fallbackCita: $('[data-fallback-cita]'),
        fichaQr: $('[data-ficha-qr]'),
        resetCount: $('[data-reset-count]'),
        resetProgress: $('[data-reset-progress]'),
        verifyTitle: $('[data-verify-title]'),
        verifyText: $('[data-verify-text]'),
        verifyProgress: $('[data-verify-progress]'),
        toast: $('[data-toast]'),
        overlay: $('[data-overlay]'),
        overlayText: $('[data-overlay-text]'),
    };

    /* Utilidades ---------------------------------------------------------- */

    const SVG_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

    const ICONS = {
        stethoscope: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v6a5 5 0 0 0 10 0V3"/><path d="M4 3h4M14 3h4"/><path d="M11 14v2a5 5 0 0 0 10 0v-2"/><circle cx="21" cy="12" r="2"/></svg>',
        child: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/><path d="M9 6h.01M15 6h.01"/></svg>',
        elderly: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="5" r="3"/><path d="M10 8v6l-3 7M10 14l4 7M7 11h7"/><path d="M17 4v16"/></svg>',
        heart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 22l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
        female: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M12 13v8M9 18h6"/></svg>',
        default: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>',
    };

    const PHOTOS = {
        stethoscope: 'especialidad-medicina-general.jpg',
        child: 'especialidad-pediatria.jpg',
        elderly: 'especialidad-geriatria.jpg',
        heart: 'especialidad-cardiologia.jpg',
        female: 'especialidad-ginecologia.jpg',
    };

    function esc(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function iconFor(especialidad) {
        const key = String(especialidad.icono || '').toLowerCase();
        if (ICONS[key]) {
            return ICONS[key];
        }

        const nombre = String(especialidad.nombre || '').toLowerCase();
        if (nombre.includes('pediatr')) return ICONS.child;
        if (nombre.includes('geriatr')) return ICONS.elderly;
        if (nombre.includes('cardio')) return ICONS.heart;
        if (nombre.includes('gineco')) return ICONS.female;
        if (nombre.includes('general')) return ICONS.stethoscope;

        return ICONS.default;
    }

    function photoFor(especialidad) {
        const key = String(especialidad.icono || '').toLowerCase();

        if (PHOTOS[key]) {
            return `${IMG_BASE}/${PHOTOS[key]}`;
        }

        const nombre = String(especialidad.nombre || '').toLowerCase();
        if (nombre.includes('pediatr')) return `${IMG_BASE}/${PHOTOS.child}`;
        if (nombre.includes('geriatr')) return `${IMG_BASE}/${PHOTOS.elderly}`;
        if (nombre.includes('cardio')) return `${IMG_BASE}/${PHOTOS.heart}`;
        if (nombre.includes('gineco')) return `${IMG_BASE}/${PHOTOS.female}`;
        if (nombre.includes('general')) return `${IMG_BASE}/${PHOTOS.stethoscope}`;

        return null;
    }

    function initials(nombre) {
        return String(nombre || '')
            .replace(/^Dr\(a\)\.\s*/i, '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('');
    }

    function firstName(nombre) {
        return String(nombre || '').split(/\s+/).filter(Boolean)[0] || '';
    }

    function isoDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function longDate(date) {
        return date.toLocaleDateString('es-BO', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function shortMonth(date) {
        return date.toLocaleDateString('es-BO', { month: 'short' }).replace('.', '');
    }

    function shortWeekday(date) {
        return date.toLocaleDateString('es-BO', { weekday: 'short' }).replace('.', '');
    }

    function today() {
        const date = new Date();
        date.setHours(0, 0, 0, 0);

        return date;
    }

    function sanitizeCi(raw) {
        const cleaned = String(raw || '').toUpperCase().replace(/[^0-9A-Z-]/g, '');
        const parts = cleaned.split('-');
        const digits = (parts.shift() || '').slice(0, 10);
        const complement = parts.join('').replace(/[^A-Z0-9]/g, '').slice(0, 2);

        return cleaned.includes('-') ? `${digits}-${complement}` : digits;
    }

    function setError(element, message) {
        if (!element) {
            return;
        }

        element.textContent = message || '';
        element.hidden = !message;
    }

    function clearErrors() {
        setError(els.ciError, '');
        setError(els.registerError, '');
        setError(els.confirmError, '');
        setError(els.fichaError, '');
    }

    function showToast(message, type = '') {
        if (!els.toast) {
            return;
        }

        els.toast.textContent = message;
        els.toast.className = 'kiosk-toast' + (type ? ` is-${type}` : '');
        els.toast.hidden = false;

        requestAnimationFrame(() => els.toast.classList.add('is-visible'));

        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => {
            els.toast.classList.remove('is-visible');
            window.setTimeout(() => {
                els.toast.hidden = true;
            }, 300);
        }, 4200);
    }

    function showOverlay(message) {
        if (!els.overlay) {
            return;
        }

        if (message && els.overlayText) {
            els.overlayText.textContent = message;
        }

        els.overlay.hidden = false;
    }

    function handleError(error, target) {
        if (error instanceof KioskError && (error.status === 401 || error.code === 'UNAUTHENTICATED')) {
            showOverlay('La sesión del kiosco expiró. Vuelve a intentarlo para continuar.');
            return;
        }

        const fields = error && error.fields ? Object.values(error.fields).filter(Boolean) : [];
        const message = fields.length > 0
            ? String(fields[0])
            : (error && error.message ? error.message : 'No se pudo completar la operación.');

        if (target) {
            setError(target, message);
        } else {
            showToast(message, 'error');
        }
    }

    async function withBusy(button, label, operation) {
        if (state.busy) {
            return undefined;
        }

        state.busy = true;
        const original = button ? button.innerHTML : '';

        if (button) {
            button.disabled = true;
            if (label) {
                button.textContent = label;
            }
        }

        try {
            return await operation();
        } finally {
            state.busy = false;
            if (button) {
                button.disabled = false;
                button.innerHTML = original;
            }
        }
    }

    /* Token y API --------------------------------------------------------- */

    function applyToken(data) {
        if (!data || !data.token) {
            throw new KioskError('No se pudo renovar la sesión del kiosco.', 'SESSION');
        }

        state.token = String(data.token);
        state.tokenExpiresAt = Date.now() + Number(data.expires_in || 600) * 1000;
    }

    async function refreshToken(force = false) {
        if (!force && state.token && Date.now() < state.tokenExpiresAt - 60000) {
            return;
        }

        const response = await fetch(TOKEN_URL, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new KioskError('No se pudo renovar la sesión del kiosco.', 'SESSION');
        }

        const payload = await response.json();
        applyToken(payload && payload.data ? payload.data : null);
    }

    async function api(path, options = {}) {
        await refreshToken();

        const send = () => fetch(API + path, {
            method: options.method || 'GET',
            headers: {
                Accept: 'application/json',
                'X-Kiosk-Token': state.token,
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: options.body ? JSON.stringify(options.body) : undefined,
            cache: 'no-store',
            credentials: 'same-origin',
        });

        let response = await send();

        if (response.status === 401) {
            await refreshToken(true);
            response = await send();
        }

        let payload = null;

        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (!response.ok) {
            const detail = payload && payload.error ? payload.error : {};

            throw new KioskError(
                detail.message || 'No se pudo completar la operación.',
                detail.code || 'ERROR',
                response.status,
                detail.fields || {}
            );
        }

        return payload ? payload.data : null;
    }

    /* Navegación ---------------------------------------------------------- */

    const STEP_BY_SCREEN = {
        specialty: 1,
        doctor: 2,
        schedule: 3,
        confirm: 3,
        ficha: 4,
    };

    function show(screen) {
        state.screen = screen;

        $$('.screen', app).forEach((section) => {
            const active = section.dataset.screen === screen;
            section.classList.toggle('is-active', active);
            section.hidden = !active;

            if (active) {
                section.scrollTop = 0;
            }
        });

        const step = STEP_BY_SCREEN[screen] || 0;

        if (els.steps) {
            els.steps.hidden = step === 0;
            $$('li', els.steps).forEach((item) => {
                const value = Number(item.dataset.step);
                item.classList.toggle('is-current', value === step);
                item.classList.toggle('is-done', value < step);
            });
        }

        stopInactivity();
        stopFichaTimer();

        if (screen === 'ficha') {
            startFichaTimer();
        } else if (screen !== 'welcome') {
            armInactivity();
        }

        clearErrors();
    }

    function resetFlow(silent = false) {
        window.clearTimeout(state.inactivityTimer);
        stopFichaTimer();
        cancelAnimationFrame(state.verifyFrame);

        state.paciente = null;
        state.especialidades = [];
        state.especialidad = null;
        state.medicos = [];
        state.medico = null;
        state.fechas = [];
        state.fecha = null;
        state.slots = [];
        state.slot = null;
        state.cita = null;
        state.ficha = null;
        state.ci = '';
        state.demo = false;

        if (els.ciInput) {
            els.ciInput.value = '';
        }

        if (els.registerForm) {
            els.registerForm.reset();
        }

        if (els.specialties) els.specialties.innerHTML = '';
        if (els.doctors) els.doctors.innerHTML = '';
        if (els.slots) els.slots.innerHTML = '';
        if (els.dates) els.dates.innerHTML = '';
        if (els.fichaQr) els.fichaQr.innerHTML = '';

        if (els.fichaContent) els.fichaContent.hidden = false;
        if (els.fichaFallback) els.fichaFallback.hidden = true;

        clearErrors();
        show('welcome');

        if (!silent) {
            showToast('Kiosco reiniciado.');
        }
    }

    function armInactivity() {
        window.clearTimeout(state.inactivityTimer);
        state.inactivityTimer = window.setTimeout(() => {
            if (state.busy) {
                armInactivity();
                return;
            }

            resetFlow(true);
            showToast('La sesión se reinició por inactividad.');
        }, INACTIVITY_MS);
    }

    function stopInactivity() {
        window.clearTimeout(state.inactivityTimer);
        state.inactivityTimer = null;
    }

    function noteActivity() {
        if (state.screen === 'welcome' || state.screen === 'ficha' || state.busy) {
            return;
        }

        armInactivity();
    }

    function startFichaTimer() {
        stopFichaTimer();
        state.fichaDeadline = Date.now() + FICHA_RESET_MS;

        const tick = () => {
            const remaining = Math.max(0, state.fichaDeadline - Date.now());
            const elapsed = FICHA_RESET_MS - remaining;

            if (els.resetCount) {
                els.resetCount.textContent = String(Math.ceil(remaining / 1000));
            }

            if (els.resetProgress) {
                els.resetProgress.style.width = `${Math.min(100, (elapsed / FICHA_RESET_MS) * 100)}%`;
            }

            if (remaining <= 0) {
                state.fichaTimer = null;
                resetFlow(true);
                showToast('Nueva atención. Puedes sacar otra ficha.');
                return;
            }
        };

        tick();
        state.fichaTimer = window.setInterval(tick, 250);
    }

    function stopFichaTimer() {
        if (state.fichaTimer) {
            window.clearInterval(state.fichaTimer);
            state.fichaTimer = null;
        }
    }

    /* Identificación ------------------------------------------------------ */

    function startFlow() {
        window.clearTimeout(state.inactivityTimer);

        if (els.toast) {
            els.toast.classList.remove('is-visible');
            els.toast.hidden = true;
        }

        state.paciente = null;
        state.especialidad = null;
        state.medico = null;
        state.slot = null;
        state.fecha = null;
        state.cita = null;
        state.ficha = null;

        if (els.ciInput) {
            els.ciInput.value = '';
            els.ciInput.focus({ preventScroll: true });
        }

        clearErrors();
        show('identify');
    }

    async function identify() {
        const ci = sanitizeCi(els.ciInput ? els.ciInput.value : '');

        if (els.ciInput && els.ciInput.value !== ci) {
            els.ciInput.value = ci;
        }

        if (!/^\d{5,10}(-[A-Z0-9]{1,2})?$/.test(ci)) {
            setError(els.ciError, 'Ingresa una cédula válida de 5 a 10 dígitos. El complemento es opcional.');
            return;
        }

        state.ci = ci;
        setError(els.ciError, '');
        setError(els.registerError, '');
        const button = $('[data-action="identify"]');

        await withBusy(button, 'Buscando…', async () => {
            try {
                const result = await api('/identificar', {
                    method: 'POST',
                    body: { ci },
                });

                if (!result || result.existe !== true || !result.paciente) {
                    if (els.registerCi) {
                        els.registerCi.textContent = `CI ${ci}`;
                    }

                    show('register');
                    window.setTimeout(() => {
                        const first = els.registerForm ? els.registerForm.querySelector('input[name="nombres"]') : null;
                        if (first) first.focus();
                    }, 150);
                    return;
                }

                if (result.paciente.estado !== 'activo') {
                    setError(els.ciError, 'Tu registro está inactivo. Acércate a recepción para reactivarlo.');
                    return;
                }

                state.paciente = result.paciente;
                runVerification(
                    'Verificando identidad…',
                    `Hola, ${firstName(result.paciente.nombre_completo)}. Estamos comparando tus datos con el registro de la clínica.`,
                    () => loadSpecialties()
                );
            } catch (error) {
                handleError(error, els.ciError);
            }
        });
    }

    async function registerPatient(event) {
        event.preventDefault();

        if (!els.registerForm || !els.registerForm.reportValidity()) {
            return;
        }

        setError(els.registerError, '');
        const formData = new FormData(els.registerForm);
        const payload = {
            ci: state.ci,
            nombres: String(formData.get('nombres') || '').trim(),
            apellidos: String(formData.get('apellidos') || '').trim(),
            fecha_nacimiento: String(formData.get('fecha_nacimiento') || ''),
            sexo: String(formData.get('sexo') || ''),
            telefono: String(formData.get('telefono') || '').trim(),
        };

        const button = els.registerForm.querySelector('button[type="submit"]');

        await withBusy(button, 'Guardando…', async () => {
            try {
                const paciente = await api('/registrar', {
                    method: 'POST',
                    body: payload,
                });

                state.paciente = paciente;
                runVerification(
                    'Registrando tu identidad…',
                    'Estamos creando tu registro de atención y preparando las especialidades.',
                    () => loadSpecialties()
                );
            } catch (error) {
                if (error instanceof KioskError && error.code === 'CI_TAKEN') {
                    setError(els.registerError, 'Esa CI ya está registrada. Vuelve e ingrésala de nuevo para identificarte.');
                    return;
                }

                handleError(error, els.registerError);
            }
        });
    }

    function runVerification(title, text, done) {
        cancelAnimationFrame(state.verifyFrame);
        show('verify');

        if (els.verifyTitle) els.verifyTitle.textContent = title;
        if (els.verifyText) els.verifyText.textContent = text;
        if (els.verifyProgress) els.verifyProgress.style.width = '0%';

        const start = performance.now();
        const duration = 2200;

        const tick = (now) => {
            const progress = Math.min(100, ((now - start) / duration) * 100);

            if (els.verifyProgress) {
                els.verifyProgress.style.width = `${progress}%`;
            }

            if (progress < 100) {
                state.verifyFrame = requestAnimationFrame(tick);
            } else {
                done();
            }
        };

        state.verifyFrame = requestAnimationFrame(tick);
    }

    /* Catálogos ----------------------------------------------------------- */

    async function loadSpecialties() {
        if (!state.paciente) {
            show('welcome');
            return;
        }

        show('specialty');

        if (els.patientGreeting) {
            els.patientGreeting.textContent = `Hola, ${firstName(state.paciente.nombre_completo)}. Elige la especialidad de tu consulta.`;
        }

        if (els.specialties) els.specialties.innerHTML = '';
        if (els.specialtiesLoading) els.specialtiesLoading.hidden = false;
        if (els.specialtiesError) els.specialtiesError.hidden = true;

        try {
            // TEMPORAL: se omite `paciente_id` para mostrar todas las
            // especialidades sin el filtro de sexo/edad (solo para capturas
            // de pantalla). Volver a `?paciente_id=${state.paciente.id}`
            // cuando se revise ese filtro.
            const data = await api('/especialidades');
            state.especialidades = Array.isArray(data) ? data : [];
            renderSpecialties();

            if (state.especialidades.length === 0) {
                setError(els.specialtiesErrorText, 'No hay especialidades disponibles para tu perfil.');
                if (els.specialtiesError) els.specialtiesError.hidden = false;
            }
        } catch (error) {
            if (els.specialtiesErrorText) {
                els.specialtiesErrorText.textContent = error.message || 'No pudimos cargar las especialidades.';
            }
            if (els.specialtiesError) els.specialtiesError.hidden = false;
        } finally {
            if (els.specialtiesLoading) els.specialtiesLoading.hidden = true;
        }
    }

    function renderSpecialties() {
        if (!els.specialties) {
            return;
        }

        els.specialties.innerHTML = '';
        const fragment = document.createDocumentFragment();

        state.especialidades.forEach((especialidad) => {
            const foto = photoFor(especialidad);
            const card = document.createElement('button');
            card.type = 'button';

            if (foto) {
                card.className = 'specialty-card has-photo' + (especialidad.destacada ? ' is-featured' : '');
                card.innerHTML = `
                    <span class="specialty-photo-tile">
                        <img src="${esc(foto)}" alt="" loading="lazy">
                    </span>
                    <span class="specialty-tile-footer">
                        <strong>${esc(especialidad.nombre)}</strong>
                        <span class="card-arrow">${SVG_ARROW}</span>
                    </span>
                `;
            } else {
                card.className = 'specialty-card' + (especialidad.destacada ? ' is-featured' : '');
                card.innerHTML = `
                    <span class="specialty-icon">${iconFor(especialidad)}</span>
                    <strong>${esc(especialidad.nombre)}</strong>
                    <p>${esc(especialidad.descripcion || 'Atención profesional en la clínica.')}</p>
                    <span class="card-arrow">${SVG_ARROW}</span>
                `;
            }

            card.addEventListener('click', () => chooseSpecialty(especialidad));
            fragment.appendChild(card);
        });

        els.specialties.appendChild(fragment);
    }

    async function chooseSpecialty(especialidad) {
        if (state.busy) {
            return;
        }

        state.especialidad = especialidad;
        state.medico = null;
        state.slot = null;
        await loadDoctors();
    }

    async function loadDoctors() {
        if (!state.especialidad) {
            show('specialty');
            return;
        }

        show('doctor');

        if (els.specialtyBadge) {
            els.specialtyBadge.textContent = state.especialidad.nombre;
        }

        if (els.doctorSubtitle) {
            els.doctorSubtitle.textContent = `Profesionales disponibles para ${state.especialidad.nombre}.`;
        }

        if (els.doctors) els.doctors.innerHTML = '';
        if (els.doctorsLoading) els.doctorsLoading.hidden = false;
        if (els.doctorsError) els.doctorsError.hidden = true;

        try {
            const data = await api(
                `/medicos?especialidad=${encodeURIComponent(state.especialidad.id)}`
                + `&paciente_id=${encodeURIComponent(state.paciente.id)}`
            );
            state.medicos = Array.isArray(data) ? data : [];
            renderDoctors();

            if (state.medicos.length === 0) {
                if (els.doctorsError) {
                    const message = els.doctorsError.querySelector('p');
                    if (message) message.textContent = 'No hay médicos disponibles para esta especialidad.';
                    els.doctorsError.hidden = false;
                }
            }
        } catch (error) {
            if (els.doctorsError) {
                const message = els.doctorsError.querySelector('p');
                if (message) message.textContent = error.message || 'No pudimos cargar los médicos.';
                els.doctorsError.hidden = false;
            }
        } finally {
            if (els.doctorsLoading) els.doctorsLoading.hidden = true;
        }
    }

    function renderDoctors() {
        if (!els.doctors) {
            return;
        }

        els.doctors.innerHTML = '';
        const fragment = document.createDocumentFragment();

        state.medicos.forEach((medico) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'doctor-card';
            const specialties = (medico.especialidades || [])
                .map((item) => `<span>${esc(item.nombre)}</span>`)
                .join('');

            card.innerHTML = `
                <span class="avatar" aria-hidden="true">${esc(initials(medico.nombre_completo))}</span>
                <span class="doctor-card-body">
                    <strong>${esc(medico.nombre_completo)}</strong>
                    <small>${esc(medico.registro_profesional || 'Registro profesional')} · ${Number(medico.duracion_cita_min) || 30} min</small>
                    <span class="doctor-specialties">${specialties}</span>
                </span>
                <span class="card-arrow">${SVG_ARROW}</span>
            `;
            card.addEventListener('click', () => chooseDoctor(medico));
            fragment.appendChild(card);
        });

        els.doctors.appendChild(fragment);
    }

    function chooseDoctor(medico) {
        state.medico = medico;
        state.slot = null;
        state.slots = [];
        buildDates();
        show('schedule');

        if (els.scheduleSubtitle) {
            els.scheduleSubtitle.textContent = `Horarios de ${medico.nombre_completo} para ${state.especialidad.nombre}.`;
        }

        if (els.scheduleDoctor) {
            els.scheduleDoctor.textContent = medico.nombre_completo;
        }

        autoLoadSlots();
    }

    /**
     * Busca el primer día con cupo para que el paciente no aterrice en un día
     * cerrado (por ejemplo, un domingo).
     */
    async function autoLoadSlots() {
        if (!state.medico || state.fechas.length === 0) {
            return;
        }

        const requestId = ++state.slotsRequest;

        if (els.slots) els.slots.innerHTML = '';
        if (els.slotsLoading) els.slotsLoading.hidden = false;
        if (els.slotsEmpty) els.slotsEmpty.hidden = true;
        state.slots = [];
        state.slot = null;
        updateSelection();

        try {
            for (const date of state.fechas) {
                const data = await api(
                    `/disponibilidad?medico=${encodeURIComponent(state.medico.id)}`
                    + `&fecha=${encodeURIComponent(isoDate(date))}`
                    + `&paciente_id=${encodeURIComponent(state.paciente.id)}`
                );

                if (requestId !== state.slotsRequest) {
                    return;
                }

                const slots = data && Array.isArray(data.slots) ? data.slots : [];

                if (slots.some((slot) => slot.disponible === true)) {
                    state.fecha = date;
                    state.slots = slots;
                    renderDates();
                    renderSlots();
                    updateSelection();
                    return;
                }
            }

            if (requestId !== state.slotsRequest) {
                return;
            }

            state.fecha = state.fechas[0] || null;
            renderDates();
            renderSlots();

            if (els.slotsEmpty) {
                els.slotsEmpty.hidden = false;
            }
        } catch (error) {
            if (requestId === state.slotsRequest && els.slotsEmpty) {
                els.slotsEmpty.hidden = false;
                const message = els.slotsEmpty.querySelector('p');
                if (message) message.textContent = error.message || 'No pudimos cargar los horarios.';
            }
        } finally {
            if (requestId === state.slotsRequest && els.slotsLoading) {
                els.slotsLoading.hidden = true;
            }
        }
    }

    /* Fechas y horarios --------------------------------------------------- */

    function buildDates() {
        state.fechas = [];
        const base = today();

        for (let index = 0; index < 10; index += 1) {
            const date = new Date(base);
            date.setDate(base.getDate() + index);
            state.fechas.push(date);
        }

        state.fecha = state.fechas[0] || null;
        renderDates();
    }

    function renderDates() {
        if (!els.dates) {
            return;
        }

        els.dates.innerHTML = '';
        const fragment = document.createDocumentFragment();

        state.fechas.forEach((date) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'date-chip' + (state.fecha && isoDate(state.fecha) === isoDate(date) ? ' is-selected' : '');
            button.setAttribute('aria-pressed', state.fecha && isoDate(state.fecha) === isoDate(date) ? 'true' : 'false');
            button.innerHTML = `
                <small>${esc(shortWeekday(date))}</small>
                <strong>${date.getDate()}</strong>
                <span>${esc(shortMonth(date))}</span>
            `;
            button.addEventListener('click', () => {
                state.fecha = date;
                state.slot = null;
                renderDates();
                updateSelection();
                loadSlots(date);
            });
            fragment.appendChild(button);
        });

        els.dates.appendChild(fragment);
    }

    async function loadSlots(date) {
        if (!state.medico || !date) {
            return;
        }

        const requestId = ++state.slotsRequest;

        state.fecha = date;
        state.slots = [];
        state.slot = null;
        updateSelection();
        if (els.slots) els.slots.innerHTML = '';
        if (els.slotsLoading) els.slotsLoading.hidden = false;
        if (els.slotsEmpty) els.slotsEmpty.hidden = true;

        try {
            const data = await api(
                `/disponibilidad?medico=${encodeURIComponent(state.medico.id)}`
                + `&fecha=${encodeURIComponent(isoDate(date))}`
                + `&paciente_id=${encodeURIComponent(state.paciente.id)}`
            );

            if (requestId !== state.slotsRequest) {
                return;
            }

            state.slots = data && Array.isArray(data.slots) ? data.slots : [];
            renderSlots();

            if (state.slots.length === 0 && els.slotsEmpty) {
                els.slotsEmpty.hidden = false;
            }
        } catch (error) {
            if (requestId === state.slotsRequest && els.slotsEmpty) {
                els.slotsEmpty.hidden = false;
                const message = els.slotsEmpty.querySelector('p');
                if (message) message.textContent = error.message || 'No pudimos cargar los horarios.';
            }
        } finally {
            if (requestId === state.slotsRequest && els.slotsLoading) {
                els.slotsLoading.hidden = true;
            }
        }
    }

    function renderSlots() {
        if (!els.slots) {
            return;
        }

        els.slots.innerHTML = '';
        const fragment = document.createDocumentFragment();

        state.slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'slot';
            button.disabled = slot.disponible !== true;
            button.innerHTML = `
                <strong>${esc(slot.hora)}</strong>
                <small>${slot.disponible === true ? 'Disponible' : 'Sin cupo'}</small>
            `;

            if (slot.disponible === true) {
                button.addEventListener('click', () => {
                    state.slot = slot;
                    updateSelection();
                    $$('.slot', els.slots).forEach((item) => item.classList.remove('is-selected'));
                    button.classList.add('is-selected');
                });
            }

            fragment.appendChild(button);
        });

        els.slots.appendChild(fragment);
    }

    function updateSelection() {
        const continueButton = $('[data-action="to-confirm"]');

        if (continueButton) {
            continueButton.disabled = !state.slot;
        }

        if (!els.selectionNote) {
            return;
        }

        if (state.slot && state.fecha) {
            els.selectionNote.textContent = `Seleccionado: ${longDate(state.fecha)} · ${state.slot.hora}`;
        } else {
            els.selectionNote.textContent = 'Selecciona un horario para continuar.';
        }
    }

    /* Confirmación y ficha ------------------------------------------------ */

    function renderConfirm() {
        if (!state.paciente || !state.especialidad || !state.medico || !state.fecha || !state.slot) {
            show('schedule');
            return;
        }

        if (els.confirmAvatar) els.confirmAvatar.textContent = initials(state.paciente.nombre_completo);
        if (els.confirmPatient) els.confirmPatient.textContent = state.paciente.nombre_completo;
        if (els.confirmCi) els.confirmCi.textContent = `CI ${state.paciente.ci}`;
        if (els.confirmSpecialty) els.confirmSpecialty.textContent = state.especialidad.nombre;
        if (els.confirmDoctor) els.confirmDoctor.textContent = state.medico.nombre_completo;
        if (els.confirmDate) els.confirmDate.textContent = longDate(state.fecha);
        if (els.confirmTime) els.confirmTime.textContent = state.slot.hora;
        if (els.confirmDuration) els.confirmDuration.textContent = `${Number(state.medico.duracion_cita_min) || 30} minutos`;
        setError(els.confirmError, '');
        show('confirm');
    }

    async function confirmReservation() {
        if (!state.paciente || !state.especialidad || !state.medico || !state.fecha || !state.slot) {
            return;
        }

        setError(els.confirmError, '');
        const label = els.confirmLabel ? els.confirmLabel.textContent : 'Confirmar y generar ficha';

        await withBusy(els.confirmBtn, 'Reservando…', async () => {
            try {
                const cita = await api('/reservar', {
                    method: 'POST',
                    body: {
                        paciente_id: state.paciente.id,
                        medico_id: state.medico.id,
                        especialidad_id: state.especialidad.id,
                        fecha: isoDate(state.fecha),
                        hora: state.slot.hora,
                    },
                });

                state.cita = cita;
                await emitFicha();
            } catch (error) {
                handleError(error, els.confirmError);

                if (error instanceof KioskError && error.code === 'SLOT_TAKEN') {
                    await loadSlots(state.fecha);
                    showToast('Ese horario se ocupó. Elige otro.', 'error');
                }
            } finally {
                if (els.confirmLabel) {
                    els.confirmLabel.textContent = label;
                }
            }
        });
    }

    async function emitFicha() {
        if (!state.cita) {
            return;
        }

        try {
            const ficha = await api('/ficha', {
                method: 'POST',
                body: {
                    cita_id: state.cita.id,
                    verificacion_biometrica: true,
                },
            });

            state.ficha = ficha;
            renderFicha(ficha);
            show('ficha');
        } catch (error) {
            showFichaFallback(error);
        }
    }

    function showFichaFallback(error) {
        if (els.fichaContent) els.fichaContent.hidden = true;
        if (els.fichaFallback) els.fichaFallback.hidden = false;
        if (els.fallbackCita) els.fallbackCita.textContent = String(state.cita && state.cita.codigo ? state.cita.codigo : '—');
        handleError(error, els.fichaError);
        show('ficha');
    }

    function renderFicha(ficha) {
        if (!ficha) {
            return;
        }

        if (els.fichaContent) els.fichaContent.hidden = false;
        if (els.fichaFallback) els.fichaFallback.hidden = true;

        const set = (selector, value) => {
            const element = $(selector);
            if (element) {
                element.textContent = value == null ? '' : String(value);
            }
        };

        const impresion = ficha.impresion || {};
        const cita = ficha.cita || {};

        set('[data-ficha-clinica]', impresion.clinica || 'Clínica Vida Salud');
        set('[data-ficha-codigo]', ficha.codigo);
        set('[data-ficha-acceso]', ficha.codigo_acceso);
        set('[data-ficha-paciente]', impresion.paciente || (ficha.paciente || {}).nombre_completo);
        set('[data-ficha-ci]', impresion.ci || (ficha.paciente || {}).ci);
        set('[data-ficha-especialidad]', impresion.especialidad || (ficha.especialidad || {}).nombre);
        set('[data-ficha-medico]', impresion.medico || (ficha.medico || {}).nombre_completo);
        set('[data-ficha-fecha]', impresion.fecha);
        set('[data-ficha-hora]', impresion.hora);
        set('[data-ficha-cita]', cita.codigo);
        set('[data-ficha-cita-side]', cita.codigo);
        set('[data-ficha-instrucciones]', impresion.instrucciones);
        set('[data-ficha-estado]', cita.estado === 'confirmada' ? 'Confirmada' : (cita.estado || 'Emitida'));
        set('[data-ficha-verificacion]', ficha.verificacion_biometrica ? 'Simulada (rostro)' : 'No realizada');

        const qr = $('[data-ficha-qr]');

        if (qr) {
            qr.innerHTML = '';

            if (typeof window.qrcode === 'function' && ficha.qr_contenido) {
                try {
                    const code = window.qrcode(0, 'M');
                    code.addData(String(ficha.qr_contenido));
                    code.make();
                    qr.innerHTML = code.createSvgTag({
                        cellSize: 5,
                        margin: 3,
                        scalable: true,
                        alt: `Código QR de la ficha ${ficha.codigo}`,
                    });
                } catch (error) {
                    qr.textContent = 'QR no disponible';
                }
            } else {
                qr.textContent = 'QR no disponible';
            }
        }
    }

    async function retryFicha() {
        setError(els.fichaError, '');
        const button = $('[data-action="retry-ficha"]');
        await withBusy(button, 'Reintentando…', emitFicha);
    }

    /* Eventos ------------------------------------------------------------- */

    app.addEventListener('click', (event) => {
        const actionElement = event.target.closest('[data-action]');

        if (actionElement) {
            const action = actionElement.dataset.action;

            switch (action) {
                case 'start':
                    startFlow();
                    break;
                case 'demo':
                    startFlow();
                    if (els.ciInput) {
                        els.ciInput.value = DEMO_CI;
                        identify();
                    }
                    break;
                case 'identify':
                    identify();
                    break;
                case 'backspace':
                    if (els.ciInput) {
                        els.ciInput.value = sanitizeCi(els.ciInput.value.slice(0, -1));
                        els.ciInput.focus({ preventScroll: true });
                    }
                    break;
                case 'go-welcome':
                    resetFlow(true);
                    break;
                case 'go-identify':
                    show('identify');
                    break;
                case 'go-specialty':
                    show('specialty');
                    break;
                case 'go-doctor':
                    show('doctor');
                    break;
                case 'go-schedule':
                    show('schedule');
                    updateSelection();
                    break;
                case 'retry-specialties':
                    loadSpecialties();
                    break;
                case 'retry-doctors':
                    loadDoctors();
                    break;
                case 'to-confirm':
                    renderConfirm();
                    break;
                case 'confirm':
                    confirmReservation();
                    break;
                case 'retry-ficha':
                    retryFicha();
                    break;
                case 'print':
                    stopFichaTimer();
                    window.print();
                    break;
                case 'reset':
                    resetFlow();
                    break;
                case 'reconnect':
                    window.location.reload();
                    break;
                default:
                    break;
            }

            return;
        }

        const keyButton = event.target.closest('[data-key]');

        if (keyButton && els.ciInput) {
            const key = keyButton.dataset.key;
            let value = els.ciInput.value;

            if (key === 'clear') {
                value = '';
            } else if (key === 'back') {
                value = value.slice(0, -1);
            } else if (/^\d$/.test(key)) {
                value += key;
            }

            els.ciInput.value = sanitizeCi(value);
            els.ciInput.focus({ preventScroll: true });
        }
    });

    if (els.ciInput) {
        els.ciInput.addEventListener('input', () => {
            els.ciInput.value = sanitizeCi(els.ciInput.value);
            setError(els.ciError, '');
        });

        els.ciInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                identify();
            }
        });
    }

    if (els.registerForm) {
        els.registerForm.addEventListener('submit', registerPatient);
        const dateInput = els.registerForm.querySelector('input[name="fecha_nacimiento"]');

        if (dateInput) {
            dateInput.max = isoDate(new Date());
        }
    }

    document.addEventListener('pointerdown', noteActivity, { passive: true });
    document.addEventListener('keydown', noteActivity);

    window.addEventListener('afterprint', () => {
        if (state.screen === 'ficha') {
            startFichaTimer();
        }
    });

    window.addEventListener('pageshow', () => {
        if (state.token) {
            refreshToken().catch(() => {
                /* la sesión se reintenta en la próxima llamada */
            });
        }
    });

    /* Reloj ---------------------------------------------------------------- */

    function updateClock() {
        if (!els.clock) {
            return;
        }

        const now = new Date();
        const date = now.toLocaleDateString('es-BO', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }).replace(/\./g, '');

        const time = now.toLocaleTimeString('es-BO', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });

        els.clock.textContent = `${date} · ${time}`;
    }

    /* Arranque ------------------------------------------------------------- */

    try {
        state.token = app.dataset.token || '';
        state.tokenExpiresAt = Date.now() + Number(app.dataset.tokenTtl || 600) * 1000;
    } catch (error) {
        state.token = '';
    }

    updateClock();
    window.setInterval(updateClock, 15000);
    show('welcome');
})();
