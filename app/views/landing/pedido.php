<?php
/** @var string $codigo */
?>
<section class="pedido-publico">
    <div class="container pedido-publico-wrap">
        <header class="pedido-publico-head">
            <h1>Consultar mi pedido</h1>
            <p>Ingresa el código de tu receta, documento u orden y el código de acceso de 4 dígitos que recibiste por WhatsApp.</p>
        </header>

        <form class="pedido-form" data-pedido-form novalidate>
            <div class="pedido-field">
                <label for="pedido-codigo">Código del pedido</label>
                <input
                    type="text"
                    id="pedido-codigo"
                    name="codigo"
                    placeholder="RSV-000123"
                    autocomplete="off"
                    value="<?= e($codigo) ?>"
                    required
                >
            </div>
            <div class="pedido-field">
                <label for="pedido-acceso">Código de acceso</label>
                <input
                    type="text"
                    id="pedido-acceso"
                    name="acceso"
                    placeholder="0000"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    autocomplete="off"
                    required
                >
            </div>
            <button type="submit" class="btn btn-primary">Consultar</button>
        </form>

        <p class="pedido-error" data-pedido-error hidden></p>

        <article class="pedido-resultado" data-pedido-resultado hidden>
            <div class="pedido-resultado-head">
                <h2 data-pedido-titulo></h2>
                <button type="button" class="btn btn-ghost" data-pedido-imprimir>Imprimir</button>
            </div>
            <dl class="pedido-meta">
                <div><dt>Paciente</dt><dd data-pedido-paciente></dd></div>
                <div><dt>Médico</dt><dd data-pedido-medico></dd></div>
                <div><dt>Fecha</dt><dd data-pedido-fecha></dd></div>
                <div><dt>Código</dt><dd data-pedido-codigo></dd></div>
            </dl>
            <div data-pedido-contenido></div>
        </article>
    </div>
</section>

