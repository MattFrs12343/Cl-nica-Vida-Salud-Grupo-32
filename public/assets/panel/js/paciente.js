const page = document.body.dataset.page;

const loaders = {
    portal: () => import('./paciente-dashboard.js'),
    citas: () => import('./paciente-mis-citas.js'),
    historial: () => import('./paciente-historial.js'),
    perfil: () => import('./paciente-perfil.js'),
};

const loader = loaders[page];

if (loader) {
    loader().catch((error) => {
        console.error('[portal] No se pudo cargar el módulo', page, error);
    });
}
