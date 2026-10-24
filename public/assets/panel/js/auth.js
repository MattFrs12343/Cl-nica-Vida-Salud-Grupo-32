import {
    $,
    $$,
    PANEL,
    apiFetch,
    apiErrorMessage,
    clearFieldErrors,
    renderFieldErrors,
    setLoading,
    toast,
} from './app.js';

function initPasswordToggles() {
    $$('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.parentElement?.querySelector('[data-password]');

            if (!input) {
                return;
            }

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-label', showing ? 'Mostrar contraseña' : 'Ocultar contraseña');
            button.classList.toggle('is-showing', !showing);
            button.innerHTML = `<svg class="icon" aria-hidden="true"><use href="#i-${showing ? 'eye' : 'eye-off'}"></use></svg>`;
        });
    });
}

function initPasswordMeters() {
    $$('form[data-auth-form]').forEach((form) => {
        const password = form.querySelector('input[name="password"]');
        const meter = form.querySelector('[data-password-meter]');

        if (!password || !meter) {
            return;
        }

        password.addEventListener('input', () => {
            const value = password.value;
            let score = 0;

            if (value.length >= 8) score += 1;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
            if (/\d/.test(value)) score += 1;
            if (/[^\w\s]/.test(value) || value.length >= 12) score += 1;

            meter.dataset.score = String(score);
        });
    });
}

function formPayload(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function modeOf(form) {
    return form.dataset.authMode || 'login';
}

function successRedirect(form, payload) {
    if (payload?.data?.redirect) {
        return payload.data.redirect;
    }

    if (form.dataset.successRedirect) {
        return form.dataset.successRedirect;
    }

    return PANEL.home || '/login';
}

async function submitAuthForm(form, button) {
    const mode = modeOf(form);
    const payload = formPayload(form);

    clearFieldErrors(form);

    if (payload.identificador) {
        payload.identificador = String(payload.identificador).trim();
    }

    if (['reset', 'activar'].includes(mode) && payload.password !== payload.password_confirmation) {
        renderFieldErrors(form, { password_confirmation: 'Las contraseñas no coinciden.' });
        toast('error', 'Revisa la confirmación de tu contraseña.');
        return;
    }

    setLoading(button, true);

    try {
        const response = await apiFetch(form.action, {
            method: 'POST',
            body: payload,
            redirectOn401: mode !== 'login',
        });

        const message = response?.message || (
            mode === 'login' ? 'Bienvenido de nuevo.' : 'Operación completada.'
        );

        toast('success', message);

        if (mode === 'login') {
            const redirect = successRedirect(form, response);
            window.setTimeout(() => window.location.assign(redirect), 320);
            return;
        }

        form.reset();
        const redirect = successRedirect(form, response);
        window.setTimeout(() => window.location.assign(redirect), 1100);
    } catch (error) {
        const message = apiErrorMessage(error);

        if (error?.fields && Object.keys(error.fields).length > 0) {
            renderFieldErrors(form, error.fields);
        }

        toast('error', message);

        if (mode === 'login' && error?.status === 401) {
            form.querySelector('input[name="password"]')?.focus();
        }
    } finally {
        setLoading(button, false);
    }
}

function initForms() {
    $$('form[data-auth-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            submitAuthForm(form, button);
        });
    });
}

initPasswordToggles();
initPasswordMeters();
initForms();
