# -*- coding: utf-8 -*-
from puds_lib import b


def chapter5():
    b.h1("5. ANÁLISIS DEL SISTEMA")
    b.h2("5.1 Propósito del análisis")
    b.p("El análisis transforma los requisitos en una comprensión estructurada del dominio y de las responsabilidades del sistema. Se identifican clases de frontera, control y entidad; se modelan las relaciones del dominio; se realizan los casos de uso mediante diagramas de secuencia; y se especifica el ciclo de vida de la cita. El resultado sirve de puente entre la captura de requisitos y el diseño arquitectónico.")
    b.p("El análisis se concentra en la trazabilidad: cada caso de uso crítico se realiza con clases concretas y cada regla de negocio se refleja en una validación o transición. Los diagramas de análisis no describen todavía decisiones de implementación, sino responsabilidades y colaboraciones. La disciplina se organiza en cuatro actividades: análisis de la arquitectura, análisis de los casos de uso, análisis de las clases y análisis de los paquetes.")

    b.h2("5.2 Análisis de arquitectura")
    b.p("El análisis de arquitectura identifica los paquetes del análisis, las clases de entidad evidentes y los casos de uso significativos para la arquitectura. La descomposición se apoya en los paquetes de casos de uso PK-01, PK-02 y PK-03, y agrega un paquete transversal de servicios compartidos. El criterio es agrupar por proceso de negocio y por actor, buscando bajo acoplamiento y alta cohesión.")
    b.figure("21_paquetes_analisis.png", 11.5, "Paquetes del análisis y dependencias", max_height_cm=20.0)
    b.table(
        ["Paquete", "Casos de uso", "Clases principales", "Dependencias"],
        [
            ("PK-01 Acceso público y reserva web", "UC01–UC07", "LandingView, LoginView, AuthController, DisponibilidadService, CitaService, Paciente, Medico, Especialidad, Cita", "PK-02 (disponibilidad y cita), PK-04 (seguridad y notificaciones)"),
            ("PK-02 Citas y agenda", "UC08–UC14", "AgendaView, CitaService, DisponibilidadService, EstadoCitaService, Cita, Horario, Ficha", "PK-04 (persistencia, correo y auditoría)"),
            ("PK-03 Atención y administración", "UC15–UC24", "KioscoView, KioscoController, ConsultaService, FichaService, ReporteService, Consulta, Pedido, Pago", "PK-01 (reserva), PK-02 (agenda), PK-04 (servicios)"),
            ("PK-04 Servicios compartidos", "Transversal", "AuthService, MailService, AuditoriaModel, DB, configuración", "Ninguna dependencia hacia los paquetes de aplicación"),
        ],
        [4.2, 2.6, 5.8, 4.0],
        caption="Paquetes del análisis, clases y dependencias",
        font_size=7.9,
    )
    b.p("Los casos de uso significativos para la arquitectura son UC04 Iniciar sesión, UC05 Reservar cita web, UC09 Programar cita, UC15 Emitir ficha en kiosco, UC16 Registrar consulta y UC21 Configurar horarios y cupos. Estos flujos concentran los riesgos de seguridad, concurrencia, transacción y persistencia, y por ello se analizan y diseñan con mayor detalle.")
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

    b.h2("5.3 Analizar casos de uso")
    b.p("Las realizaciones de casos de uso describen la interacción entre actores, fronteras, controles y entidades. Se documentan los flujos de login, reserva web, kiosco, cancelación y consulta médica, por ser los de mayor criticidad para la operación y la seguridad. Cada realización mantiene una traza con el caso de uso del modelo de requisitos.")
    b.table(
        ["Caso de uso", "Frontera", "Control", "Entidades", "Diagrama"],
        [
            ("UC04 Iniciar sesión", "LoginView", "AuthController / AuthService", "Usuario, Rol", "Sec. 5.3"),
            ("UC05 Reservar cita web", "LandingView", "CitaService / DisponibilidadService", "Paciente, Medico, Cita, Ficha", "Sec. 5.3"),
            ("UC15 Emitir ficha en kiosco", "KioscoView", "KioscoController / FichaService", "Paciente, Cita, Ficha", "Sec. 5.3"),
            ("UC06 / UC11 Cancelar cita", "AgendaView / MisCitasView", "CitaService / EstadoCitaService", "Cita, Cupo, HistorialEstado", "Sec. 5.3"),
            ("UC16 Registrar consulta", "HistoriaView", "ConsultaService / PedidoService", "Cita, Consulta, Pedido, Pago", "Sec. 5.3"),
        ],
        [4.0, 3.2, 4.4, 3.8, 1.6],
        caption="Realizaciones de casos de uso y clases participantes",
        font_size=8.0,
        align_center_cols={4},
    )
    b.figure("08_sec_login.png", 15.8, "Secuencia de inicio de sesión y control de intentos")
    b.figure("09_sec_reserva.png", 16.0, "Secuencia de reserva de cita con validación de disponibilidad")
    b.figure("10_sec_kiosco.png", 16.0, "Secuencia de emisión de ficha en el kiosco")
    b.figure("11_sec_cancelar.png", 15.8, "Secuencia de cancelación de cita")
    b.figure("12_sec_consulta.png", 16.0, "Secuencia de registro de consulta y generación de pedidos")

    b.h2("5.4 Clases de análisis")
    b.p("Las clases de frontera representan la interacción con el usuario o con sistemas externos; las clases de control coordinan el flujo de un caso de uso; las clases de entidad modelan la información persistente. Esta clasificación permite separar la interfaz, la lógica de coordinación y el dominio.")
    b.figure("05_analisis_clases.png", 16.0, "Diagrama de clases de análisis")
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

    b.h2("5.5 Modelo de dominio")
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
            ("Ficha", "codigo, canal, codigo_acceso, verificacion_biometrica", "1–1 Cita"),
            ("Consulta", "motivo, anamnesis, signos_vitales, diagnostico, tratamiento", "1–1 Cita; 1–N Pedidos"),
            ("Pedido", "codigo, tipo, codigo_acceso, enviado_whatsapp", "N–1 Consulta/Paciente/Médico"),
            ("Pago", "monto, metodo, referencia_paciente, fecha", "N–1 Paciente/Médico/Usuario"),
        ],
        [2.4, 8.4, 5.2],
        caption="Entidades principales del dominio",
        font_size=8.1,
    )

    b.h2("5.6 Analizar paquetes")
    b.p("El análisis de paquetes verifica que cada agrupación sea lo más independiente posible, que cumpla su objetivo de realizar las clases del dominio y los casos de uso asignados y que sus dependencias estén explícitas. Las dependencias se orientan en el mismo sentido que la navegabilidad y se concentran en el paquete de servicios compartidos.")
    b.table(
        ["Paquete", "Responsabilidad", "Cohesión y acoplamiento", "Riesgo y mitigación"],
        [
            ("PK-01 Acceso público y reserva web", "Autenticación del paciente, búsqueda y reserva.", "Alta cohesión funcional; depende de PK-02 y PK-04.", "Exposición pública: validación en servidor, rate limiting y captcha opcional."),
            ("PK-02 Citas y agenda", "Programación, reprogramación, cancelación y estados.", "Alta cohesión; dependencia mínima de PK-04.", "Concurrencia: transacción y bloqueo pesimista."),
            ("PK-03 Atención y administración", "Ficha, consulta, pedidos, pagos, reportes y auditoría.", "Cohesión por proceso clínico; depende de PK-01, PK-02 y PK-04.", "Datos sensibles: RLS, roles y separación de vistas."),
            ("PK-04 Servicios compartidos", "Seguridad, correo, auditoría y persistencia.", "Bajo acoplamiento; no depende de los paquetes de aplicación.", "Punto único de fallo: pruebas de integración y respaldo."),
        ],
        [4.0, 4.2, 4.2, 3.6],
        caption="Análisis de paquetes: cohesión, acoplamiento y riesgos",
        font_size=7.9,
    )
    b.p("La traza entre paquetes de análisis y subsistemas de diseño es directa: PK-01 y PK-03 se materializan en los módulos de presentación y aplicación; PK-02 en los servicios de citas y disponibilidad; y PK-04 en la infraestructura de seguridad, correo, auditoría y acceso a datos. Esta correspondencia se detalla en la sección 6.1.2.")

    b.h2("5.7 Ciclo de vida de la cita")
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

    b.h2("5.8 Colaboraciones y reglas de análisis")
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

    b.h2("5.9 Riesgos identificados en el análisis")
    b.table(
        ["Riesgo", "Impacto", "Mitigación"],
        [
            ("Condiciones de carrera al reservar", "Alto", "Transacción con bloqueo y índice único parcial."),
            ("Cálculo incorrecto de cupos", "Alto", "Servicio único de disponibilidad y pruebas por canal."),
            ("Datos clínicos expuestos", "Alto", "RLS, roles y separación de la vista pública de pedidos."),
            ("Estados inconsistentes", "Medio", "Máquina de estados y historial de transiciones."),
            ("Duplicidad de pacientes", "Medio", "CI única y búsqueda previa."),
            ("Kiosco sin conectividad", "Medio", "Mensaje de reintento y operación alterna en recepción."),
        ],
        [4.8, 3.0, 8.2],
        caption="Riesgos del análisis y mitigaciones",
    )


