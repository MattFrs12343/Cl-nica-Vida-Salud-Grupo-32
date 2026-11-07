# -*- coding: utf-8 -*-
from puds_lib import b


def chapter5():
    b.h1("5. FT. ANÁLISIS")
    b.p("El análisis transforma los requisitos en una comprensión estructurada del dominio y de las responsabilidades del sistema. Se identifican clases de frontera, control y entidad; se relacionan los paquetes con los casos de uso; se realizan los flujos críticos mediante diagramas de comunicación; y se verifica que las clases de análisis tengan correspondencia con las tablas de la base de datos. El resultado sirve de puente entre la captura de requisitos y el diseño arquitectónico.")
    b.p("El análisis se concentra en la trazabilidad: cada caso de uso crítico se realiza con clases concretas, cada regla de negocio se refleja en una validación o transición y cada entidad persistente se conecta con una tabla del esquema PostgreSQL. Los diagramas de análisis no describen todavía decisiones de implementación, sino responsabilidades y colaboraciones. La disciplina se organiza en cuatro actividades: análisis de la arquitectura, análisis de un caso de uso, análisis de las clases y análisis de los paquetes.")

    b.h2("5.1 Análisis de arquitectura")
    b.p("El análisis de arquitectura identifica los paquetes del análisis, los casos de uso que realizan y las clases de entidad que los soportan. La descomposición parte de los paquetes de casos de uso PK-01, PK-02 y PK-03 y agrega PK-04 para los servicios compartidos. El criterio es agrupar por proceso de negocio y por actor, buscar alta cohesión, bajo acoplamiento y dependencias acíclicas, y dejar explícito qué paquete es responsable de cada flujo.")

    b.h3("5.1.1 Identificar paquetes")
    b.p("Para identificar los paquetes se agruparon los casos de uso por proceso de negocio y por actor, y se separaron las responsabilidades transversales que no pertenecen a un flujo funcional concreto. Cada paquete se describe con su nombre, su objetivo, los casos de uso que realiza, los actores que interactúan con él y las entidades que administra. La tabla siguiente utiliza la notación UML de paquete y resume la identificación realizada.")
    b.table(
        ["Paquete del análisis", "Descripción y responsabilidad"],
        [
            (("img", "paquete_pk01.png", 4.6), "Agrupa el acceso público, el registro y la reserva web de citas, además de la consulta y cancelación por parte del paciente. Es el paquete de entrada al sistema y concentra la validación de disponibilidad del canal web."),
            (("img", "paquete_pk02.png", 4.6), "Agrupa la operación de citas y agenda: gestión de pacientes, programación, reprogramación, cancelación, control de asistencia, reconsultas y consulta de la agenda e historial."),
            (("img", "paquete_pk03.png", 4.6), "Agrupa la atención presencial y la administración: orientación por síntomas en kiosco (chatbot Dr. Alex), emisión de fichas en kiosco, registro de consultas, generación de recetas, documentos y órdenes, pagos, catálogos, reportes y auditoría."),
            (("img", "paquete_pk04.png", 4.6), "Agrupa los servicios compartidos de autenticación, autorización, persistencia, correo, auditoría y configuración. No contiene casos de uso de negocio; provee infraestructura a los demás paquetes."),
        ],
        [5.3, 11.7],
        caption="Identificación de los paquetes del análisis",
        font_size=8.6,
    )
    b.p("PK-01 se concentra en el paciente y en el canal público; PK-02 en la recepción y en la agenda; PK-03 en el médico, el kiosco y la administración; PK-04 en los servicios que comparten los tres paquetes funcionales. Esta separación evita que la lógica de autenticación, correo o auditoría quede dispersa y permite reemplazar un servicio transversal sin reescribir los flujos de negocio.")
    b.table(
        ["Paquete", "Objetivo", "Casos de uso", "Actores", "Entidades y tablas", "Dependencias"],
        [
            ("PK-01 Acceso público y reserva web", "Permitir el acceso público, el registro, la reserva web y la consulta o cancelación de citas del paciente.", "UC01–UC07", "Público, paciente, usuario autenticado", "Usuario, Paciente, Medico, Especialidad, Horario, Cita, Ficha\nusuarios, pacientes, medicos, especialidades, horarios, citas, fichas", "PK-02 (disponibilidad y registro), PK-04 (seguridad, correo y auditoría)"),
            ("PK-02 Citas y agenda", "Gestionar pacientes, agenda, programación, reprogramación, cancelación, asistencia y reconsultas.", "UC08–UC14", "Recepcionista, recepcionista de médico, médico", "Paciente, Medico, Especialidad, Horario, Bloqueo, Cita, HistorialEstado, Ficha\npacientes, medicos, especialidades, horarios, bloqueos, citas, citas_estado_historial, fichas", "PK-01 (reserva web), PK-04 (persistencia y auditoría)"),
            ("PK-03 Atención y administración", "Orientar por síntomas en kiosco, emitir fichas, registrar la atención, generar pedidos y pagos, administrar catálogos y producir reportes.", "UC15–UC25", "Kiosco, paciente presencial, médico, recepción, administrador", "Cita, Ficha, Consulta, Receta, Documento, Orden, Pago, Auditoria; árbol de síntomas en frontend\ncitas, fichas, consultas, recetas, receta_detalle, documentos, ordenes, orden_detalle, resultados, pagos, auditoria", "PK-01 (reserva), PK-02 (agenda), PK-04 (servicios); reutiliza /api/kiosco/identificar"),
            ("PK-04 Servicios compartidos", "Proveer autenticación, autorización, persistencia, correo, auditoría y configuración compartida.", "Transversal", "Todos los roles", "Usuario, Auditoria, ColaCorreo\nusuarios, auditoria, cola_correos", "Ninguna hacia PK-01, PK-02 ni PK-03"),
        ],
        [2.5, 3.6, 1.7, 2.4, 4.3, 2.5],
        caption="Paquetes del análisis: objetivos, actores, entidades y dependencias",
        font_size=7.1,
    )
    b.figure("21_paquetes_analisis.png", 12.5, "Dependencias entre los paquetes del análisis")
    b.p("Las dependencias se orientan desde los paquetes de aplicación hacia los servicios compartidos. PK-01 depende de PK-02 porque la reserva web consulta la disponibilidad y registra la cita; PK-02 depende de PK-04 para persistencia, transacciones y auditoría; PK-03 depende de PK-01 y PK-02 porque la ficha y la consulta operan sobre citas ya reservadas. PK-04 no depende de los paquetes de aplicación, lo que permite sustituir el correo, el almacenamiento o la auditoría sin afectar los flujos de negocio.")

    b.h3("5.1.2 Relacionar paquete y casos de uso")
    b.p("Cada paquete se relaciona con los casos de uso que realiza mediante una dependencia «trace». La traza permite verificar que ningún caso de uso quede sin paquete y que cada paquete tenga un conjunto coherente de responsabilidades. Las relaciones «include» y «extend» se conservan dentro de la vista porque explican la reutilización de flujos y los comportamientos opcionales que atraviesan el límite del paquete.")
    b.table(
        ["Paquete", "Casos de uso", "Relaciones «include» / «extend»", "Actores"],
        [
            ("PK-01 Acceso público y reserva web", "UC01–UC07", "«include» UC05→UC02\n«extend» UC06→UC07", "Público, paciente"),
            ("PK-02 Citas y agenda", "UC08–UC14", "«include» UC09→UC04 (PK-01)\n«extend» UC11→UC14 y UC13→UC12", "Recepcionista, recepcionista de médico, médico"),
            ("PK-03 Atención y administración", "UC15–UC25", "«include» UC15→UC05 (PK-01)\n«extend» UC25→UC15\n«extend» UC18→UC17\nAuditoría transversal UC24", "Kiosco, paciente presencial, médico, recepción, administrador"),
        ],
        [3.2, 3.0, 6.0, 4.8],
        caption="Trazabilidad entre paquetes, casos de uso y relaciones",
        font_size=7.9,
    )
    b.figure("46_relacion_pk01.png", 15.5, "Relación del paquete PK-01 con los casos de uso de acceso público y reserva web")
    b.figure("47_relacion_pk02.png", 15.5, "Relación del paquete PK-02 con los casos de uso de citas y agenda", max_height_cm=18.5)
    b.figure("48_relacion_pk03_atencion.png", 15.5, "Relación del paquete PK-03 con los casos de uso de atención clínica y kiosco")
    b.figure("49_relacion_pk03_admin.png", 15.5, "Relación del paquete PK-03 con los casos de uso de administración y control")
    b.p("La cobertura queda completa: PK-01 realiza UC01–UC07, PK-02 realiza UC08–UC14 y PK-03 realiza UC15–UC25. Las relaciones de inclusión se resuelven en el paquete que contiene el caso reutilizado; por ejemplo, UC09 incluye UC04, que pertenece a PK-01, y UC15 incluye UC05, también de PK-01. Las extensiones se mantienen en el paquete que contiene el caso base: la cancelación en recepción extiende la consulta de agenda, la gestión de reconsultas extiende el control de asistencia y UC25 puede derivar al flujo de ficha cuando el paciente acepta agendar.")

    b.h3("5.1.3 Vista de paquetes")
    b.p("La vista de paquetes muestra el límite de cada paquete, los actores que interactúan con él y los casos de uso que contiene. A diferencia de la vista global de casos de uso, esta vista hace explícita la asignación de responsabilidades: un mismo actor puede aparecer en varios paquetes, pero cada caso de uso pertenece a un único paquete. También se muestran las relaciones «include» y «extend» y, cuando corresponde, el paquete relacionado que aporta el caso reutilizado.")
    b.figure("50_vista_pk01.png", 15.5, "Vista del paquete PK-01: actores y casos de uso de acceso público y reserva web")
    b.figure("51_vista_pk02.png", 15.5, "Vista del paquete PK-02: actores y casos de uso de citas y agenda", max_height_cm=20.0)
    b.figure("52_vista_pk03.png", 15.5, "Vista del paquete PK-03: actores y casos de uso de atención y administración", max_height_cm=20.5)
    b.table(
        ["Actor", "Paquete(s)", "Casos de uso"],
        [
            ("Público", "PK-01", "UC01, UC02"),
            ("Paciente", "PK-01", "UC03, UC04, UC05, UC06, UC07"),
            ("Recepcionista", "PK-02; PK-03", "PK-02: UC08–UC14; PK-03: UC17, UC18, UC19"),
            ("Recepcionista de médico", "PK-02", "UC08–UC14, con alcance limitado a sus médicos asignados"),
            ("Médico", "PK-02; PK-03", "PK-02: UC12, UC13, UC14; PK-03: UC16, UC17, UC18, UC19"),
            ("Kiosco", "PK-03", "UC15"),
            ("Paciente presencial", "PK-03", "UC25"),
            ("Administrador", "PK-03", "UC20, UC21, UC22, UC23, UC24"),
        ],
        [3.4, 3.0, 9.6],
        caption="Actores y paquetes del análisis",
        font_size=8.2,
    )
    b.p("La vista confirma que los actores operativos se distribuyen entre PK-02 y PK-03, mientras que el paciente se concentra en PK-01 y el kiosco en PK-03. Esta distribución sustenta la separación de permisos del diseño: el paciente no accede a la agenda interna, el kiosco solo emite fichas y el administrador no registra consultas clínicas. PK-04 no aparece con actores propios porque es un paquete de servicios consumido por los demás.")

    b.h3("5.1.4 Casos de uso significativos y requisitos especiales")
    b.p("Los casos de uso significativos para la arquitectura son los que concentran los riesgos de seguridad, concurrencia, transacción, persistencia o confidencialidad. Se seleccionan UC04 Iniciar sesión, UC05 Reservar cita web, UC09 Programar cita, UC15 Emitir ficha en kiosco, UC16 Registrar consulta y UC21 Configurar horarios y cupos. Los cinco primeros se analizan con diagramas de comunicación y de clases en las secciones 5.2 y 5.3; UC21 se refleja en el análisis del paquete PK-03 y en el diseño de datos.")
    b.table(
        ["Requisito especial", "Origen", "Decisión de análisis"],
        [
            ("Concurrencia en la reserva", "RF07 / RN07", "Coordinar la validación y la inserción en una única transacción con bloqueo del slot."),
            ("Persistencia e integridad", "RN14 / RNF05", "Separar entidades de dominio y delegar la integridad referencial a PostgreSQL."),
            ("Seguridad y confidencialidad", "RNF03 / RLS", "Aislar los datos clínicos en el paquete de atención y filtrar por rol."),
            ("Trazabilidad", "RNF09 / RX13", "Registrar cambios de catálogos mediante auditoría transversal."),
            ("Disponibilidad de cupos", "RX01", "Concentrar el cálculo de cupos web/kiosco en DisponibilidadService."),
        ],
        [4.0, 3.2, 8.8],
        caption="Requisitos especiales identificados en el análisis",
        font_size=8.2,
    )

    b.h2("5.2 Análisis de un caso de uso")
    b.p("El análisis de un caso de uso describe cómo las clases de frontera, control y entidad colaboran para realizar un flujo. Se seleccionan los casos de uso P1 Crítica que representan riesgos distintos: autenticación, reserva web, programación en recepción, emisión de ficha en kiosco y registro de consulta. La selección evita repetir flujos equivalentes y cubre los canales web, presencial y clínico.")
    b.p("Para cada caso se elabora un diagrama de comunicación, que muestra los objetos participantes, los enlaces y los mensajes numerados en orden. Como complemento, la sección 5.2.2 conserva las trazas de secuencia que detallan el orden temporal y las alternativas; ambas vistas mantienen la misma numeración conceptual y la misma traza con el caso de uso del modelo de requisitos.")

    b.h3("5.2.1 Diagramas de comunicación")
    b.p("El diagrama de comunicación muestra los objetos como nodos, los enlaces como líneas y los mensajes como etiquetas numeradas. Los objetos se estereotipan como «boundary», «control», «entity» o «database». Las flechas → y ← dentro de la etiqueta indican el sentido del mensaje; un mismo enlace puede transportar varios mensajes, lo que reduce el ruido visual y permite leer la colaboración completa sin una línea de vida temporal.")
    b.table(
        ["Caso de uso", "Paquete", "Actor principal", "Criterio de selección", "Objetos principales"],
        [
            ("UC04 Iniciar sesión", "PK-01", "Usuario interno o paciente", "Puerta de entrada y control de intentos; aplica a todos los roles.", "LoginView, AuthController, UsuarioModel, PostgreSQL"),
            ("UC05 Reservar cita web", "PK-01", "Paciente", "Reserva transaccional con cupo web y prevención de superposición.", "ReservaWebView, CitaService, DisponibilidadService, PostgreSQL"),
            ("UC09 Programar cita", "PK-02", "Recepcionista", "Programación en recepción; incluye UC04 y revalida disponibilidad.", "AgendaView, AuthController, CitaService, DisponibilidadService, PostgreSQL"),
            ("UC15 Emitir ficha en kiosco", "PK-03", "Kiosco / paciente", "Ficha en kiosco; incluye UC05, token HMAC y cupo kiosco.", "KioscoView, KioscoController, CitaService, FichaService, PostgreSQL"),
            ("UC16 Registrar consulta", "PK-03", "Médico", "Registro clínico y generación de pedidos; cierra la atención.", "HistoriaView, ConsultaService, PedidoService, PostgreSQL"),
        ],
        [3.2, 1.8, 2.8, 5.6, 3.8],
        caption="Selección de casos de uso para los diagramas de comunicación",
        font_size=7.7,
    )
    b.figure("53_com_uc04.png", 10.5, "Comunicación de UC04 Iniciar sesión", max_height_cm=18.0)
    b.figure("54_com_uc05.png", 11.5, "Comunicación de UC05 Reservar cita web", max_height_cm=18.5)
    b.figure("55_com_uc09.png", 15.5, "Comunicación de UC09 Programar cita en recepción", max_height_cm=18.5)
    b.figure("56_com_uc15.png", 13.5, "Comunicación de UC15 Emitir ficha en kiosco", max_height_cm=18.5)
    b.figure("57_com_uc16.png", 11.5, "Comunicación de UC16 Registrar consulta", max_height_cm=18.5)
    b.table(
        ["Caso de uso", "Mensajes principales", "Entidades y tablas"],
        [
            ("UC04", "1 ingresar; 2 autenticar; 3 buscar credencial; 4 SELECT usuarios; 5 registro; 6 usuario + hash; 7 validar credenciales; 8 crear sesión; 9 redirigir.", "usuarios"),
            ("UC05", "1 seleccionar especialidad; 2 buscar médicos; 3 consultar disponibilidad; 4 SELECT horarios, citas y bloqueos; 7 confirmar; 8 registrar cita; 9 revalidar; 12 INSERT citas y fichas; 14 mostrar ficha.", "horarios, citas, fichas, medicos, especialidades"),
            ("UC09", "1 iniciar sesión; 2 autenticar; 6 buscar paciente; 9 seleccionar; 10 consultar disponibilidad; 15 registrar cita; 19 INSERT citas y fichas; 21 comprobante.", "usuarios, pacientes, horarios, citas, fichas"),
            ("UC15", "1 iniciar sesión kiosco; 2 firmar token; 4 identificar paciente; 8 consultar disponibilidad; 14 registrar cita; 17 emitir ficha; 18 INSERT fichas y UPDATE citas; 21 imprimir.", "pacientes, horarios, citas, fichas"),
            ("UC16", "1 abrir cita; 2 consultar cita; 6 registrar consulta; 8 INSERT consultas y UPDATE citas; 11 crear receta; 14 crear orden; 16 pedido generado; 17 mostrar.", "citas, consultas, recetas, ordenes"),
        ],
        [2.4, 9.4, 4.2],
        caption="Mensajes principales y entidades de los diagramas de comunicación",
        font_size=7.7,
    )
    b.p("La lectura conjunta de los cinco diagramas muestra un patrón estable: una frontera captura la intención del actor, un control coordina la validación y la transacción, y un modelo o la base de datos concentra la persistencia. La diferencia entre canales está en la frontera y en el cupo utilizado, no en la lógica de negocio: la reserva web y la programación en recepción comparten CitaService y DisponibilidadService; el kiosco agrega KioscoController y FichaService; la consulta agrega ConsultaService y PedidoService.")

    b.h3("5.2.2 Trazas de secuencia complementarias")
    b.p("Las trazas de secuencia conservan el detalle temporal de las mismas colaboraciones y permiten revisar las alternativas de error, los bloqueos y las transacciones. Se mantienen como complemento de los diagramas de comunicación y no como sustitutos: la comunicación enfatiza los objetos y enlaces; la secuencia enfatiza el orden y las condiciones.")
    b.figure("08_sec_login.png", 15.8, "Secuencia de inicio de sesión y control de intentos")
    b.figure("09_sec_reserva.png", 16.0, "Secuencia de reserva de cita con validación de disponibilidad")
    b.figure("10_sec_kiosco.png", 16.0, "Secuencia de emisión de ficha en el kiosco")
    b.figure("11_sec_cancelar.png", 15.8, "Secuencia de cancelación de cita")
    b.figure("12_sec_consulta.png", 16.0, "Secuencia de registro de consulta y generación de pedidos")

    b.h3("5.2.3 Ciclo de vida de la cita")
    b.p("La cita posee un ciclo de vida controlado. La ficha la confirma; la atención la marca como atendida; la inasistencia se registra cuando el paciente no se presenta; la cancelación solo procede hasta el día anterior. Las transiciones no permitidas se rechazan con un mensaje específico y se conserva el historial de estados.")
    b.figure("13_estados_cita.png", 15.8, "Diagrama de estados de la cita")
    b.table(
        ["Estado actual", "Transiciones válidas", "Regla"],
        [
            ("Programada", "Confirmada, cancelada, inasistencia", "La ficha confirma; la cancelación requiere anticipación."),
            ("Confirmada", "Atendida, cancelada, inasistencia", "Solo se atiende si la ficha fue emitida."),
            ("Atendida", "Ninguna", "Estado terminal; conserva historial."),
            ("Cancelada", "Ninguna", "Estado terminal; libera el slot."),
            ("Inasistencia", "Ninguna", "Estado terminal; puede originar reconsulta autorizada."),
        ],
        [3.2, 6.0, 6.8],
        caption="Matriz resumida de transiciones de estado",
    )

    b.h3("5.2.4 Colaboraciones y reglas de análisis")
    b.table(
        ["Regla", "Colaboración responsable"],
        [
            ("RN07 / RF07", "CitaService + CitaModel + índice único parcial; bloqueo del slot en transacción."),
            ("RN13", "DisponibilidadService revalida en el servidor antes de insertar o actualizar."),
            ("RX01", "DisponibilidadService descuenta cupos por canal y franja."),
            ("RX02 / RX03", "EspecialidadModel valida sexo y edad antes de mostrar médicos o registrar la cita."),
            ("RX04", "CitaService compara la fecha de la cita con la fecha actual."),
            ("RX05 / RX06 / RX07", "ReconsultaService crea la cita prioritaria y conserva la cita origen."),
            ("RX08", "FichaService emite la ficha y confirma la cita en la misma transacción."),
            ("RF10", "EstadoCitaService aplica la matriz de transiciones y registra el historial."),
        ],
        [3.4, 12.6],
        caption="Reglas de negocio y colaboraciones de análisis",
    )

    b.h3("5.2.5 Análisis del chatbot de síntomas Dr. Alex (UC25)")
    b.p("UC25 es un caso de uso orientativo ejecutado en el frontend del kiosco. No introduce transacciones, tablas ni servicios de negocio en el servidor: su lógica reside en un árbol de decisión fijo implementado en JavaScript. El análisis se centra en la colaboración entre la interfaz del chat, la animación del personaje, el motor de preguntas y la reutilización del flujo existente de identificación y agendamiento.")
    b.table(
        ["Elemento", "Tipo de análisis", "Responsabilidad"],
        [
            ("KioscoWelcomeView / modal de chat", "Frontera", "Presentar el chatbot en la bienvenida, capturar texto libre y opciones múltiples, y mostrar la recomendación."),
            ("DrAlexBuddy", "Frontera (presentación)", "Animación decorativa del personaje: presentación inicial y estado acoplado al chat; sin lógica de negocio."),
            ("SintomasDecisionTree", "Control (frontend)", "Detectar palabras clave, ubicar la descripción en 10 categorías, formular al menos 3 preguntas de confirmación, gestionar ambigüedad/fallback y recomendar especialidad."),
            ("Validador de entrada del chat", "Control (frontend)", "Bloquear números fuera del paso de cédula; en el resto del flujo solo se aceptan descripciones de síntomas u opciones."),
            ("Identificación de paciente (reuso)", "Colaboración externa", "Al aceptar agendar, solicita cédula y consume `/api/kiosco/identificar`; el resultado alimenta el flujo existente de reserva/ficha con especialidad preseleccionada."),
        ],
        [4.0, 3.2, 8.8],
        caption="Colaboración de análisis del chatbot Dr. Alex (UC25)",
        font_size=8.0,
    )
    b.note("La recomendación de especialidad es orientativa. El chatbot no realiza diagnóstico, no usa IA generativa, no invoca proveedores externos y no persiste síntomas en el servidor. La persistencia y validación transaccional solo ocurren cuando el usuario continúa por el flujo existente de identificación y reserva.", "Límites de UC25")

    b.h2("5.3 Análisis de clase")
    b.p("El análisis de clase identifica las clases que participan en cada realización y las relaciona con los actores y las tablas de la base de datos. Se emplean tres estereotipos: «boundary» para la interacción, «control» para la coordinación y «entity» para la información persistente. Las entidades se anotan además con «table» y con el nombre físico de la tabla PostgreSQL, de modo que el lector pueda verificar que cada atributo relevante del análisis tiene soporte en el esquema de datos.")
    b.p("Los diagramas siguientes muestran primero una vista general de la clasificación y luego el detalle de los casos de uso seleccionados. En cada detalle, los actores se conectan con la frontera; la frontera con el control; y el control con las entidades o tablas que consulta o modifica. Esta cadena permite revisar permisos, responsabilidades y dependencias de datos antes de pasar al diseño.")

    b.h3("5.3.1 Diagramas de análisis de clase por caso de uso")
    b.figure("05_analisis_clases.png", 16.0, "Vista general de las clases de análisis: frontera, control y entidad", max_height_cm=20.0)
    b.figure("58_clase_uc04.png", 15.5, "Análisis de clase de UC04 Iniciar sesión: actores y tabla usuarios")
    b.figure("59_clase_uc05.png", 14.5, "Análisis de clase de UC05/UC09: actores y tablas de reserva y agenda", max_height_cm=20.5)
    b.figure("60_clase_uc15.png", 14.5, "Análisis de clase de UC15: actores y tablas del kiosco", max_height_cm=20.0)
    b.figure("61_clase_uc16.png", 13.5, "Análisis de clase de UC16: actor y tablas de la atención clínica", max_height_cm=20.0)
    b.p("La vista general confirma la separación entre presentación, coordinación y dominio. Los diagramas específicos agregan la dimensión de datos: LoginView y AuthController acceden a usuarios; ReservaWebView, AgendaView, CitaService y DisponibilidadService operan sobre pacientes, medicos, especialidades, horarios, citas y fichas; KioscoView y KioscoController agregan el token y el cupo kiosco; HistoriaView, ConsultaService y PedidoService operan sobre citas, consultas, recetas y ordenes.")
    b.table(
        ["Caso de uso", "Frontera", "Control", "Entidades / tablas", "Actores"],
        [
            ("UC04", "LoginView", "AuthController", "Usuario, UsuarioModel\nusuarios", "Administrador, recepcionista, médico, paciente"),
            ("UC05", "ReservaWebView", "CitaService, DisponibilidadService", "Paciente, Medico, Especialidad, Horario, Cita, Ficha\npacientes, medicos, especialidades, horarios, citas, fichas", "Paciente"),
            ("UC09", "AgendaView", "AuthController, CitaService, DisponibilidadService", "Usuario, Paciente, Medico, Horario, Cita, Ficha\nusuarios, pacientes, medicos, horarios, citas, fichas", "Recepcionista, recepcionista de médico"),
            ("UC15", "KioscoView", "KioscoController, CitaService, FichaService", "Paciente, Especialidad, Horario, Cita, Ficha\npacientes, especialidades, horarios, citas, fichas", "Kiosco, paciente"),
            ("UC16", "HistoriaView", "ConsultaService, PedidoService", "Cita, Consulta, Receta, Orden\ncitas, consultas, recetas, ordenes", "Médico"),
        ],
        [1.9, 2.4, 3.6, 5.6, 2.5],
        caption="Clases de análisis, actores y tablas por caso de uso",
        font_size=7.4,
    )

    b.h3("5.3.2 Clases de análisis, actores y tablas de la base de datos")
    b.p("La tabla siguiente detalla la correspondencia entre las clases de análisis, los actores que las utilizan y las tablas o columnas que soportan su estado. Esta correspondencia es la base del modelo lógico de la sección 6.3 y permite detectar atributos sin persistencia o tablas sin responsable funcional.")
    b.table(
        ["Clase de análisis", "Tipo", "Tabla / columnas clave", "Actores"],
        [
            ("LoginView", "Frontera", "— (captura usuario y clave; no persiste)", "Todos los roles"),
            ("AuthController", "Control", "usuarios (username, password_hash, rol, activo, bloqueado, intentos_fallidos, ultimo_acceso)", "Todos los roles"),
            ("UsuarioModel", "Entidad", "usuarios (id, username, email, password_hash, rol)", "Todos los roles"),
            ("ReservaWebView", "Frontera", "— (especialidad, médico, fecha, slot)", "Paciente"),
            ("CitaService", "Control", "citas (codigo, paciente_id, medico_id, especialidad_id, fecha, hora_inicio, estado, origen), fichas (codigo, cita_id, canal, codigo_acceso)", "Paciente, recepcionista"),
            ("DisponibilidadService", "Control", "horarios (medico_id, dia_semana, hora_inicio, hora_fin, cupos_web, cupos_kiosco), bloqueos (medico_id, fecha, hora_inicio, hora_fin), citas (medico_id, fecha, hora_inicio)", "Paciente, recepcionista"),
            ("AgendaView", "Frontera", "— (búsqueda, programación y acciones de agenda)", "Recepcionista, recepcionista de médico, médico"),
            ("KioscoView", "Frontera", "— (CI, especialidad, médico, slot)", "Kiosco, paciente"),
            ("KioscoController", "Control", "pacientes (ci, nombres, fecha_nacimiento, sexo), citas (origen, estado), fichas (canal, codigo_acceso)", "Kiosco"),
            ("FichaService", "Control", "fichas (codigo, cita_id, canal, codigo_acceso, verificacion_biometrica), citas (estado)", "Kiosco, recepcionista"),
            ("HistoriaView", "Frontera", "— (motivo, anamnesis, signos_vitales, diagnóstico, tratamiento)", "Médico"),
            ("ConsultaService", "Control", "consultas (cita_id, paciente_id, medico_id, motivo, anamnesis, signos_vitales, diagnostico, tratamiento), citas (estado)", "Médico"),
            ("PedidoService", "Control", "recetas (codigo, consulta_id, paciente_id, codigo_acceso), receta_detalle (medicamento, dosis), documentos (tipo, contenido), ordenes (tipo, codigo_acceso), orden_detalle (estudio)", "Médico, recepción"),
            ("ReporteService", "Control", "citas (fecha, estado, origen), pagos (monto, metodo, fecha), consultas (fecha, medico_id)", "Administrador"),
        ],
        [3.3, 1.8, 9.0, 2.9],
        caption="Correspondencia entre clases de análisis, actores y tablas",
        font_size=7.1,
    )
    b.p("La correspondencia confirma que las fronteras no persisten datos, que los controles concentran las transacciones y que las entidades coinciden con tablas concretas. También revela dos decisiones de análisis: la autenticación se apoya en una única tabla usuarios con el rol como enumeración, y los pedidos clínicos se separan en recetas, documentos y ordenes para conservar su ciclo de vida y su código de acceso.")
    b.table(
        ["Tipo", "Clase", "Responsabilidad"],
        [
            ("Frontera", "LoginView", "Capturar credenciales y mostrar errores."),
            ("Frontera", "LandingView", "Presentar especialidades, médicos y reserva."),
            ("Frontera", "KioscoView", "Guiar identificación, selección y ficha."),
            ("Frontera", "AgendaView", "Mostrar la agenda y acciones de atención."),
            ("Frontera", "HistoriaView", "Registrar y consultar la historia clínica."),
            ("Control", "AuthController", "Coordinar autenticación, bloqueo y recuperación."),
            ("Control", "CitaService", "Validar y ejecutar reserva, reprogramación y cancelación."),
            ("Control", "DisponibilidadService", "Calcular slots y cupos por canal."),
            ("Control", "KioscoController", "Orquestar identificación, reserva y ficha."),
            ("Control", "ConsultaService", "Registrar consultas y generar pedidos."),
            ("Entidad", "Paciente", "Datos personales, contacto e historial."),
            ("Entidad", "Medico", "Datos profesionales, especialidades y agenda."),
            ("Entidad", "Especialidad", "Restricciones de sexo y edad."),
            ("Entidad", "Horario", "Franjas, cupos web/kiosco y bloqueos."),
            ("Entidad", "Cita", "Reserva, estado, origen y prioridad."),
            ("Entidad", "Ficha", "Código, acceso y confirmación de la cita."),
            ("Entidad", "Consulta", "Registro clínico de la atención."),
            ("Entidad", "Pedido", "Receta, documento u orden de estudio."),
            ("Entidad", "Pago", "Monto, método y responsable del registro."),
        ],
        [2.2, 4.2, 9.6],
        caption="Clases de análisis y responsabilidades",
        font_size=8.2,
    )

    b.h3("5.3.3 Modelo de dominio")
    b.p("El modelo de dominio se divide en dos vistas complementarias. La vista núcleo agrupa usuarios, pacientes, médicos, especialidades, horarios y citas. La vista clínica agrupa consultas, recetas, documentos, órdenes, resultados y pagos. La separación facilita la comprensión y permite asignar responsabilidades sin mezclar la operación administrativa con la información clínica.")
    b.figure("03_clases_dominio_core.png", 16.0, "Modelo de clases del dominio núcleo")
    b.figure("04_clases_dominio_clinico.png", 16.0, "Modelo de clases del dominio clínico")
    b.table(
        ["Entidad", "Atributos clave", "Relaciones"],
        [
            ("Paciente", "codigo, ci, nombres, fecha_nacimiento, sexo, telefono, estado", "1–N Citas; 1–N Consultas; 1–N Pagos; 0..1 Usuario"),
            ("Medico", "codigo, registro_profesional, nombres, duracion_cita_min, estado", "N–M Especialidades; 1–N Horarios; 1–N Citas"),
            ("Especialidad", "nombre, sexo_permitido, edad_min, edad_max, destacada", "N–M Médicos; 1–N Citas"),
            ("Horario", "dia_semana, hora_inicio, hora_fin, cupos_web, cupos_kiosco", "N–1 Médico"),
            ("Cita", "codigo, fecha, hora_inicio, hora_fin, estado, origen, es_reconsulta", "N–1 Paciente/Médico/Especialidad; 1–1 Ficha"),
            ("Ficha", "codigo, canal, codigo_acceso", "1–1 Cita"),
            ("Consulta", "motivo, anamnesis, signos_vitales, diagnostico, tratamiento", "1–1 Cita; 1–N Pedidos"),
            ("Pedido", "codigo, tipo, codigo_acceso, enviado_whatsapp", "N–1 Consulta/Paciente/Médico"),
            ("Pago", "monto, metodo, referencia_paciente, fecha", "N–1 Paciente/Médico/Usuario"),
        ],
        [2.4, 8.4, 5.2],
        caption="Entidades principales del dominio",
        font_size=8.1,
    )

    b.h2("5.4 Análisis de paquete")
    b.p("La figura integra los paquetes del análisis (PK-01 a PK-04), los casos de uso que contiene cada uno y las relaciones que los conectan. Las flechas discontinuas grises representan «include» y «extend» entre casos de uso; las flechas azules representan dependencias «use» entre paquetes. PK-04 actúa como paquete transversal y no contiene casos de uso de negocio.")
    b.figure("62_paquetes_integral.png", 16.0, "Vista integral de paquetes del análisis: casos de uso, relaciones «include»/«extend» y dependencias «use»", max_height_cm=21.5)
    b.p("Las dependencias permitidas forman un grafo acíclico: PK-01 y PK-03 usan servicios de PK-02 y PK-04; PK-02 usa PK-04; PK-04 no depende de los paquetes funcionales. Las relaciones cruzadas entre paquetes (UC09 «include» UC04 y UC15 «include» UC05) se resuelven en el paquete que contiene el caso reutilizado, preservando la cohesión y la trazabilidad hacia el diseño (sección 6.1.2).")


