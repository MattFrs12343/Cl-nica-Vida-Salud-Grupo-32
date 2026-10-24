/**
 * Entrypoint del panel de recepción y del dashboard médico.
 * El layout expone la página lógica en `body[data-page]`.
 */
const page = document.body.dataset.page;

const modules = {
    dashboard: () => import('./recepcion-dashboard.js'),
    pacientes: () => import('./recepcion-pacientes.js'),
    citas: () => import('./recepcion-citas.js'),
    agenda: () => import('./recepcion-agenda.js'),
    reconsultas: () => import('./recepcion-reconsultas.js'),
    fichas: () => import('./recepcion-fichas.js'),
    pagos: () => import('./recepcion-pagos.js'),
};

const load = modules[page];

if (load) {
    load()
        .then((module) => module.default?.())
        .catch((error) => {
            console.error('[panel] No se pudo iniciar la página.', error);
        });
}
