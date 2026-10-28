const page = document.body.dataset.page || '';

const modules = {
    dashboard: () => import('./medico-dashboard.js'),
    agenda: () => import('./medico-agenda.js'),
    pacientes: () => import('./medico-pacientes.js'),
    consultas: () => import('./medico-consultas.js'),
    historia: () => import('./medico-historia.js'),
    pedidos: () => import('./medico-pedidos.js'),
    pagos: () => import('./medico-pagos.js'),
    qr: () => import('./medico-qr.js'),
};

modules[page]?.().catch((error) => {
    console.error('No se pudo inicializar el módulo médico.', error);
});
