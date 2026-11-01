import {
    $,
    addDays,
    apiFetch,
    apiErrorMessage,
    escapeHtml,
    toast,
    todayISO,
} from './app.js';
import {
    downloadCsv,
    emptyStateHtml,
    formatDateNumeric,
    metaOf,
    normalizeData,
    renderLoadError,
} from './admin-shared.js';

export default function initAdminReportes() {
    const desdeInput = $('[data-reporte-desde]');
    const hastaInput = $('[data-reporte-hasta]');
    const apply = $('[data-reporte-apply]');
    const exportButton = $('[data-reporte-export]');
    const especialidadesBox = $('[data-reporte-especialidades]');
    const medicosBody = $('[data-reporte-medicos]');
    const estadosBox = $('[data-reporte-estados]');
    const periodLabel = $('[data-reporte-periodo]');

    if (!especialidadesBox || !medicosBody) {
        return;
    }

    const state = {
        desde: addDays(todayISO(), -29),
        hasta: todayISO(),
        asistencia: {},
        porMedico: [],
        porEspecialidad: [],
        porEstado: {},
        pacientes: 0,
        loaded: false,
    };

    if (desdeInput) desdeInput.value = state.desde;
    if (hastaInput) hastaInput.value = state.hasta;

    const load = async () => {
        if (!state.desde || !state.hasta) {
            toast('error', 'Indica el rango de fechas.');
            return;
        }

        if (state.desde > state.hasta) {
            toast('error', 'La fecha inicial no puede ser posterior a la final.');
            return;
        }

        especialidadesBox.innerHTML = `<div class="table-loading">${'<div class="skeleton skeleton-row"></div>'.repeat(4)}</div>`;
        medicosBody.innerHTML = `<tr><td colspan="5"><div class="skeleton skeleton-row"></div></td></tr>`;

        const query = new URLSearchParams({ desde: state.desde, hasta: state.hasta }).toString();

        try {
            const [asistencia, medicos, especialidades, estados, pacientes] = await Promise.all([
                apiFetch(`/api/reportes/asistencia?${query}`),
                apiFetch(`/api/reportes/citas-por-medico?${query}`),
                apiFetch(`/api/reportes/citas-por-especialidad?${query}`),
                apiFetch(`/api/reportes/citas-por-estado?${query}`),
                apiFetch(`/api/reportes/pacientes?${query}`),
            ]);

            state.asistencia = asistencia?.data || {};
            state.porMedico = normalizeData(medicos);
            state.porEspecialidad = normalizeData(especialidades);
            state.porEstado = estados?.data || {};
            state.pacientes = Number(metaOf(pacientes).total || normalizeData(pacientes).length || 0);
            state.loaded = true;

            renderKpis();
            renderEspecialidades();
            renderMedicos();
            renderEstados();

            if (periodLabel) {
                periodLabel.textContent = `${formatDateNumeric(state.desde)} – ${formatDateNumeric(state.hasta)}`;
            }
        } catch (error) {
            renderLoadError(especialidadesBox, error, load);
            medicosBody.innerHTML = `<tr><td colspan="5">${escapeHtml(apiErrorMessage(error))}</td></tr>`;
            toast('error', apiErrorMessage(error));
        }
    };

    const renderKpis = () => {
        const data = state.asistencia;
        setText('[data-reporte-kpi="total"]', data.total ?? 0);
        setText('[data-reporte-kpi="atendidas"]', data.atendidas ?? 0);
        setText('[data-reporte-kpi="inasistencias"]', data.inasistencias ?? 0);
        setText('[data-reporte-kpi="asistencia"]', `${Number(data.porcentaje_asistencia ?? 0).toFixed(1)}%`);
        setText('[data-reporte-kpi="pacientes"]', state.pacientes);
        setText('[data-reporte-kpi="canceladas"]', data.canceladas ?? 0);
    };

    const renderEspecialidades = () => {
        const items = state.porEspecialidad.filter((item) => Number(item.total) > 0);

        if (items.length === 0) {
            especialidadesBox.innerHTML = emptyStateHtml({
                icon: 'chart',
                title: 'Sin citas en el periodo',
                message: 'Prueba con otro rango de fechas.',
            });
            return;
        }

        const max = Math.max(...items.map((item) => Number(item.total) || 0), 1);

        especialidadesBox.innerHTML = `
            <div class="report-bars">
                ${items.map((item) => {
                    const total = Number(item.total) || 0;
                    const pct = Math.max(2, Math.round((total / max) * 100));

                    return `
                        <div class="report-bar-row">
                            <div class="report-bar-head">
                                <strong>${escapeHtml(item.nombre)}</strong>
                                <span>${total} cita(s)</span>
                            </div>
                            <div class="report-bar-track">
                                <div class="report-bar-fill" style="width:${pct}%"></div>
                            </div>
                            <small>${Number(item.atendidas) || 0} atendidas · ${Number(item.inasistencias) || 0} inasistencias</small>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    };

    const renderMedicos = () => {
        if (state.porMedico.length === 0) {
            medicosBody.innerHTML = `<tr><td colspan="5">${emptyStateHtml({
                icon: 'stethoscope',
                title: 'Sin médicos para reportar',
                message: 'No hay datos en el periodo seleccionado.',
            })}</td></tr>`;
            return;
        }

        medicosBody.innerHTML = state.porMedico.map((item) => {
            const atendidas = Number(item.atendidas) || 0;
            const inasistencias = Number(item.inasistencias) || 0;
            const base = atendidas + inasistencias;
            const porcentaje = base > 0 ? Math.round((atendidas / base) * 100) : 0;

            return `
                <tr>
                    <td>
                        <div class="cell-stack">
                            <strong>${escapeHtml(item.nombre_completo || 'Médico')}</strong>
                            <small>${escapeHtml(item.codigo || '')}</small>
                        </div>
                    </td>
                    <td>${Number(item.total) || 0}</td>
                    <td>${atendidas}</td>
                    <td>${inasistencias}</td>
                    <td>
                        <span class="badge ${porcentaje >= 90 ? 'badge-pine' : 'badge-info'}">${porcentaje}%</span>
                    </td>
                </tr>
            `;
        }).join('');
    };

    const renderEstados = () => {
        const estados = [
            ['programada', 'Programadas', 'badge-muted'],
            ['confirmada', 'Confirmadas', 'badge-info'],
            ['atendida', 'Atendidas', 'badge-success'],
            ['cancelada', 'Canceladas', 'badge-danger'],
            ['inasistencia', 'Inasistencias', 'badge-warning'],
        ];

        estadosBox.innerHTML = estados.map(([clave, etiqueta, badge]) => `
            <div class="mini-stat">
                <span class="mini-stat-icon" aria-hidden="true"><svg class="icon"><use href="#i-${clave === 'atendida' ? 'check-circle' : (clave === 'inasistencia' ? 'x-circle' : 'calendar')}"></use></svg></span>
                <div>
                    <strong>${Number(state.porEstado[clave]) || 0}</strong>
                    <span>${escapeHtml(etiqueta)}</span>
                </div>
            </div>
        `).join('');
    };

    const exportCsv = () => {
        if (!state.loaded) {
            toast('error', 'Primero carga un periodo.');
            return;
        }

        const rows = [
            ['Reporte', 'Clínica Vida Salud'],
            ['Desde', state.desde],
            ['Hasta', state.hasta],
            [],
            ['Resumen', 'Valor'],
            ['Citas totales', state.asistencia.total ?? 0],
            ['Atendidas', state.asistencia.atendidas ?? 0],
            ['Inasistencias', state.asistencia.inasistencias ?? 0],
            ['Canceladas', state.asistencia.canceladas ?? 0],
            ['Asistencia %', state.asistencia.porcentaje_asistencia ?? 0],
            ['Pacientes nuevos', state.pacientes],
            [],
            ['Especialidad', 'Citas', 'Atendidas', 'Inasistencias'],
            ...state.porEspecialidad.map((item) => [
                item.nombre,
                item.total,
                item.atendidas,
                item.inasistencias,
            ]),
            [],
            ['Médico', 'Código', 'Citas', 'Atendidas', 'Inasistencias'],
            ...state.porMedico.map((item) => [
                item.nombre_completo,
                item.codigo,
                item.total,
                item.atendidas,
                item.inasistencias,
            ]),
        ];

        downloadCsv(`reporte-${state.desde}-${state.hasta}.csv`, rows);
    };

    apply?.addEventListener('click', () => {
        state.desde = desdeInput?.value || '';
        state.hasta = hastaInput?.value || '';
        load();
    });

    exportButton?.addEventListener('click', exportCsv);

    load();
}

function setText(selector, value) {
    const node = document.querySelector(selector);
    if (node) {
        node.textContent = String(value ?? '—');
    }
}