def chapter6():
    b.h1("6. FT. DISEÑO")
    b.p("El diseño transforma el análisis en una solución construible. Se especifica la arquitectura lógica y física, se diseñan los casos de uso críticos mediante diagramas de secuencia, se define el modelo de datos lógico y físico y se documentan los patrones, la seguridad, la interfaz y la API. La estructura sigue el esquema solicitado para la disciplina de Diseño: 6.1 arquitectura física y lógica, 6.2 diseño de casos de uso y 6.3 diseño de datos.")

    b.h2("6.1 Diseño de arquitectura (física y lógica)")
    b.h3("6.1.1 Arquitectura física")
    b.p("La arquitectura física describe la infraestructura sobre la que se ejecuta la arquitectura lógica y complementa la vista de despliegue. Se organiza en cinco zonas: la zona pública de Internet, el perímetro de seguridad, la zona de aplicación del hosting, la zona de datos gestionada y la red local de la clínica, además de los servicios externos contratados.")
    b.figure("17_arquitectura_fisica.png", 16.5, "Arquitectura física del sistema por zonas y nodos", max_height_cm=21.0)
    b.p("El navegador del paciente y el personal remoto acceden por HTTPS al firewall y WAF del hosting, que deriva el tráfico al servidor Apache con PHP-FPM. La aplicación no expone la base de datos a Internet: PostgreSQL se aloja en una instancia gestionada con TLS obligatorio y respaldo diario. La red local de la clínica aloja el PC de recepción y el iPad del kiosco, que consumen la misma aplicación web; la impresora de fichas es opcional. El correo se entrega por el relay SMTP de Brevo y los pedidos se comparten mediante el enlace de WhatsApp.")
    b.figure("07_despliegue.png", 16.0, "Diagrama de despliegue del sistema")
    b.table(
        ["Zona", "Nodo físico", "Software y configuración", "Protocolo / puerto"],
        [
            ("Pública (Internet)", "PC o móvil del paciente", "Navegador moderno con TLS 1.2 o superior.", "HTTPS 443"),
            ("Pública (Internet)", "Equipo del personal remoto (opcional)", "Navegador moderno.", "HTTPS 443"),
            ("Perímetro", "Firewall y WAF del hosting", "Reglas de entrada, filtrado y terminación TLS.", "HTTPS 443"),
            ("Aplicación", "Servidor del hosting cPanel\n2 vCPU · 2 GB RAM · 20 GB SSD", "Apache 2.4, PHP 8.1 FPM, pdo_pgsql y openssl.", "HTTPS 443"),
            ("Datos", "Instancia PostgreSQL 16 gestionada\nNeon o Railway", "PostgreSQL 16, RLS, triggers y snapshot diario.", "pdo_pgsql 5432 / TLS"),
            ("LAN clínica", "PC de recepción", "Windows 11 con Chrome o Edge.", "HTTPS 443"),
            ("LAN clínica", "iPad kiosco", "iPadOS con Safari en modo kiosco.", "HTTPS 443"),
            ("LAN clínica", "Impresora de fichas (opcional)", "Impresión desde la estación de recepción.", "USB / red local"),
            ("Servicios externos", "Brevo SMTP", "Relay de correo transaccional.", "SMTP 587 / TLS"),
            ("Servicios externos", "WhatsApp (wa.me)", "Enlace profundo con código de acceso.", "HTTPS 443"),
            ("Servicios externos", "GitHub", "Repositorio del código y la documentación.", "HTTPS 443 / SSH 22"),
        ],
        [2.8, 5.2, 5.8, 3.2],
        caption="Nodos de la arquitectura física",
        font_size=7.9,
    )
    b.table(
        ["Nodo", "Tecnología", "Función"],
        [
            ("Cliente web", "Navegador moderno", "Landing, portal y paneles."),
            ("Kiosco", "iPad / tablet", "Flujo táctil de identificación y ficha."),
            ("Servidor web", "cPanel + PHP 8.1+", "Aplicación MVC y endpoints."),
            ("Base de datos", "PostgreSQL 16 en Neon/Railway", "Persistencia, RLS y transacciones."),
            ("Correo", "Brevo SMTP + PHPMailer", "Recuperación y notificaciones."),
            ("WhatsApp", "Deep link", "Acceso a pedidos con código."),
        ],
        [3.0, 6.0, 7.0],
        caption="Nodos de despliegue",
    )
    b.note("El dimensionamiento corresponde al mínimo previsto para la operación de la clínica. El hosting y la instancia de base de datos pueden escalar de forma vertical sin modificar la arquitectura lógica, y el respaldo diario permite restaurar la información ante una falla.", "Capacidad y continuidad")

    b.h3("6.1.2 Arquitectura lógica")
    b.p("La arquitectura lógica se documenta con un diagrama de paquetes organizado en cuatro capas: funcional (módulos del sistema), lógica, intermedia (tecnologías y frameworks) y software del sistema. Las dependencias descienden de los módulos hacia la lógica, de la lógica hacia las tecnologías y de estas hacia la plataforma de ejecución. Esta organización separa responsabilidades, evita dependencias circulares y facilita las pruebas y el reemplazo de tecnologías.")
    b.figure("30_capas_arquitectura_logica.png", 16.5, "Diagrama de paquetes por capas de la arquitectura lógica", max_height_cm=20.0)
    b.p("El diagrama muestra los paquetes de cada capa y las relaciones permitidas entre ellos; la tabla siguiente resume su contenido y el sentido de las dependencias.")
    b.page_break()
    b.table(
        ["Capa", "Paquetes", "Relación"],
        [
            ("Funcional (módulos del sistema)", "Usuarios y seguridad; pacientes; gestión médica y agenda; kiosco y fichas; atención clínica; panel y reportes.", "Depende de la capa lógica."),
            ("Lógica", "Autenticación y roles; servicios de negocio; controladores y API; modelos de datos.", "Implementa reglas y coordinación; depende de la capa intermedia."),
            ("Intermedia (tecnologías / frameworks)", "HTML5, CSS3 y JavaScript; Bootstrap/Gentelella; API REST (PHP); Apache/PHP-FPM; Git/GitHub.", "Materializa la solución; depende del software del sistema."),
            ("Software del sistema", "PHP 8.1+; PostgreSQL 16; navegador/PWA.", "Plataforma de ejecución y almacenamiento."),
        ],
        [3.6, 8.4, 4.0],
        caption="Capas y paquetes de la arquitectura lógica",
        font_size=8.0,
    )
    b.p("La vista de componentes complementa el diagrama de paquetes y desglosa los componentes por capa de ejecución: presentación, aplicación, dominio, infraestructura y datos. La capa de presentación incluye las vistas Gentelella de los paneles, la landing y el kiosco; en la bienvenida del kiosco se incorpora el chatbot \"Dr. Alex\" como componente frontend orientativo. La capa de aplicación contiene el router, los controladores y el middleware. El dominio agrupa servicios y reglas de negocio. La infraestructura comprende modelos PDO, correo, generación de QR y acceso a PostgreSQL. La tabla siguiente resume estos componentes y la figura los relaciona entre sí.")
    b.table(
        ["Capa", "Componentes", "Responsabilidad"],
        [
            ("Presentación", "Layouts, landing, kiosco, chatbot Dr. Alex (JS), vistas de panel", "Renderizar HTML, formularios, orientación por síntomas y respuestas visuales."),
            ("Aplicación", "Router, middleware, controladores", "Recibir peticiones, validar sesión/rol y coordinar casos de uso."),
            ("Dominio", "Services: Auth, Disponibilidad, Citas, Estados, Reconsultas, Fichas, Consultas, Reportes", "Aplicar reglas, transacciones y validaciones de negocio."),
            ("Infraestructura", "Modelos PDO, MailService, QRService, configuración", "Persistencia, correo, códigos QR y configuración."),
            ("Datos", "PostgreSQL 16, RLS, triggers", "Almacenamiento, integridad y aislamiento por rol."),
        ],
        [2.8, 8.0, 5.2],
        caption="Componentes por capa de ejecución",
        font_size=8.0,
    )
    b.figure("06_arquitectura_logica.png", 15.0, "Arquitectura lógica por capas y componentes")

    b.h3("6.1.3 Patrones y decisiones de diseño")
    b.bullets([
        ("MVC: ", "separa presentación, coordinación y datos; facilita el mantenimiento y las pruebas."),
        ("Front Controller: ", "un único punto de entrada resuelve rutas y middleware."),
        ("Service Layer: ", "concentra reglas de negocio y transacciones fuera de los controladores."),
        ("Repository/Model: ", "encapsula SQL parametrizado y operaciones de persistencia."),
        ("Dependency Injection simple: ", "los controladores reciben servicios y modelos desde el contenedor."),
        ("Template Method: ", "los layouts y vistas reutilizan estructura y bloques."),
        ("Token firmado: ", "el kiosco utiliza HMAC con expiración corta."),
    ])

    b.h3("6.1.4 Seguridad y control de acceso")
    b.p("La seguridad se implementa en varias capas. En la aplicación, el middleware valida sesión y rol, y los formularios incluyen CSRF. En la base de datos, RLS filtra filas según el rol y el usuario de sesión. El kiosco usa un rol de base de datos restringido y un token HMAC. La página pública de pedidos valida código y código de acceso sin exponer datos sensibles.")
    b.table(
        ["Control", "Implementación"],
        [
            ("Autenticación", "password_hash/password_verify, sesión regenerada y cookies seguras."),
            ("Autorización", "RoleMiddleware + políticas RLS por tabla."),
            ("CSRF", "Token por sesión en formularios y fetch."),
            ("Kiosco", "Token HMAC de 10 minutos y usuario de BD restringido."),
            ("Auditoría", "Trigger SECURITY DEFINER con IP y JSONB antes/después."),
            ("Transporte", "HTTPS obligatorio y sslmode=require hacia la BD."),
            ("Entrada", "Validación cliente/servidor y PDO preparado; el chat Dr. Alex bloquea números fuera del paso de cédula."),
            ("Salida", "htmlspecialchars() y respuestas JSON controladas."),
        ],
        [3.6, 12.4],
        caption="Controles de seguridad del diseño",
    )

    b.h3("6.1.5 Diseño de la API")
    b.table(
        ["Grupo", "Endpoints representativos", "Propósito"],
        [
            ("Autenticación", "POST /auth/login, /auth/recuperar, /auth/reset", "Sesión y recuperación."),
            ("Catálogos", "GET/POST /api/pacientes, /api/medicos, /api/especialidades", "CRUD de catálogos."),
            ("Horarios", "GET /api/disponibilidad, POST /api/medicos/{id}/horarios", "Cupos y slots."),
            ("Citas", "POST /api/citas, PUT /api/citas/{id}/reprogramar, PATCH /api/citas/{id}/cancelar", "Ciclo de la cita."),
            ("Estados", "PATCH /api/citas/{id}/atender, /inasistencia, /reconsulta", "Asistencia y reconsultas."),
            ("Kiosco", "POST /api/kiosco/identificar, /api/kiosco/reservar, /api/kiosco/ficha", "Identificación, reserva y ficha; `/api/kiosco/identificar` es reutilizado por el chatbot Dr. Alex al aceptar agendar."),
            ("Clínica", "POST /api/consultas, /recetas, /ordenes", "Historia y pedidos."),
            ("Reportes", "GET /api/reportes/agenda, /demanda, /pagos", "Indicadores."),
        ],
        [3.0, 8.0, 5.0],
        caption="Grupos de endpoints de la API",
        font_size=8.1,
    )
    b.p("UC25 (chatbot Dr. Alex) no expone endpoints nuevos: ejecuta un árbol de decisión en el frontend y, solo cuando el paciente acepta agendar, reutiliza la API existente de identificación y el flujo de reserva/ficha del kiosco.")

    b.h3("6.1.6 Decisiones de diseño relevantes")
    b.table(
        ["Decisión", "Alternativa considerada", "Justificación"],
        [
            ("MVC manual en PHP", "Framework completo", "Restricción académica y control didáctico."),
            ("PostgreSQL con RLS", "Filtrado solo en PHP", "Defensa en profundidad y aislamiento por rol."),
            ("Cupos por franja", "Cupos globales", "Garantiza el 50/50 web/kiosco."),
            ("Bloqueo pesimista", "Solo validación previa", "Evita carreras en reservas concurrentes."),
            ("Ficha digital con QR", "Ficha solo impresa", "Reduce papel y permite validación rápida."),
            ("WhatsApp por deep link", "API Business", "Menor costo y complejidad para el alcance."),
            ("Chatbot de síntomas por árbol de decisión", "IA generativa o servicio externo de triage", "Funciona sin conexión a proveedores, sin costo por uso y con comportamiento predecible; la recomendación es orientativa y no sustituye el criterio clínico."),
        ],
        [4.2, 4.8, 7.0],
        caption="Decisiones de diseño y justificación",
        font_size=8.2,
    )
    b.h2("6.2 Diseño de casos de uso (diagramas de secuencia)")
    b.p("El diseño de casos de uso traduce las realizaciones del análisis en interacciones entre los componentes de diseño: router frontal, middleware, controlador, servicio y modelo. Se detallan los flujos críticos de los dos ciclos: UC04 Iniciar sesión, UC08 Gestionar pacientes, UC21 Configurar horarios y cupos, UC05 Reservar cita web, UC09 Programar cita, UC10 Reprogramar cita, UC15 Emitir ficha en kiosco y UC16 Registrar consulta. Cada diagrama conserva la traza con la realización de análisis correspondiente de la sección 5.2.")
    b.table(
        ["Caso de uso", "Actor", "Componentes de diseño", "Diagrama"],
        [
            ("UC04 Iniciar sesión", "Todos", "AuthMiddleware, AuthController, AuthService, UsuarioModel", "Sección 6.2"),
            ("UC08 Gestionar pacientes", "Recepcionista", "PacienteController, PacienteService, PacienteModel", "Sección 6.2"),
            ("UC21 Configurar horarios y cupos", "Administrador", "HorarioController, DisponibilidadService, HorarioModel", "Sección 6.2"),
            ("UC05 Reservar cita web", "Paciente", "PublicApiController, CitaService, DisponibilidadService, CitaModel, FichaService", "Sección 6.2"),
            ("UC09 Programar cita", "Recepcionista", "CitaController, CitaService, DisponibilidadService, CitaModel", "Sección 6.2"),
            ("UC10 Reprogramar cita", "Recepcionista", "CitaController, CitaService, DisponibilidadService, CitaModel", "Sección 6.2"),
            ("UC15 Emitir ficha en kiosco", "Kiosco", "KioskMiddleware, KioscoController, KioscoTokenService, CitaService, FichaService", "Sección 6.2"),
            ("UC16 Registrar consulta", "Médico", "ConsultaController, ConsultaService, ConsultaModel, PedidoService", "Sección 6.2"),
            ("UC25 Consultar síntomas (Dr. Alex)", "Paciente presencial", "ChatSintomas (kiosco-sintomas.js), DrAlexBuddy (kiosco-chat-buddy.js), modal en kiosco/index.php; reutiliza /api/kiosco/identificar", "Frontend (sin secuencia servidor nueva)"),
        ],
        [4.6, 2.6, 7.4, 2.4],
        caption="Casos de uso críticos y su realización de diseño",
        font_size=7.8,
    )
    b.figure("18_sec_login_diseno.png", 16.2, "Secuencia de diseño de inicio de sesión y control de intentos")
    b.figure("19_sec_pacientes_diseno.png", 16.2, "Secuencia de diseño de registro y validación de pacientes")
    b.figure("20_sec_horarios_diseno.png", 16.2, "Secuencia de diseño de configuración de horarios y cupos")
    b.figure("23_sec_reserva_diseno.png", 16.2, "Secuencia de diseño de reserva de cita web")
    b.figure("24_sec_programar_diseno.png", 16.2, "Secuencia de diseño de programación de cita en recepción")
    b.figure("25_sec_reprogramar_diseno.png", 16.2, "Secuencia de diseño de reprogramación de cita")
    b.figure("26_sec_kiosco_diseno.png", 16.2, "Secuencia de diseño de emisión de ficha en kiosco")
    b.figure("27_sec_consulta_diseno.png", 16.2, "Secuencia de diseño de registro de consulta y pedidos")
    b.note("Las secuencias de diseño muestran el orden de los mensajes y las transacciones. La validación de disponibilidad, la prevención de superposiciones y la emisión de la ficha se ejecutan en el servidor; la interfaz solo presenta los resultados y los mensajes de error. UC25 se resuelve en el cliente con un árbol de decisión fijo y solo delega al servidor la identificación del paciente cuando el usuario decide agendar.", "Trazabilidad análisis–diseño")

    b.h3("6.2.1 Diseño de interfaz y navegación")
    b.p("La interfaz se organiza por roles. La landing presenta la clínica, especialidades destacadas, médicos y el acceso a la reserva. El portal del paciente permite ver y cancelar citas. El panel administrativo usa Gentelella y el panel médico prioriza la agenda del día. El kiosco emplea botones grandes, teclado numérico y un flujo de cuatro pasos con reinicio automático por inactividad.")
    b.figure("16_mapa_navegacion.png", 15.8, "Mapa de navegación por rol")
    b.table(
        ["Vista", "Rol", "Elementos principales"],
        [
            ("Landing", "Público", "Hero, especialidades, médicos, reserva, contacto."),
            ("Login / activar", "Todos", "Credenciales, recuperación y activación."),
            ("Dashboard", "Admin / recepción / médico", "Indicadores, agenda y accesos rápidos."),
            ("Pacientes", "Recepción / admin", "Búsqueda, ficha, historial y estado."),
            ("Agenda", "Recepción / médico", "Calendario, slots, estados y acciones."),
            ("Kiosco", "Paciente presencial", "Bienvenida con chatbot Dr. Alex, identificación, especialidad, médico, slot y ficha."),
            ("Consulta", "Médico", "Signos vitales, diagnóstico, tratamiento y pedidos."),
            ("Reportes", "Admin", "Demanda, atención, cancelaciones y productividad."),
        ],
        [3.2, 4.2, 8.6],
        caption="Vistas principales de la interfaz",
        font_size=8.2,
    )

    b.h2("6.3 Diseño de datos (lógico y físico)")
    b.p("El diseño de datos comprende dos niveles. El nivel lógico se expresa con los modelos entidad-relación y las relaciones normalizadas del dominio. El nivel físico precisa los tipos de PostgreSQL, las claves, los índices, los enums, los triggers y las políticas RLS que materializan el modelo en la base de datos `clinica_vida_salud`.")
    b.p("El modelo relacional se normaliza para evitar duplicidad y se complementa con enums, índices y restricciones. Las entidades con historial no se eliminan físicamente. Los códigos visibles se generan en el servicio mediante secuencias por prefijo. La tabla `auditoria` se alimenta por trigger y no admite escritura directa desde la aplicación. El chatbot Dr. Alex (UC25) no incorpora tablas, columnas ni migraciones: su árbol de categorías y preguntas reside en el frontend y no persiste síntomas en la base de datos.")
    b.p("Las relaciones principales del modelo, derivadas del diagrama de clases y de la especificación del proyecto, son las siguientes. Cada relación se implementa con claves foráneas y restricciones de integridad, y se conserva la trazabilidad con las clases del dominio de la sección 5.3.3.")
    b.table(
        ["Relación", "Cardinalidad", "Implementación"],
        [
            ("Paciente – Cita", "1:N", "`citas.paciente_id` con FK y `ON DELETE RESTRICT`."),
            ("Médico – Cita", "1:N", "`citas.medico_id` con FK y `ON DELETE RESTRICT`."),
            ("Médico – Especialidad", "N:M", "Tabla intermedia `medico_especialidad` con PK compuesta."),
            ("Especialidad – Médico", "1:N desde la especialidad", "La misma tabla intermedia permite consultar médicos por especialidad."),
            ("Médico – Horario", "1:N", "`horarios.medico_id` con FK y restricción de franja válida."),
            ("Usuario – Rol", "N:1", "`usuarios.rol` con enum y políticas RLS por rol."),
            ("Cita – Ficha", "1:1", "`fichas.cita_id` único y FK."),
            ("Consulta – Cita", "1:1", "`consultas.cita_id` único y FK."),
            ("Consulta – Pedido", "1:N", "`pedidos.consulta_id` con FK."),
            ("Paciente / Médico – Pago", "1:N", "`pagos.paciente_id` y `pagos.medico_id` con FK."),
        ],
        [4.2, 3.2, 8.6],
        caption="Relaciones principales del modelo de datos",
        font_size=8.1,
    )
    b.figure("14_er_nucleo.png", 16.0, "Modelo entidad-relación del núcleo")
    b.figure("15_er_clinico.png", 16.0, "Modelo entidad-relación de la historia clínica y pagos")
    b.table(
        ["Tabla", "Propósito", "Restricciones clave"],
        [
            ("usuarios", "Credenciales y roles.", "username/email únicos; rol enum."),
            ("pacientes", "Datos del paciente.", "ci única; usuario_id nullable."),
            ("medicos", "Datos profesionales.", "codigo y registro_profesional únicos."),
            ("especialidades", "Catálogo médico.", "nombre normalizado único; sexo/edad."),
            ("horarios", "Franjas y cupos.", "hora_fin > hora_inicio; cupos ≥ 0."),
            ("citas", "Reserva y estado.", "índice único parcial anti-superposición."),
            ("fichas", "Ticket de atención.", "cita_id único; código único."),
            ("consultas", "Registro clínico.", "cita_id, paciente_id y medico_id válidos."),
            ("pagos", "Registro financiero.", "monto > 0; método enum."),
            ("auditoria", "Trazabilidad de catálogos.", "trigger con IP y datos antes/después."),
        ],
        [2.8, 7.2, 6.0],
        caption="Tablas principales y restricciones",
        font_size=8.2,
    )
    b.table(
        ["Elemento físico", "Implementación en PostgreSQL", "Propósito"],
        [
            ("Claves primarias", "`BIGSERIAL` o `UUID` según la tabla.", "Identidad única de cada registro."),
            ("Claves foráneas", "`REFERENCES` con `ON UPDATE CASCADE` y `ON DELETE RESTRICT`.", "Integridad referencial e historial."),
            ("Tipos enumerados", "`CREATE TYPE estado_cita AS ENUM (...)`.", "Dominios controlados y legibles."),
            ("Índices", "`ux_cita_no_superpone` parcial; índices por fecha, médico y estado.", "Rendimiento y anti-superposición."),
            ("Restricciones", "`CHECK (hora_fin > hora_inicio)` y `CHECK (monto > 0)`.", "Validación directa en la base de datos."),
            ("Triggers", "`fn_auditar()` con `SECURITY DEFINER`.", "Auditoría de catálogos con IP y JSONB."),
            ("RLS", "`FORCE ROW LEVEL SECURITY` y políticas por rol.", "Aislamiento de filas por usuario."),
            ("JSONB", "`signos_vitales JSONB` en la tabla de consultas.", "Datos clínicos flexibles y consultables."),
        ],
        [3.2, 7.8, 5.0],
        caption="Elementos del modelo físico de datos",
        font_size=8.0,
    )

