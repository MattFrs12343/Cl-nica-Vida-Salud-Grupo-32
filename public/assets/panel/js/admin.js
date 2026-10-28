import { toast } from './app.js';

const PAGE_MODULES = {
    dashboard: () => import('./admin-dashboard.js'),
    usuarios: () => import('./admin-usuarios.js'),
    medicos: () => import('./admin-medicos.js'),
    especialidades: () => import('./admin-especialidades.js'),
    horarios: () => import('./admin-horarios.js'),
    citas: () => import('./admin-citas.js'),
    reportes: () => import('./admin-reportes.js'),
    auditoria: () => import('./admin-auditoria.js'),
};

const page = document.body.dataset.page || 'dashboard';
const load = PAGE_MODULES[page];

if (load) {
    load()
        .then((module) => module.default?.())
        .catch((error) => {
            console.error(error);
            toast('error', 'No pudimos iniciar esta sección del panel.');
        });
}
