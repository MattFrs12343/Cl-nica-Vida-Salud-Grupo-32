/**
 * Animación del personaje del asistente (Dr. Alex) — capa puramente visual,
 * desacoplada de kiosco-sintomas.js. Solo observa el atributo `hidden` del
 * modal del chat y alterna clases CSS; no toca el árbol de síntomas ni su
 * lógica.
 *
 * - Al abrirse el modal: aparece grande, superpuesto por encima de él (que
 *   se difumina detrás), presentándose.
 * - Espera a que se toque cualquier zona de la pantalla (sin temporizador
 *   automático) para desplazarse a su posición final junto al chat.
 * - Al cerrarse el modal: se oculta, para repetir la presentación la
 *   próxima vez que se abra.
 */
(() => {
    'use strict';

    const modal = document.querySelector('[data-chat-modal]');
    const buddy = document.querySelector('[data-chat-buddy]');

    if (!modal || !buddy) {
        return;
    }

    function continuar() {
        buddy.classList.remove('is-presenting');
        modal.classList.remove('is-presenting');
    }

    function presentar() {
        buddy.classList.add('is-visible', 'is-presenting');
        modal.classList.add('is-presenting');
    }

    function ocultar() {
        buddy.classList.remove('is-visible', 'is-presenting');
        modal.classList.remove('is-presenting');
    }

    modal.addEventListener('click', () => {
        if (modal.classList.contains('is-presenting')) {
            continuar();
        }
    });

    const observer = new MutationObserver(() => {
        if (modal.hidden) {
            ocultar();
        } else {
            presentar();
        }
    });
    observer.observe(modal, { attributes: true, attributeFilter: ['hidden'] });

    if (!modal.hidden) {
        presentar();
    }
})();