def chapter6():
    b.h1("6. DISEÑO DEL SISTEMA")
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
    b.p("La vista de componentes complementa el diagrama de paquetes y desglosa los componentes por capa de ejecución: presentación, aplicación, dominio, infraestructura y datos. La capa de presentación incluye las vistas Gentelella de los paneles, la landing y el kiosco. La capa de aplicación contiene el router, los controladores y el middleware. El dominio agrupa servicios y reglas de negocio. La infraestructura comprende modelos PDO, correo, generación de QR y acceso a PostgreSQL. La tabla siguiente resume estos componentes y la figura los relaciona entre sí.")
    b.table(
        ["Capa", "Componentes", "Responsabilidad"],
        [
            ("Presentación", "Layouts, landing, kiosco, vistas de panel", "Renderizar HTML, formularios y respuestas visuales."),
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

    b.h2("6.2 Diseño de casos de uso (diagramas de secuencia)")
    b.p("El diseño de casos de uso traduce las realizaciones del análisis en interacciones entre los componentes de diseño: router frontal, middleware, controlador, servicio y modelo. Se detallan los flujos críticos de los dos ciclos: UC04 Iniciar sesión, UC08 Gestionar pacientes, UC21 Configurar horarios y cupos, UC05 Reservar cita web, UC09 Programar cita, UC10 Reprogramar cita, UC15 Emitir ficha en kiosco y UC16 Registrar consulta. Cada diagrama conserva la traza con la realización de análisis correspondiente de la sección 5.3.")
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
        ],
        [4.6, 2.6, 7.4, 2.4],
        caption="Casos de uso críticos y su realización de diseño",
        font_size=8.0,
    )
    b.figure("18_sec_login_diseno.png", 16.2, "Secuencia de diseño de inicio de sesión y control de intentos")
    b.figure("19_sec_pacientes_diseno.png", 16.2, "Secuencia de diseño de registro y validación de pacientes")
    b.figure("20_sec_horarios_diseno.png", 16.2, "Secuencia de diseño de configuración de horarios y cupos")
    b.figure("23_sec_reserva_diseno.png", 16.2, "Secuencia de diseño de reserva de cita web")
    b.figure("24_sec_programar_diseno.png", 16.2, "Secuencia de diseño de programación de cita en recepción")
    b.figure("25_sec_reprogramar_diseno.png", 16.2, "Secuencia de diseño de reprogramación de cita")
    b.figure("26_sec_kiosco_diseno.png", 16.2, "Secuencia de diseño de emisión de ficha en kiosco")
    b.figure("27_sec_consulta_diseno.png", 16.2, "Secuencia de diseño de registro de consulta y pedidos")
    b.note("Las secuencias de diseño muestran el orden de los mensajes y las transacciones. La validación de disponibilidad, la prevención de superposiciones y la emisión de la ficha se ejecutan en el servidor; la interfaz solo presenta los resultados y los mensajes de error.", "Trazabilidad análisis–diseño")

    b.h2("6.3 Diseño de datos (lógico y físico)")
    b.p("El diseño de datos comprende dos niveles. El nivel lógico se expresa con los modelos entidad-relación y las relaciones normalizadas del dominio. El nivel físico precisa los tipos de PostgreSQL, las claves, los índices, los enums, los triggers y las políticas RLS que materializan el modelo en la base de datos `clinica_vida_salud`.")
    b.p("El modelo relacional se normaliza para evitar duplicidad y se complementa con enums, índices y restricciones. Las entidades con historial no se eliminan físicamente. Los códigos visibles se generan en el servicio mediante secuencias por prefijo. La tabla `auditoria` se alimenta por trigger y no admite escritura directa desde la aplicación.")
    b.p("Las relaciones principales del modelo, derivadas del diagrama de clases y de la especificación del proyecto, son las siguientes. Cada relación se implementa con claves foráneas y restricciones de integridad, y se conserva la trazabilidad con las clases del dominio de la sección 5.5.")
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

    b.h2("6.4 Patrones y decisiones de diseño")
    b.bullets([
        ("MVC: ", "separa presentación, coordinación y datos; facilita el mantenimiento y las pruebas."),
        ("Front Controller: ", "un único punto de entrada resuelve rutas y middleware."),
        ("Service Layer: ", "concentra reglas de negocio y transacciones fuera de los controladores."),
        ("Repository/Model: ", "encapsula SQL parametrizado y operaciones de persistencia."),
        ("Dependency Injection simple: ", "los controladores reciben servicios y modelos desde el contenedor."),
        ("Template Method: ", "los layouts y vistas reutilizan estructura y bloques."),
        ("Token firmado: ", "el kiosco utiliza HMAC con expiración corta."),
    ])

    b.h2("6.5 Seguridad y control de acceso")
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
            ("Entrada", "Validación cliente/servidor y PDO preparado."),
            ("Salida", "htmlspecialchars() y respuestas JSON controladas."),
        ],
        [3.6, 12.4],
        caption="Controles de seguridad del diseño",
    )

    b.h2("6.6 Diseño de interfaz y navegación")
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
            ("Kiosco", "Paciente presencial", "Identificación, especialidad, médico, slot y ficha."),
            ("Consulta", "Médico", "Signos vitales, diagnóstico, tratamiento y pedidos."),
            ("Reportes", "Admin", "Demanda, atención, cancelaciones y productividad."),
        ],
        [3.2, 4.2, 8.6],
        caption="Vistas principales de la interfaz",
        font_size=8.2,
    )

    b.h2("6.7 Diseño de la API")
    b.table(
        ["Grupo", "Endpoints representativos", "Propósito"],
        [
            ("Autenticación", "POST /auth/login, /auth/recuperar, /auth/reset", "Sesión y recuperación."),
            ("Catálogos", "GET/POST /api/pacientes, /api/medicos, /api/especialidades", "CRUD de catálogos."),
            ("Horarios", "GET /api/disponibilidad, POST /api/medicos/{id}/horarios", "Cupos y slots."),
            ("Citas", "POST /api/citas, PUT /api/citas/{id}/reprogramar, PATCH /api/citas/{id}/cancelar", "Ciclo de la cita."),
            ("Estados", "PATCH /api/citas/{id}/atender, /inasistencia, /reconsulta", "Asistencia y reconsultas."),
            ("Kiosco", "POST /api/kiosco/reservar, /api/kiosco/ficha", "Reserva y ficha."),
            ("Clínica", "POST /api/consultas, /recetas, /ordenes", "Historia y pedidos."),
            ("Reportes", "GET /api/reportes/agenda, /demanda, /pagos", "Indicadores."),
        ],
        [3.0, 8.0, 5.0],
        caption="Grupos de endpoints de la API",
        font_size=8.1,
    )

    b.h2("6.8 Decisiones de diseño relevantes")
    b.table(
        ["Decisión", "Alternativa considerada", "Justificación"],
        [
            ("MVC manual en PHP", "Framework completo", "Restricción académica y control didáctico."),
            ("PostgreSQL con RLS", "Filtrado solo en PHP", "Defensa en profundidad y aislamiento por rol."),
            ("Cupos por franja", "Cupos globales", "Garantiza el 50/50 web/kiosco."),
            ("Bloqueo pesimista", "Solo validación previa", "Evita carreras en reservas concurrentes."),
            ("Ficha digital con QR", "Ficha solo impresa", "Reduce papel y permite validación rápida."),
            ("WhatsApp por deep link", "API Business", "Menor costo y complejidad para el alcance."),
        ],
        [4.2, 4.8, 7.0],
        caption="Decisiones de diseño y justificación",
        font_size=8.2,
    )