<style>
    .pedido-publico { padding: clamp(2rem, 5vw, 4rem) 0 clamp(3rem, 6vw, 5rem); }
    .pedido-publico-wrap { max-width: 640px; }
    .pedido-publico-head h1 { margin: 0 0 .5rem; }
    .pedido-publico-head p { color: var(--ink-soft, #5b6b63); margin: 0 0 2rem; }
    .pedido-form { display: grid; gap: 1rem; grid-template-columns: 2fr 1fr auto; align-items: end; margin-bottom: 1.25rem; }
    .pedido-field { display: flex; flex-direction: column; gap: .35rem; }
    .pedido-field label { font-size: .85rem; font-weight: 600; }
    .pedido-field input {
        padding: .65rem .8rem; border: 1px solid #d8ded6; border-radius: .6rem; font-size: 1rem;
    }
    .pedido-error { color: #b23b2c; font-weight: 600; margin: 0 0 1rem; }
    .pedido-resultado {
        border: 1px solid #d8ded6; border-radius: .9rem; padding: 1.5rem; background: #fff;
    }
    .pedido-resultado-head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
    .pedido-resultado-head h2 { margin: 0; }
    .pedido-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem 1.5rem; margin: 0 0 1.25rem; }
    .pedido-meta dt { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #7a877f; margin: 0; }
    .pedido-meta dd { margin: 0; font-weight: 600; }
    .pedido-detalle-item { padding: .6rem 0; border-top: 1px solid #ecefe9; }
    .pedido-detalle-item:first-child { border-top: none; }
    @media (max-width: 640px) {
        .pedido-form { grid-template-columns: 1fr; }
    }
    @media print {
        .pedido-publico-head, .pedido-form, .pedido-error, .site-header, .site-footer, .flash-stack,
        [data-pedido-imprimir] { display: none !important; }
    }
</style>

<script>
(function () {
    var form = document.querySelector('[data-pedido-form]');
    var errorBox = document.querySelector('[data-pedido-error]');
    var resultado = document.querySelector('[data-pedido-resultado]');
    var etiquetas = { receta: 'Receta', documento: 'Documento', orden: 'Orden de estudios' };

    if (!form) {
        return;
    }

    function mostrarError(mensaje) {
        resultado.hidden = true;
        errorBox.hidden = false;
        errorBox.textContent = mensaje;
    }

    function escapar(texto) {
        var div = document.createElement('div');
        div.textContent = texto == null ? '' : String(texto);
        return div.innerHTML;
    }

    function renderContenido(tipo, datos) {
        if (tipo === 'receta') {
            return (datos.detalle || []).map(function (item) {
                return '<div class="pedido-detalle-item"><strong>' + escapar(item.medicamento) + '</strong>'
                    + '<div>' + [item.dosis, item.via, item.frecuencia, item.duracion].filter(Boolean).map(escapar).join(' · ') + '</div>'
                    + (item.instrucciones ? '<div>' + escapar(item.instrucciones) + '</div>' : '')
                    + '</div>';
            }).join('') || '<p>Sin medicamentos registrados.</p>';
        }

        if (tipo === 'orden') {
            var estudios = (datos.detalle || []).map(function (item) {
                return '<div class="pedido-detalle-item"><strong>' + escapar(item.estudio) + '</strong>'
                    + (item.observaciones ? '<div>' + escapar(item.observaciones) + '</div>' : '') + '</div>';
            }).join('') || '<p>Sin estudios registrados.</p>';

            var resultados = (datos.resultados || []).map(function (item) {
                return '<div class="pedido-detalle-item">' + (item.texto ? escapar(item.texto) : 'Archivo adjunto') + '</div>';
            }).join('');

            return estudios + (resultados ? '<h3>Resultados</h3>' + resultados : '');
        }

        return '<p>' + escapar(datos.contenido || 'Sin contenido.') + '</p>';
    }

    form.addEventListener('submit', function (evento) {
        evento.preventDefault();
        errorBox.hidden = true;

        var codigo = form.codigo.value.trim();
        var acceso = form.acceso.value.trim();

        if (codigo === '' || !/^\d{4}$/.test(acceso)) {
            mostrarError('Ingresa el código del pedido y el código de acceso de 4 dígitos.');
            return;
        }

        var params = new URLSearchParams({ codigo: codigo, acceso: acceso });

        fetch('/api/public/pedido?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (respuesta) {
                return respuesta.json().then(function (cuerpo) {
                    return { ok: respuesta.ok, cuerpo: cuerpo };
                });
            })
            .then(function (resultadoFetch) {
                if (!resultadoFetch.ok || !resultadoFetch.cuerpo.ok) {
                    mostrarError((resultadoFetch.cuerpo.error && resultadoFetch.cuerpo.error.message) || 'No se pudo consultar el pedido.');
                    return;
                }

                var datos = resultadoFetch.cuerpo.data;
                resultado.querySelector('[data-pedido-titulo]').textContent = etiquetas[datos.tipo] || 'Pedido';
                resultado.querySelector('[data-pedido-paciente]').textContent = (datos.paciente && datos.paciente.nombre_completo) || '—';
                resultado.querySelector('[data-pedido-medico]').textContent = (datos.medico && datos.medico.nombre_completo) || '—';
                resultado.querySelector('[data-pedido-fecha]').textContent = datos.fecha || '—';
                resultado.querySelector('[data-pedido-codigo]').textContent = datos.codigo || '—';
                resultado.querySelector('[data-pedido-contenido]').innerHTML = renderContenido(datos.tipo, datos);
                resultado.hidden = false;
            })
            .catch(function () {
                mostrarError('No se pudo consultar el pedido. Intenta nuevamente.');
            });
    });

    var imprimirBtn = document.querySelector('[data-pedido-imprimir]');

    if (imprimirBtn) {
        imprimirBtn.addEventListener('click', function () {
            window.print();
        });
    }
})();
</script>
