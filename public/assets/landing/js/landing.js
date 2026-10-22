(() => {
    'use strict';

    const header = document.querySelector('[data-site-header]');
    const navToggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-site-nav]');

    if (navToggle && nav) {
        navToggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        nav.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                nav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (header) {
        const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
        updateHeader();
        window.addEventListener('scroll', updateHeader, { passive: true });
    }

    const grid = document.querySelector('[data-doctor-grid]');
    const subtitle = document.getElementById('medicos-subtitulo');

    if (!grid) {
        return;
    }

    const renderEmpty = (message) => {
        grid.replaceChildren();
        const box = document.createElement('div');
        box.className = 'empty-state';
        const title = document.createElement('strong');
        title.textContent = message;
        const text = document.createElement('p');
        text.textContent = 'Prueba con otra especialidad o vuelve a intentarlo.';
        box.append(title, text);
        grid.append(box);
    };

    const renderError = (message) => {
        grid.replaceChildren();
        const box = document.createElement('div');
        box.className = 'empty-state';
        const title = document.createElement('strong');
        title.textContent = message;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-outline';
        button.textContent = 'Reintentar';
        button.addEventListener('click', () => loadDoctors(currentEspecialidad));
        box.append(title, button);
        grid.append(box);
    };

    const makeDoctorCard = (doctor) => {
        const card = document.createElement('article');
        card.className = 'doctor-card';

        const name = document.createElement('h3');
        name.textContent = doctor.nombre_completo || `${doctor.nombres} ${doctor.apellidos}`;
        card.append(name);

        const specialties = document.createElement('ul');
        specialties.className = 'doctor-specialties';

        (doctor.especialidades || []).forEach((specialty) => {
            const item = document.createElement('li');
            item.textContent = specialty.nombre;
            specialties.append(item);
        });

        card.append(specialties);

        const actions = document.createElement('div');
        actions.className = 'doctor-actions';

        const duration = document.createElement('small');
        duration.textContent = `${doctor.duracion_cita_min || 30} min por consulta`;

        const link = document.createElement('a');
        link.className = 'button button-outline';
        link.href = '/reservar';
        link.textContent = 'Agendar';

        actions.append(duration, link);
        card.append(actions);

        return card;
    };

    let currentEspecialidad = null;
    let requestId = 0;

    const loadDoctors = async (especialidadId = null) => {
        currentEspecialidad = especialidadId;
        const currentRequest = ++requestId;

        grid.replaceChildren();

        const loading = document.createElement('div');
        loading.className = 'empty-state';
        const loadingTitle = document.createElement('strong');
        loadingTitle.textContent = 'Cargando médicos…';
        loading.append(loadingTitle);
        grid.append(loading);

        const params = new URLSearchParams();

        if (especialidadId) {
            params.set('especialidad', especialidadId);
        }

        const url = '/api/public/medicos' + (params.toString() ? `?${params.toString()}` : '');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json().catch(() => null);

            if (currentRequest !== requestId) {
                return;
            }

            if (!response.ok || !payload?.ok) {
                renderError(payload?.error?.message || 'No pudimos cargar los médicos.');
                return;
            }

            const doctors = Array.isArray(payload.data) ? payload.data : [];

            if (doctors.length === 0) {
                renderEmpty('No hay médicos disponibles para esa especialidad.');
                return;
            }

            grid.replaceChildren(...doctors.map(makeDoctorCard));
        } catch {
            if (currentRequest === requestId) {
                renderError('No pudimos conectar con el servidor.');
            }
        }
    };

    document.querySelectorAll('[data-especialidad-id]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.especialidadId;
            const name = button.dataset.especialidadNombre || 'la especialidad';

            if (subtitle) {
                subtitle.textContent = `Médicos de ${name}.`;
            }

            loadDoctors(id);
            document.getElementById('medicos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    loadDoctors();
})();
