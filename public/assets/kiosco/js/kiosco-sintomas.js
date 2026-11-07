/**
 * Chatbot de síntomas del kiosco (demo) — árbol de decisión local, sin IA
 * ni llamadas externas. Es lo primero que ve el paciente: se abre solo, a
 * pantalla completa, apenas el kiosco muestra la bienvenida (se reabre cada
 * vez que se vuelve a esa pantalla). Describe su síntoma en texto libre, el
 * árbol primero adelanta qué especialidades podrían atenderlo y luego hace
 * 1-2 preguntas de opción múltiple para confirmar una. Como todavía no
 * sabemos su edad/sexo (eso filtra las especialidades elegibles), la
 * recomendación no se aplica aquí — `window.KioscoAsistente.agendarConRecomendacion`
 * manda al paciente a ingresar su cédula, y kiosco.js la aplica solo si es
 * elegible una vez que `loadSpecialties()` carga su lista filtrada. Cerrar
 * el chat deja ver la bienvenida real, con sus botones de siempre.
 */
(() => {
    'use strict';

    /**
     * Cada nodo es una hoja (`resultado`: nombre exacto de la especialidad)
     * o una pregunta (`pregunta` + `opciones`, donde cada opción es a su vez
     * un nodo). Las raíces además llevan `keywords` para el primer match.
     *
     * Cada camino tiene al menos 3 preguntas antes de llegar a una hoja, para
     * estar más seguros de la recomendación. Los nodos de 2º/3er nivel que se
     * repiten en varias ramas se definen una sola vez (p. ej. `Q3_*`) y se
     * reusan con spread (`...Q3_X`) para no duplicar contenido.
     */
    const Q3_PEDIATRIA_EDAD = {
        pregunta: '¿Es menor de 5 años?',
        opciones: [
            { texto: 'Sí', resultado: 'Pediatría' },
            { texto: 'No', resultado: 'Pediatría' },
        ],
    };

    const Q2_PEDIATRIA_DECAIMIENTO = {
        pregunta: '¿Tiene decaimiento o no quiere comer?',
        opciones: [
            { texto: 'Sí', ...Q3_PEDIATRIA_EDAD },
            { texto: 'No', ...Q3_PEDIATRIA_EDAD },
        ],
    };

    const Q3_GERIATRIA_VIVE = {
        pregunta: '¿Vive solo o acompañado?',
        opciones: [
            { texto: 'Vive solo', resultado: 'Geriatría' },
            { texto: 'Vive acompañado', resultado: 'Geriatría' },
        ],
    };

    const Q2_GERIATRIA_MEDICAMENTO = {
        pregunta: '¿Toma algún medicamento de forma regular?',
        opciones: [
            { texto: 'Sí', ...Q3_GERIATRIA_VIVE },
            { texto: 'No', ...Q3_GERIATRIA_VIVE },
        ],
    };

    const Q3_GINECO_SINTOMA = {
        pregunta: '¿Tienes dolor o sangrado actualmente?',
        opciones: [
            { texto: 'Sí', resultado: 'Ginecología' },
            { texto: 'No', resultado: 'Ginecología' },
        ],
    };

    const Q2_GINECO_PRIMERA_VEZ = {
        pregunta: '¿Es tu primera consulta de este tipo en la clínica?',
        opciones: [
            { texto: 'Sí', ...Q3_GINECO_SINTOMA },
            { texto: 'No', ...Q3_GINECO_SINTOMA },
        ],
    };

    const Q3_CARDIO_REPOSO = {
        pregunta: '¿Esto ocurre en reposo o solo al hacer esfuerzo?',
        opciones: [
            { texto: 'En reposo', resultado: 'Cardiología' },
            { texto: 'Solo al esfuerzo', resultado: 'Cardiología' },
        ],
    };

    const Q2_CARDIO_IRRADIA = {
        pregunta: '¿El dolor se irradia al brazo o la mandíbula?',
        opciones: [
            { texto: 'Sí', ...Q3_CARDIO_REPOSO },
            { texto: 'No', ...Q3_CARDIO_REPOSO },
        ],
    };

    const Q3_GENERAL_PRESION = {
        pregunta: '¿Duele más al presionar o mover esa zona?',
        opciones: [
            { texto: 'Sí', resultado: 'Medicina General' },
            { texto: 'No', resultado: 'Medicina General' },
        ],
    };

    const Q3_GASTRO_EMPEORA = {
        pregunta: '¿El dolor empeora después de comer?',
        opciones: [
            { texto: 'Sí', resultado: 'Gastroenterología' },
            { texto: 'No', resultado: 'Gastroenterología' },
        ],
    };

    const Q2_GASTRO_INICIO = {
        pregunta: '¿El malestar empezó de golpe o fue progresivo?',
        opciones: [
            { texto: 'De golpe', ...Q3_GASTRO_EMPEORA },
            { texto: 'Progresivo', ...Q3_GASTRO_EMPEORA },
        ],
    };

    const Q2_GASTRO_UBICACION = {
        pregunta: '¿Eres mujer y el dolor está en la parte baja del abdomen?',
        opciones: [
            {
                texto: 'Sí',
                pregunta: '¿Tiene relación con tu ciclo menstrual?',
                opciones: [
                    { texto: 'Sí', resultado: 'Ginecología' },
                    { texto: 'No', resultado: 'Ginecología' },
                ],
            },
            { texto: 'No', ...Q3_GASTRO_EMPEORA },
        ],
    };

    const Q3_DERMA_TIEMPO = {
        pregunta: '¿Apareció hace poco o es algo recurrente?',
        opciones: [
            { texto: 'Hace poco', resultado: 'Dermatología' },
            { texto: 'Es recurrente', resultado: 'Dermatología' },
        ],
    };

    const Q3_OTORRINO_LADO = {
        pregunta: '¿La molestia es de un solo lado o de ambos?',
        opciones: [
            { texto: 'Un lado', resultado: 'Otorrinolaringología' },
            { texto: 'Ambos lados', resultado: 'Otorrinolaringología' },
        ],
    };

    const Q3_OTORRINO_AGACHAR = {
        pregunta: '¿La presión aumenta al agacharte?',
        opciones: [
            { texto: 'Sí', resultado: 'Otorrinolaringología' },
            { texto: 'No', resultado: 'Otorrinolaringología' },
        ],
    };

    const Q2_OTORRINO_CONGESTION = {
        pregunta: '¿Tienes la nariz congestionada o tapada?',
        opciones: [
            { texto: 'Sí', ...Q3_OTORRINO_AGACHAR },
            { texto: 'No', ...Q3_OTORRINO_AGACHAR },
        ],
    };

    const Q3_GENERAL_DIAS = {
        pregunta: '¿Hace cuántos días tienes malestar general?',
        opciones: [
            { texto: 'Menos de 3 días', resultado: 'Medicina General' },
            { texto: 'Más de 3 días', resultado: 'Medicina General' },
        ],
    };

    const Q3_GENERAL_FRECUENCIA = {
        pregunta: '¿Este tipo de dolor te pasa seguido?',
        opciones: [
            { texto: 'Sí, frecuente', resultado: 'Medicina General' },
            { texto: 'No, es raro en mí', resultado: 'Medicina General' },
        ],
    };

    const Q2_GENERAL_RESFRIO = {
        pregunta: '¿Tienes fiebre o síntomas de resfrío junto al dolor?',
        opciones: [
            { texto: 'Sí', ...Q3_GENERAL_FRECUENCIA },
            { texto: 'No', ...Q3_GENERAL_FRECUENCIA },
        ],
    };

    const Q3_GENERAL_MEDICAMENTO = {
        pregunta: '¿La fiebre ha bajado con algún medicamento?',
        opciones: [
            { texto: 'Sí', resultado: 'Medicina General' },
            { texto: 'No', resultado: 'Medicina General' },
        ],
    };

    const Q2_GENERAL_CUERPO = {
        pregunta: '¿Tienes dolor de cuerpo o escalofríos?',
        opciones: [
            { texto: 'Sí', ...Q3_GENERAL_MEDICAMENTO },
            { texto: 'No', ...Q3_GENERAL_MEDICAMENTO },
        ],
    };

    const Q3_CUERPO_LIMITA = {
        pregunta: '¿Te limita para moverte con normalidad?',
        opciones: [
            { texto: 'Sí', resultado: 'Medicina General' },
            { texto: 'No', resultado: 'Medicina General' },
        ],
    };

    const Q2_CUERPO_HINCHAZON = {
        pregunta: '¿Hay hinchazón o moretón en la zona?',
        opciones: [
            { texto: 'Sí', ...Q3_CUERPO_LIMITA },
            { texto: 'No', ...Q3_CUERPO_LIMITA },
        ],
    };

    const RAMAS = [
        {
            id: 'nino',
            keywords: ['nino', 'nina', 'bebe', 'hijo', 'hija'],
            pregunta: '¿La fiebre supera los 38.5°C o dura más de 2 días?',
            opciones: [
                { texto: 'Sí', ...Q2_PEDIATRIA_DECAIMIENTO },
                { texto: 'No', ...Q2_PEDIATRIA_DECAIMIENTO },
            ],
        },
        {
            id: 'adulto_mayor',
            keywords: ['abuelo', 'abuela', 'adulto mayor', 'anciano', 'anciana', 'vejez'],
            pregunta: '¿Es un chequeo rutinario o hay un síntoma nuevo que te preocupa?',
            opciones: [
                { texto: 'Chequeo rutinario', ...Q2_GERIATRIA_MEDICAMENTO },
                { texto: 'Hay un síntoma nuevo', ...Q2_GERIATRIA_MEDICAMENTO },
            ],
        },
        {
            id: 'embarazo',
            keywords: ['embarazo', 'embarazada', 'menstrua', 'regla', 'ginecolog'],
            pregunta: '¿Es un control de embarazo o una consulta ginecológica general?',
            opciones: [
                { texto: 'Control de embarazo', ...Q2_GINECO_PRIMERA_VEZ },
                { texto: 'Consulta general', ...Q2_GINECO_PRIMERA_VEZ },
            ],
        },
        {
            id: 'pecho',
            keywords: ['pecho', 'corazon', 'palpita', 'taquicardia'],
            pregunta: '¿Sientes el corazón acelerado o palpitaciones?',
            opciones: [
                { texto: 'Sí', ...Q2_CARDIO_IRRADIA },
                {
                    texto: 'No',
                    pregunta: '¿El dolor aumenta al toser, respirar hondo o moverte?',
                    opciones: [
                        { texto: 'Sí, parece muscular', ...Q3_GENERAL_PRESION },
                        { texto: 'No, es un dolor constante', ...Q3_CARDIO_REPOSO },
                    ],
                },
            ],
        },
        {
            id: 'estomago',
            keywords: ['estomago', 'abdomen', 'panza', 'barriga', 'vientre'],
            pregunta: '¿El dolor viene con náuseas, vómitos o diarrea?',
            opciones: [
                { texto: 'Sí', ...Q2_GASTRO_INICIO },
                { texto: 'No', ...Q2_GASTRO_UBICACION },
            ],
        },
        {
            id: 'piel',
            keywords: ['piel', 'erupcion', 'alergia', 'picazon', 'grano', 'mancha', 'ronchas'],
            pregunta: '¿Tienes fiebre además de la molestia en la piel?',
            opciones: [
                {
                    texto: 'Sí',
                    pregunta: '¿La molestia en la piel pica o arde?',
                    opciones: [
                        { texto: 'Pica', ...Q3_GENERAL_PRESION },
                        { texto: 'Arde', ...Q3_GENERAL_PRESION },
                    ],
                },
                {
                    texto: 'No',
                    pregunta: '¿La zona afectada está inflamada o con ronchas?',
                    opciones: [
                        { texto: 'Sí', ...Q3_DERMA_TIEMPO },
                        { texto: 'No', ...Q3_DERMA_TIEMPO },
                    ],
                },
            ],
        },
        {
            id: 'garganta',
            keywords: ['garganta', 'oido', 'nariz', 'congestion', 'ronquera'],
            pregunta: '¿Tienes fiebre alta o malestar general fuerte?',
            opciones: [
                {
                    texto: 'Sí',
                    pregunta: '¿El malestar te impide hacer tus actividades normales?',
                    opciones: [
                        { texto: 'Sí', ...Q3_GENERAL_DIAS },
                        { texto: 'No', ...Q3_GENERAL_DIAS },
                    ],
                },
                {
                    texto: 'No, es solo garganta, oído o nariz',
                    pregunta: '¿Tienes dolor al tragar o al mover la mandíbula?',
                    opciones: [
                        { texto: 'Sí', ...Q3_OTORRINO_LADO },
                        { texto: 'No', ...Q3_OTORRINO_LADO },
                    ],
                },
            ],
        },
        {
            id: 'cabeza',
            keywords: ['cabeza', 'migrana', 'jaqueca'],
            pregunta: '¿Sientes presión o dolor en la cara, cerca de la nariz o los ojos?',
            opciones: [
                { texto: 'Sí', ...Q2_OTORRINO_CONGESTION },
                { texto: 'No', ...Q2_GENERAL_RESFRIO },
            ],
        },
        {
            id: 'general',
            keywords: ['fiebre', 'gripe', 'resfrio', 'malestar', 'cansancio'],
            pregunta: '¿Hace cuántos días tienes estos síntomas?',
            opciones: [
                { texto: 'Menos de 3 días', ...Q2_GENERAL_CUERPO },
                { texto: 'Más de 3 días', ...Q2_GENERAL_CUERPO },
            ],
        },
        {
            id: 'cuerpo',
            keywords: [
                'pie', 'pies', 'pierna', 'piernas', 'rodilla', 'espalda', 'hueso', 'huesos',
                'articulacion', 'musculo', 'brazo', 'hombro', 'tobillo', 'mano', 'cuello',
            ],
            pregunta: '¿Fue por un golpe o accidente reciente?',
            opciones: [
                { texto: 'Sí, fue un golpe reciente', ...Q2_CUERPO_HINCHAZON },
                { texto: 'No, es un dolor que viene de antes', ...Q2_CUERPO_HINCHAZON },
            ],
        },
    ];

    /**
     * Si el texto libre no matchea ninguna rama (ni por palabra exacta ni por
     * similitud), se ofrecen estas categorías como red de seguridad: el
     * paciente siempre termina con una recomendación, nunca en un callejón
     * sin salida. `ramaId: null` cae directo a Medicina General.
     */
    const CATEGORIAS_FALLBACK = [
        { texto: 'Algo de la piel', ramaId: 'piel' },
        { texto: 'El estómago o abdomen', ramaId: 'estomago' },
        { texto: 'El pecho o el corazón', ramaId: 'pecho' },
        { texto: 'Garganta, oído o nariz', ramaId: 'garganta' },
        { texto: 'Huesos, músculos o articulaciones', ramaId: 'cuerpo' },
        { texto: 'No estoy seguro', ramaId: null },
    ];

    const ESPECIALIDAD_INFO = {
        'Medicina General': { foto: 'especialidad-medicina-general.jpg', descripcion: 'Atención integral para adultos y familias.' },
        Pediatría: { foto: 'especialidad-pediatria.jpg', descripcion: 'Atención de niños y adolescentes.' },
        Geriatría: { foto: 'especialidad-geriatria.jpg', descripcion: 'Atención integral de personas mayores.' },
        Cardiología: { foto: 'especialidad-cardiologia.jpg', descripcion: 'Evaluación y seguimiento cardiovascular.' },
        Ginecología: { foto: 'especialidad-ginecologia.jpg', descripcion: 'Atención de salud femenina.' },
        Dermatología: { foto: 'especialidad-dermatologia.jpg', descripcion: 'Diagnóstico y tratamiento de piel, cabello y uñas.' },
        Otorrinolaringología: { foto: 'especialidad-otorrinolaringologia.jpg', descripcion: 'Atención de oído, nariz y garganta.' },
        Gastroenterología: { foto: 'especialidad-gastroenterologia.jpg', descripcion: 'Atención de malestares digestivos y abdominales.' },
    };

    function normalizar(texto) {
        return String(texto || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '');
    }

    /** Solo letras y espacios: nada de números ni símbolos/puntuación. */
    function sanitizarSintoma(texto) {
        return String(texto || '').replace(/[^\p{L}\s]/gu, '');
    }

    /** Cédula: dígitos y un complemento alfanumérico opcional tras un guion. */
    function sanitizarCedula(texto) {
        return String(texto || '').toUpperCase().replace(/[^0-9A-Z-]/g, '').slice(0, 13);
    }

    function buscarRama(texto) {
        const normalizado = normalizar(texto);

        return RAMAS.find((rama) => rama.keywords.some((keyword) => normalizado.includes(keyword))) || null;
    }

    function distanciaLevenshtein(a, b) {
        const filas = a.length + 1;
        const columnas = b.length + 1;
        const matriz = Array.from({ length: filas }, () => new Array(columnas).fill(0));

        for (let i = 0; i < filas; i += 1) matriz[i][0] = i;
        for (let j = 0; j < columnas; j += 1) matriz[0][j] = j;

        for (let i = 1; i < filas; i += 1) {
            for (let j = 1; j < columnas; j += 1) {
                const costo = a[i - 1] === b[j - 1] ? 0 : 1;
                matriz[i][j] = Math.min(
                    matriz[i - 1][j] + 1,
                    matriz[i][j - 1] + 1,
                    matriz[i - 1][j - 1] + costo
                );
            }
        }

        return matriz[a.length][b.length];
    }

    /**
     * "Autocorrector" del matching: si no hubo coincidencia exacta, busca
     * palabras del texto parecidas (típicos errores de tipeo) a alguna
     * keyword de alguna rama, tolerando 1 error en palabras cortas y 2 en
     * palabras largas.
     */
    function buscarRamaAproximada(texto) {
        const palabras = normalizar(texto).split(/\s+/).filter((palabra) => palabra.length >= 4);
        let mejorRama = null;
        let mejorDistancia = Infinity;

        palabras.forEach((palabra) => {
            RAMAS.forEach((rama) => {
                rama.keywords.forEach((keyword) => {
                    const tolerancia = keyword.length <= 5 ? 1 : 2;
                    const distancia = distanciaLevenshtein(palabra, keyword);

                    if (distancia <= tolerancia && distancia < mejorDistancia) {
                        mejorDistancia = distancia;
                        mejorRama = rama;
                    }
                });
            });
        });

        return mejorRama;
    }

    function recolectarResultados(nodo, acumulado = new Set()) {
        if (nodo.resultado) {
            acumulado.add(nodo.resultado);
        } else {
            nodo.opciones.forEach((opcion) => recolectarResultados(opcion, acumulado));
        }

        return acumulado;
    }

    function formatarLista(items) {
        if (items.length <= 1) {
            return items[0] || '';
        }

        return `${items.slice(0, -1).join(', ')} o ${items[items.length - 1]}`;
    }

    const modal = document.querySelector('[data-chat-modal]');

    if (!modal || !window.KioscoAsistente) {
        return;
    }

    const kioskApp = document.getElementById('kiosk-app');
    const IMG_BASE = ((kioskApp && kioskApp.dataset.imgBase) || '/assets/kiosco/img').replace(/\/$/, '');
    const welcomeScreen = document.querySelector('[data-screen="welcome"]');

    const body = modal.querySelector('[data-chat-body]');
    const form = modal.querySelector('[data-chat-form]');
    const input = modal.querySelector('[data-chat-input]');
    const closeButtons = modal.querySelectorAll('[data-action="chat-close"]');
    const placeholderSintoma = input ? input.placeholder : '';

    /**
     * El input normalmente solo acepta letras (descripción del síntoma). Al
     * pedir la cédula para agendar, cambia a modo "cedula" y se permiten
     * dígitos — en cualquier otro momento quedan bloqueados.
     */
    let modoEntrada = 'sintoma';
    let especialidadParaAgendar = null;

    function esperar(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }

    function scrollToBottom() {
        body.scrollTop = body.scrollHeight;
    }

    async function addBotMessage(texto) {
        const typing = document.createElement('div');
        typing.className = 'chat-msg chat-msg-bot chat-typing';
        typing.innerHTML = '<p><span></span><span></span><span></span></p>';
        body.appendChild(typing);
        scrollToBottom();

        await esperar(450);
        typing.remove();

        const bubble = document.createElement('div');
        bubble.className = 'chat-msg chat-msg-bot';
        bubble.innerHTML = '<p></p>';
        bubble.querySelector('p').textContent = texto;
        body.appendChild(bubble);
        scrollToBottom();

        return bubble;
    }

    function addUserMessage(texto) {
        const bubble = document.createElement('div');
        bubble.className = 'chat-msg chat-msg-user';
        bubble.innerHTML = '<p></p>';
        bubble.querySelector('p').textContent = texto;
        body.appendChild(bubble);
        scrollToBottom();
    }

    function addOptions(opciones, onPick) {
        const group = document.createElement('div');
        group.className = 'chat-options';

        opciones.forEach((opcion) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = opcion.texto;
            button.addEventListener('click', () => {
                group.querySelectorAll('button').forEach((item) => { item.disabled = true; });
                onPick(opcion);
            });
            group.appendChild(button);
        });

        body.appendChild(group);
        scrollToBottom();
    }

    function addActionButton(texto, onClick, variante = 'soft') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn btn-${variante} btn-sm chat-action`;
        button.textContent = texto;
        button.addEventListener('click', onClick);
        body.appendChild(button);
        scrollToBottom();
        return button;
    }

    function addResultCard(nombreEspecialidad) {
        const info = ESPECIALIDAD_INFO[nombreEspecialidad] || {};
        const card = document.createElement('div');
        card.className = 'chat-result';
        card.innerHTML = `
            ${info.foto ? `<img src="${IMG_BASE}/${info.foto}" alt="">` : ''}
            <div class="chat-result-body">
                <span class="chat-result-kicker">Te recomendamos</span>
                <strong></strong>
                <p></p>
            </div>
        `;
        card.querySelector('strong').textContent = nombreEspecialidad;
        card.querySelector('p').textContent = info.descripcion || '';
        body.appendChild(card);
        scrollToBottom();
    }

    async function renderNodo(nodo) {
        if (nodo.resultado) {
            await resolver(nodo.resultado);
            return;
        }

        await addBotMessage(nodo.pregunta);
        addOptions(nodo.opciones, async (opcion) => {
            addUserMessage(opcion.texto);
            await renderNodo(opcion);
        });
    }

    async function resolver(nombreEspecialidad) {
        addResultCard(nombreEspecialidad);
        await addBotMessage('¿Quieres agendar una cita para esto?');
        addOptions(
            [{ texto: 'Sí, quiero agendar' }, { texto: 'No, por ahora no' }],
            async (opcion) => {
                addUserMessage(opcion.texto);

                if (opcion.texto.startsWith('Sí')) {
                    await pedirCedula(nombreEspecialidad);
                    return;
                }

                addActionButton('Ver todas las especialidades', closeChat);
            }
        );
    }

    /** Pide la cédula sin salir del chat: solo aquí se permiten dígitos. */
    async function pedirCedula(nombreEspecialidad) {
        modoEntrada = 'cedula';
        especialidadParaAgendar = nombreEspecialidad;

        await addBotMessage('Decime tu número de cédula (CI) para identificarte.');

        if (form) {
            form.hidden = false;
            if (input) {
                input.value = '';
                input.placeholder = 'Ej: 10000001';
                window.setTimeout(() => input.focus({ preventScroll: true }), 50);
            }
        }
    }

    async function handleCedula(ci) {
        addUserMessage(ci);

        const resultado = await window.KioscoAsistente.identificarYContinuar(ci, especialidadParaAgendar);

        if (resultado.ok) {
            closeChat();
            return;
        }

        if (resultado.motivo === 'no_encontrado') {
            await addBotMessage('No encontramos tu registro con esa cédula. Vamos a la pantalla de identificación para registrarte.');
            const especialidad = especialidadParaAgendar;
            closeChat();
            window.KioscoAsistente.agendarConRecomendacion(especialidad);
            return;
        }

        const mensajes = {
            ci_invalida: 'Esa cédula no parece válida. Escribe solo números (puede llevar un complemento, ej. 12345678-1A).',
            inactivo: 'Tu registro está inactivo. Acércate a recepción para reactivarlo.',
        };

        await addBotMessage(mensajes[resultado.motivo] || 'No pudimos identificarte. Intenta de nuevo.');

        if (resultado.motivo === 'inactivo') {
            addActionButton('Ver todas las especialidades', closeChat);
            return;
        }

        if (form) {
            form.hidden = false;
            if (input) {
                input.value = '';
                window.setTimeout(() => input.focus({ preventScroll: true }), 50);
            }
        }
    }

    async function iniciarRama(rama) {
        const candidatos = Array.from(recolectarResultados(rama));

        if (candidatos.length > 1) {
            await addBotMessage(
                `Por lo que describes, esto podría atenderse en ${formatarLista(candidatos)}. Para confirmarlo, te hago una pregunta rápida:`
            );
        }

        await renderNodo(rama);
    }

    function mostrarCategoriasFallback() {
        addOptions(CATEGORIAS_FALLBACK, async (categoria) => {
            addUserMessage(categoria.texto);

            const ramaElegida = categoria.ramaId ? RAMAS.find((rama) => rama.id === categoria.ramaId) : null;

            if (ramaElegida) {
                await iniciarRama(ramaElegida);
            } else {
                await resolver('Medicina General');
            }
        });
    }

    let yaPidioRedescribir = false;

    async function handleDescripcion(texto) {
        addUserMessage(texto);

        const rama = buscarRama(texto) || buscarRamaAproximada(texto);

        if (!rama) {
            if (!yaPidioRedescribir) {
                yaPidioRedescribir = true;
                await addBotMessage('No logré entender bien eso. ¿Podrías describir tu síntoma de otra forma?');

                if (form) {
                    form.hidden = false;
                    if (input) {
                        input.value = '';
                        window.setTimeout(() => input.focus({ preventScroll: true }), 50);
                    }
                }

                return;
            }

            yaPidioRedescribir = false;
            await addBotMessage('Sigo sin identificarlo con certeza. ¿En cuál de estas áreas encaja mejor lo que sientes?');
            mostrarCategoriasFallback();
            return;
        }

        yaPidioRedescribir = false;
        await iniciarRama(rama);
    }

    async function openChat() {
        body.innerHTML = '';
        yaPidioRedescribir = false;
        modoEntrada = 'sintoma';
        especialidadParaAgendar = null;
        if (form) {
            form.hidden = false;
            if (input) {
                input.value = '';
                input.placeholder = placeholderSintoma;
            }
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');

        await addBotMessage('Hola, contame qué sientes y te ayudo a elegir la especialidad. Por ejemplo: "me duele la cabeza".');

        if (input) {
            window.setTimeout(() => input.focus({ preventScroll: true }), 50);
        }
    }

    function closeChat() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
    }

    closeButtons.forEach((button) => button.addEventListener('click', closeChat));

    if (input) {
        input.addEventListener('input', () => {
            const limpio = modoEntrada === 'cedula'
                ? sanitizarCedula(input.value)
                : sanitizarSintoma(input.value);

            if (limpio !== input.value) {
                input.value = limpio;
            }
        });
    }

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const texto = input ? input.value.trim() : '';

            if (!texto) {
                return;
            }

            form.hidden = true;

            if (modoEntrada === 'cedula') {
                handleCedula(texto);
            } else {
                handleDescripcion(texto);
            }
        });
    }

    // El chat es lo primero que ve el paciente: se abre solo apenas carga el
    // kiosco y cada vez que se vuelve a la pantalla de bienvenida (reset,
    // inactividad, fin de ficha). Cerrarlo deja ver la bienvenida real.
    if (welcomeScreen) {
        const observer = new MutationObserver(() => {
            if (!welcomeScreen.hidden && modal.hidden) {
                openChat();
            }
        });
        observer.observe(welcomeScreen, { attributes: true, attributeFilter: ['hidden', 'class'] });

        if (!welcomeScreen.hidden) {
            openChat();
        }
    }
})();
