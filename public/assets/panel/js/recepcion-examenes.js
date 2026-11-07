import {
    $,
    apiErrorMessage,
    apiFetch,
    clearFieldErrors,
    closeModal,
    confirmAction,
    debounce,
    escapeHtml,
    formatMoney,
    openModal,
    renderFieldErrors,
    setLoading,
    toast,
} from './app.js';
import { emptyStateHtml, renderLoadError } from './recepcion-shared.js';

const TIPOS = {
    laboratorio: 'Laboratorio',
    imagen: 'Imagen',
    otro: 'Otro',
};

export default function initExamenes() {
    const state = {
        rows: [],
        total: 0,
    };

    const table = $('[data-examenes-table]');
    const countNode = $('[data-examen-count]');
    const search = $('[data-examen-search]');
    const clear = $('[data-examen-clear]');
    const tipoFilter = $('[data-examen-tipo]');
    const estadoFilter = $('[data-examen-estado]');
    const modal = $('#modal-examen');
    const form = $('[data-examen-form]');
    const title = $('[data-examen-modal-title]');
    const submit = $('[data-examen-submit]');

    const openForm = (examen = null) => {
        form.reset();
        clearFieldErrors(form);
        form.elements.id.value = examen?.id || '';
        form.elements.nombre.value = examen?.nombre || '';
        form.elements.codigo.value = examen?.codigo || '';
        form.elements.tipo.value = examen?.tipo || 'laboratorio';
        form.elements.precio.value = examen ? Number(examen.precio).toFixed(2) : '';
        form.elements.activo.value = examen ? (examen.activo ? '1' : '0') : '1';

        if (title) title.textContent = examen ? 'Editar examen' : 'Nuevo examen';
        if (submit) submit.textContent = examen ? 'Guardar cambios' : 'Crear examen';

        openModal(modal);
    };

    const params = () => {
        const query = new URLSearchParams({ per_page: '100' });

        if (search?.value.trim()) query.set('q', search.value.trim());
        if (tipoFilter?.value) query.set('tipo', tipoFilter.value);
        if (estadoFilter?.value !== '') query.set('activo', estadoFilter.value);

        return query;
    };

    const load = async () => {
        table.innerHTML = `
            <div class="table-loading">
                ${Array.from({ length: 5 }, () => '<div class="skeleton skeleton-row"></div>').join('')}
            </div>
        `;

        try {
            const response = await apiFetch(`/api/examenes?${params().toString()}`);
            state.rows = Array.isArray(response?.data) ? response.data : [];
            state.total = Number(response?.meta?.total || state.rows.length);
            render();
        } catch (error) {
            renderLoadError(table, error, load);
        }
    };

    const render = () => {
        if (countNode) {
            countNode.textContent = `${state.total} examen${state.total === 1 ? '' : 'es'}`;
        }

        if (state.rows.length === 0) {
            table.innerHTML = emptyStateHtml({
                icon: 'flask',
                title: 'Sin exámenes en el catálogo',
                message: 'Crea el primer estudio para que el médico pueda pedirlo con su precio.',
            });
            return;
        }

        table.innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Examen</th>
                        <th>Tipo</th>
                        <th class="align-right">Precio</th>
                        <th>Estado</th>
                        <th class="align-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${state.rows.map((row) => `
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <strong>${escapeHtml(row.nombre)}</strong>
                                    <small>${escapeHtml(row.codigo || 'Sin código')}</small>
                                </div>
                            </td>
                            <td><span class="badge badge-muted">${escapeHtml(TIPOS[row.tipo] || row.tipo)}</span></td>
                            <td class="align-right"><strong>${escapeHtml(formatMoney(row.precio))}</strong></td>
                            <td>
                                <span class="badge ${row.activo ? 'badge-success' : 'badge-muted'}">
                                    ${row.activo ? 'Activo' : 'Inactivo'}
                                </span>
                            </td>
                            <td class="align-right">
                                <div class="row-actions">
                                    <button class="icon-btn" type="button" aria-label="Editar ${escapeHtml(row.nombre)}"
                                            data-examen-edit="${row.id}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-edit"></use></svg>
                                    </button>
                                    <button class="icon-btn" type="button"
                                            aria-label="${row.activo ? 'Desactivar' : 'Activar'} ${escapeHtml(row.nombre)}"
                                            data-examen-toggle="${row.id}" data-activo="${row.activo ? '0' : '1'}">
                                        <svg class="icon" aria-hidden="true"><use href="#i-${row.activo ? 'eye-off' : 'check'}"></use></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        table.querySelectorAll('[data-examen-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = state.rows.find((item) => String(item.id) === button.dataset.examenEdit);
                if (row) openForm(row);
            });
        });

        table.querySelectorAll('[data-examen-toggle]').forEach((button) => {
            button.addEventListener('click', async () => {
                const activo = button.dataset.activo === '1';
                const row = state.rows.find((item) => String(item.id) === button.dataset.examenToggle);

                const confirmed = await confirmAction({
                    title: activo ? 'Activar examen' : 'Desactivar examen',
                    message: activo
                        ? `«${row?.nombre || 'Este examen'}» volverá a estar disponible para nuevas órdenes.`
                        : `«${row?.nombre || 'Este examen'}» dejará de aparecer al crear órdenes. Las órdenes anteriores no cambian.`,
                    confirmLabel: activo ? 'Activar' : 'Desactivar',
                    danger: !activo,
                });

                if (!confirmed) return;

                try {
                    const response = await apiFetch(`/api/examenes/${button.dataset.examenToggle}/estado`, {
                        method: 'PATCH',
                        body: { activo },
                    });
                    toast('success', response?.message || 'Estado actualizado.');
                    load();
                } catch (error) {
                    toast('error', apiErrorMessage(error));
                }
            });
        });
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFieldErrors(form);

        const id = form.elements.id.value;
        const payload = {
            nombre: form.elements.nombre.value.trim(),
            codigo: form.elements.codigo.value.trim() || null,
            tipo: form.elements.tipo.value,
            precio: Number(form.elements.precio.value || 0),
            activo: form.elements.activo.value === '1',
        };

        if (!payload.nombre) {
            renderFieldErrors(form, { nombre: 'Ingresa el nombre del examen.' });
            toast('error', 'Revisa los datos del examen.');
            return;
        }

        if (!Number.isFinite(payload.precio) || payload.precio < 0) {
            renderFieldErrors(form, { precio: 'Ingresa un precio válido.' });
            toast('error', 'Revisa el precio del examen.');
            return;
        }

        setLoading(submit, true);

        try {
            const response = await apiFetch(id ? `/api/examenes/${id}` : '/api/examenes', {
                method: id ? 'PUT' : 'POST',
                body: payload,
            });

            toast('success', response?.message || 'Examen guardado.');
            closeModal(modal);
            load();
        } catch (error) {
            if (error?.fields) renderFieldErrors(form, error.fields);
            toast('error', apiErrorMessage(error));
        } finally {
            setLoading(submit, false);
        }
    });

    $('[data-examen-new]')?.addEventListener('click', () => openForm());
    $('[data-examen-refresh]')?.addEventListener('click', load);

    const debounced = debounce(load, 280);

    search?.addEventListener('input', () => {
        if (clear) clear.hidden = !search.value.trim();
        debounced();
    });

    clear?.addEventListener('click', () => {
        if (search) search.value = '';
        if (clear) clear.hidden = true;
        load();
    });

    tipoFilter?.addEventListener('change', load);
    estadoFilter?.addEventListener('change', load);

    load();
}
