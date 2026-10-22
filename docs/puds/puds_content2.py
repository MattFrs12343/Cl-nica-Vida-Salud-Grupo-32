# -*- coding: utf-8 -*-
from puds_lib import b


def chapter5():
    b.h1("5. ANÁLISIS DEL SISTEMA")
    b.h2("5.1 Propósito del análisis")
    b.p("El análisis transforma los requisitos en una comprensión estructurada del dominio y de las responsabilidades del sistema. Se identifican clases de frontera, control y entidad; se modelan las relaciones del dominio; se realizan los casos de uso mediante diagramas de secuencia; y se especifica el ciclo de vida de la cita. El resultado sirve de puente entre la captura de requisitos y el diseño arquitectónico.")
    b.p("El análisis se concentra en la trazabilidad: cada caso de uso crítico se realiza con clases concretas y cada regla de negocio se refleja en una validación o transición. Los diagramas de análisis no describen todavía decisiones de implementación, sino responsabilidades y colaboraciones.")

    b.h2("5.2 Clases de análisis")
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

    b.h2("5.3 Modelo de dominio")
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

    b.h2("5.4 Realización de casos de uso")
    b.p("Las realizaciones de casos de uso describen la interacción entre actores, fronteras, controles y entidades. Se documentan los flujos de login, reserva web, kiosco, cancelación y consulta médica, por ser los de mayor criticidad para la operación y la seguridad.")
    b.figure("08_sec_login.png", 15.8, "Secuencia de inicio de sesión y control de intentos")
    b.figure("09_sec_reserva.png", 16.0, "Secuencia de reserva de cita con validación de disponibilidad")
    b.figure("10_sec_kiosco.png", 16.0, "Secuencia de emisión de ficha en el kiosco")
    b.figure("11_sec_cancelar.png", 15.8, "Secuencia de cancelación de cita")
    b.figure("12_sec_consulta.png", 16.0, "Secuencia de registro de consulta y generación de pedidos")

    b.h2("5.5 Ciclo de vida de la cita")
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

    b.h2("5.6 Colaboraciones y reglas de análisis")
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

    b.h2("5.7 Riesgos identificados en el análisis")
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
    b.h2("6.1 Arquitectura lógica")
    b.p("El sistema se organiza en capas: presentación, aplicación, dominio e infraestructura. La capa de presentación incluye las vistas Gentelella de los paneles, la landing y el kiosco. La capa de aplicación contiene controladores y middleware. El dominio agrupa servicios y reglas de negocio. La infraestructura comprende modelos PDO, correo, generación de QR y acceso a PostgreSQL.")
    b.figure("06_arquitectura_logica.png", 16.0, "Arquitectura lógica por capas y componentes")
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
        caption="Capas de la arquitectura lógica",
        font_size=8.2,
    )

    b.h2("6.2 Patrones y decisiones de diseño")
    b.bullets([
        ("MVC: ", "separa presentación, coordinación y datos; facilita el mantenimiento y las pruebas."),
        ("Front Controller: ", "un único punto de entrada resuelve rutas y middleware."),
        ("Service Layer: ", "concentra reglas de negocio y transacciones fuera de los controladores."),
        ("Repository/Model: ", "encapsula SQL parametrizado y operaciones de persistencia."),
        ("Dependency Injection simple: ", "los controladores reciben servicios y modelos desde el contenedor."),
        ("Template Method: ", "los layouts y vistas reutilizan estructura y bloques."),
        ("Token firmado: ", "el kiosco utiliza HMAC con expiración corta."),
    ])

    b.h2("6.3 Arquitectura de despliegue")
    b.p("El despliegue productivo separa la aplicación web y la base de datos. El código PHP se publica en un hosting cPanel con HTTPS; PostgreSQL se aloja en Neon o Railway y se accede con `sslmode=require`. El navegador del paciente y el iPad del kiosco se conectan por HTTPS. El servidor de correo se integra mediante SMTP de Brevo.")
    b.figure("07_despliegue.png", 16.0, "Diagrama de despliegue del sistema")
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

    b.h2("6.4 Diseño de la base de datos")
    b.p("El modelo relacional se normaliza para evitar duplicidad y se complementa con enums, índices y restricciones. Las entidades con historial no se eliminan físicamente. Los códigos visibles se generan en el servicio mediante secuencias por prefijo. La tabla `auditoria` se alimenta por trigger y no admite escritura directa desde la aplicación.")
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
